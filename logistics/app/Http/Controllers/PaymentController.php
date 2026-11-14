<?php

namespace App\Http\Controllers;

use App\Enums\PaymentResultStatus;
use App\Models\User;
use App\Models\UserCar;
use App\Models\UserOrder;
use App\Models\UserSubscription;
use App\Services\PaytrRecurringService;
use App\Services\PricingCatalog;
use App\Services\SubscriptionService;
use App\Services\SlackService;
use App\Traits\ResponseTrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PaymentController extends Controller
{
    use ResponseTrait;

    public function createPayment(Request $request, SlackService $slackService)
    {
        $validated = $request->validate([
            // package: üyelik paketi, vehicle: ek araç aylık ücreti (tutar sunucuda hesaplanır)
            'purpose' => ['nullable', 'in:package,vehicle'],
            'user_car_id' => ['required_if:purpose,vehicle', 'nullable', 'integer'],
            // Otomatik talimat için kart PayTR'de saklanır (varsayılan açık)
            'store_card' => ['nullable', 'boolean'],
            'plan' => ['required_unless:purpose,vehicle', 'nullable', 'array'],
            'plan.id' => ['required_with:plan', 'string'],
            // Paket türü (individual / small / medium / large); yoksa Bireysel
            'plan.tier' => ['nullable', 'string', 'max:32'],
            'plan.months' => ['required_with:plan', 'integer', 'in:1,3,6,12'],
            'plan.total_numeric' => ['required_with:plan', 'numeric', 'min:1'],
            'plan.name' => ['required_with:plan', 'string'],
            'cc_owner' => ['required', 'string', 'max:255'],
            'card_number' => ['required', 'regex:/^[0-9]{16}$/'],
            'expiry_month' => ['required', 'regex:/^(0[1-9]|1[0-2])$/'],
            'expiry_year' => ['required', 'regex:/^[0-9]{2}$/'],
            'cvv' => ['required', 'regex:/^[0-9]{3,4}$/'],
        ], [
            'plan.required' => 'Paket bilgisi zorunludur.',
            'plan.required_unless' => 'Paket bilgisi zorunludur.',
            'user_car_id.required_if' => 'Araç bilgisi zorunludur.',
            'cc_owner.required' => 'Kart üzerindeki ad zorunludur.',
            'card_number.required' => 'Kart numarası zorunludur.',
            'card_number.regex' => 'Kart numarası 16 haneli olmalıdır.',
            'expiry_month.regex' => 'Ay 01–12 arasında olmalıdır.',
            'expiry_year.regex' => 'Yıl iki hane olmalıdır.',
            'cvv.regex' => 'CVC 3 veya 4 haneli olmalıdır.',
        ]);

        $user = $request->user();
        $purpose = (string) ($validated['purpose'] ?? 'package');
        $carId = null;

        if ($purpose === 'vehicle') {
            $car = UserCar::where('id', (int) $validated['user_car_id'])->where('user_id', $user->id)->where('status', 'active')->first();
            if (! $car) {
                return $this->error([], 'Araç bulunamadı.', [], 404);
            }
            if (! app(SubscriptionService::class)->isBillableCar($user, (int) $car->id)) {
                return $this->error([], 'Bu araç paketinize dahil, ayrıca ödeme gerekmez.', [], 422);
            }
            $carId = (int) $car->id;
            $amounts = SubscriptionService::withVat(SubscriptionService::VEHICLE_PRICE);
            $plan = [
                'id' => 'vehicle',
                'months' => 1,
                'name' => 'Araç aboneliği ('.$car->plate.')',
                'total_numeric' => $amounts['total'],
            ];
        } else {
            // Tutar istemciden değil katalogdan: paket türü + süre
            $catalog = app(PricingCatalog::class);
            $tier = (string) ($validated['plan']['tier'] ?? PricingCatalog::DEFAULT_TIER);
            $months = (int) $validated['plan']['months'];
            if (! $catalog->allowedFor($tier, (string) $user->type)) {
                return $this->error([], 'Bu paket hesabınız için uygun değil.', [], 422);
            }
            $price = $catalog->price($tier, $months);
            if ($price === null) {
                return $this->error([], 'Paket süresi geçersiz.', [], 422);
            }
            $plan = [
                'id' => $tier.'_'.$months,
                'tier' => $tier,
                'months' => $months,
                'name' => $catalog->label($tier, $months),
                'total_numeric' => $price,
            ];
        }

        $merchantOid = $this->generateMerchantOid($user->id);
        $order = null;

        try {
            $this->assertPaytrConfigured();

            $paymentAmount = (float) $plan['total_numeric'];

            $order = UserOrder::create([
                'user_id' => $user->id,
                'order_type' => $purpose,
                'user_car_id' => $carId,
                'merchant_oid' => $merchantOid,
                'package_name' => $plan['name'],
                'package_tier' => $plan['tier'] ?? null,
                'package_price' => (string) $plan['total_numeric'],
                'plan_months' => (int) $plan['months'],
                'status' => PaymentResultStatus::PENDING->value,
                'message' => 'Awaiting PayTR confirmation',
            ]);

            $response = $this->sendPaytrDirectPayment($request, $validated, $plan, $merchantOid, $paymentAmount);

            if (is_array($response)) {
                $errorMessage = (string) ($response['message'] ?? 'PayTR isteği başarısız.');
                $order->update([
                    'status' => PaymentResultStatus::FAILED->value,
                    'message' => $errorMessage,
                ]);

                $this->notifySlackPayment(
                    $slackService,
                    $user,
                    $plan,
                    'slack.payment_failed',
                    SlackService::COLOR_DANGER,
                    $errorMessage
                );

                return $this->error([], $errorMessage, [], 502);
            }

            Log::info('PayTR create payment response', [
                'merchant_oid' => $merchantOid,
                'user_id' => $user->id,
                'response_length' => is_string($response) ? strlen($response) : 0,
            ]);

            $parsed = $this->parsePaytrCreateResponse($response);

            if ($parsed['type'] === 'error') {
                $order->update([
                    'status' => PaymentResultStatus::FAILED->value,
                    'message' => $parsed['message'],
                ]);

                $this->notifySlackPayment(
                    $slackService,
                    $user,
                    $plan,
                    'slack.payment_failed',
                    SlackService::COLOR_DANGER,
                    $parsed['message']
                );

                return $this->error([], $parsed['message'], [], 422);
            }

            if ($parsed['type'] === 'html') {
                $token = Str::random(48);
                Cache::put('paytr_3ds:'.$token, $parsed['html'], now()->addMinutes(15));
                $threeDsUrl = $this->paytrApiBase().'/api/payment/3ds/'.$token;

                return $this->success(
                    [
                        'paytr_redirect_url' => $threeDsUrl,
                        'merchant_oid' => $merchantOid,
                    ],
                    '3D doğrulama adımına yönlendiriliyorsunuz.'
                );
            }

            if ($parsed['type'] === 'redirect') {
                return $this->success(
                    [
                        'paytr_redirect_url' => $parsed['url'],
                        'merchant_oid' => $merchantOid,
                    ],
                    '3D doğrulama adımına yönlendiriliyorsunuz.'
                );
            }

            if ($parsed['type'] === 'pending') {
                return $this->success(
                    [
                        'status' => 'pending',
                        'merchant_oid' => $merchantOid,
                    ],
                    $parsed['message'] ?? 'Ödeme işlemi devam ediyor. Onay sonrası premium aktif olacaktır.'
                );
            }

            if ($parsed['type'] === 'completed') {
                $this->finalizeSuccessfulPayment($order, $user, $slackService, $plan);

                return $this->success(
                    [
                        'status' => 'paid',
                        'merchant_oid' => $merchantOid,
                    ],
                    'Ödeme başarılı. Premium hesabınız aktif edildi.'
                );
            }

            Log::warning('PayTR unexpected response', [
                'merchant_oid' => $merchantOid,
                'snippet' => is_string($response) ? Str::limit($response, 200) : null,
            ]);

            $order->update([
                'status' => PaymentResultStatus::FAILED->value,
                'message' => 'Unexpected PayTR response',
            ]);

            return $this->error([], 'PayTR beklenmeyen yanıt verdi. Lütfen tekrar deneyin.', [], 502);
        } catch (\Throwable $th) {
            if ($order) {
                $order->update([
                    'status' => PaymentResultStatus::FAILED->value,
                    'message' => $th->getMessage(),
                ]);
            } else {
                UserOrder::create([
                    'user_id' => $user->id,
                    'merchant_oid' => $merchantOid,
                    'package_name' => $plan['name'],
                    'package_price' => (string) $plan['total_numeric'],
                    'plan_months' => (int) $plan['months'],
                    'status' => PaymentResultStatus::FAILED->value,
                    'message' => $th->getMessage(),
                ]);
            }

            $this->notifySlackPayment(
                $slackService,
                $user,
                $plan,
                'slack.payment_failed',
                SlackService::COLOR_DANGER,
                $th->getMessage()
            );

            return $this->error([], $th->getMessage(), [], 500);
        }
    }

    /**
     * PayTR 3D Secure HTML sayfasını tek kullanımlık token ile sunar (mobil tarayıcı uyumu).
     */
    public function showThreeDsPage(string $token): Response
    {
        $html = Cache::get('paytr_3ds:'.$token);

        if (! is_string($html) || trim($html) === '') {
            return response('3D doğrulama oturumu bulunamadı veya süresi doldu.', 404);
        }

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * PayTR 3D Secure sonrası başarılı dönüş (POST veya GET) — frontend'e GET yönlendirir.
     */
    public function merchantReturnOk(Request $request): RedirectResponse
    {
        return $this->redirectToFrontendPaymentPage($request, 'ok');
    }

    /**
     * PayTR 3D Secure sonrası başarısız dönüş (POST veya GET) — frontend'e GET yönlendirir.
     */
    public function merchantReturnFail(Request $request): RedirectResponse
    {
        return $this->redirectToFrontendPaymentPage($request, 'fail');
    }

    /**
     * PayTR bildirim URL (2. adım) — oturum gerektirmez, yanıt yalnızca "OK" olmalıdır.
     */
    public function callback(Request $request, SlackService $slackService): Response
    {
        $post = $request->post();

        $merchantKey = (string) config('paytr.store_key');
        $merchantSalt = (string) config('paytr.store_secret_key');

        if (
            ! isset($post['merchant_oid'], $post['status'], $post['total_amount'], $post['hash'])
            || $merchantKey === ''
            || $merchantSalt === ''
        ) {
            return response('PAYTR notification failed: missing data', 400);
        }

        $hash = base64_encode(hash_hmac(
            'sha256',
            $post['merchant_oid'].$merchantSalt.$post['status'].$post['total_amount'],
            $merchantKey,
            true
        ));

        if (! hash_equals($hash, (string) $post['hash'])) {
            Log::warning('PayTR callback hash mismatch', ['merchant_oid' => $post['merchant_oid']]);

            return response('PAYTR notification failed: bad hash', 400);
        }

        $order = UserOrder::query()
            ->where('merchant_oid', $post['merchant_oid'])
            ->first();

        if (! $order) {
            Log::warning('PayTR callback order not found', ['merchant_oid' => $post['merchant_oid']]);

            return response('OK', 200);
        }

        if (in_array($order->status, [PaymentResultStatus::PAID->value, PaymentResultStatus::FAILED->value], true)
            && $post['status'] !== 'success') {
            return response('OK', 200);
        }

        $user = User::query()->find($order->user_id);

        if (! $user) {
            return response('OK', 200);
        }

        // Kart saklandıysa PayTR kullanıcı kart grubunu (utoken) bildirir; otomatik ödemede kullanılır
        $utoken = trim((string) ($post['utoken'] ?? ''));
        if ($post['status'] === 'success' && $utoken !== '' && $user->paytr_utoken !== $utoken) {
            $user->forceFill(['paytr_utoken' => $utoken])->save();
        }

        $planMonths = (int) ($order->plan_months ?? 1);
        $plan = [
            'name' => $order->package_name,
            'total_numeric' => $order->package_price,
            'months' => $planMonths,
        ];

        if ($post['status'] === 'success') {
            $this->finalizeSuccessfulPayment($order, $user, $slackService, $plan);
        } else {
            if ($order->status === PaymentResultStatus::PAID->value) {
                return response('OK', 200);
            }

            $failMessage = (string) ($post['failed_reason_msg'] ?? 'Payment failed');
            $order->update([
                'status' => PaymentResultStatus::FAILED->value,
                'message' => $failMessage,
            ]);

            $this->notifySlackPayment(
                $slackService,
                $user,
                $plan,
                'slack.payment_failed',
                SlackService::COLOR_DANGER,
                $this->formatPaytrFailureReason($post)
            );
        }

        return response('OK', 200);
    }

    /**
     * Başarı sayfasından çağrılır: PayTR callback gelmemişse durum sorgusu ile premium aktif eder.
     */
    public function syncPaymentStatus(Request $request, SlackService $slackService)
    {
        $validated = $request->validate([
            'merchant_oid' => ['nullable', 'string', 'max:64'],
        ]);

        $user = $request->user();
        $merchantOid = trim((string) ($validated['merchant_oid'] ?? ''));

        if ($merchantOid === '' && (int) $user->payment_confirm === 1) {
            return $this->success(['status' => 'active'], 'Premium zaten aktif.');
        }

        $orderQuery = UserOrder::query()->where('user_id', $user->id);

        if ($merchantOid !== '') {
            $orderQuery->where('merchant_oid', $merchantOid);
        } else {
            $orderQuery
                ->where('created_at', '>=', now()->subHours(2))
                ->orderByDesc('id');
        }

        $order = $orderQuery->first();

        if (! $order) {
            return $this->success(['status' => 'unknown'], 'Bekleyen sipariş bulunamadı.');
        }

        $plan = $this->buildPlanFromOrder($order);

        if ($order->status === PaymentResultStatus::PAID->value) {
            $this->finalizeSuccessfulPayment($order, $user, $slackService, $plan, notifySlack: false);

            return $this->success(['status' => 'paid'], 'Premium aktif edildi.');
        }

        if ($order->status === PaymentResultStatus::FAILED->value) {
            return $this->success(['status' => 'failed'], 'Ödeme tamamlanamadı.');
        }

        $inquiry = $this->queryPaytrPaymentStatus((string) $order->merchant_oid);

        if ($inquiry['paid']) {
            $this->finalizeSuccessfulPayment($order, $user, $slackService, $plan);

            return $this->success(['status' => 'paid'], 'Ödeme onaylandı. Premium aktif edildi.');
        }

        if ($inquiry['failed']) {
            $order->update([
                'status' => PaymentResultStatus::FAILED->value,
                'message' => $inquiry['message'] ?? 'PayTR durum sorgusu: ödeme başarısız',
            ]);

            return $this->success(['status' => 'failed'], $inquiry['message'] ?? 'Ödeme tamamlanamadı.');
        }

        return $this->success(['status' => 'pending'], 'Ödeme henüz onaylanmadı. Birkaç saniye sonra tekrar denenecek.');
    }

    /**
     * Uygulamanın açacağı sunucu adresleri (3D sayfası, PayTR dönüşü).
     * Canlıda APP_URL; yerelde isteğin geldiği adres (emülatör 10.0.2.2:8000, telefon 192.168.x.x:8000) —
     * `localhost` cihazın kendisini gösterdiği için emülatörde açılmaz.
     */
    private function paytrApiBase(): string
    {
        $configured = rtrim((string) config('paytr.api_url'), '/');
        if (app()->environment('production')) {
            return $configured;
        }

        $request = request();
        $host = $request?->getHost();
        if ($host && ! in_array($host, ['localhost', '127.0.0.1'], true)) {
            return rtrim($request->getSchemeAndHttpHost(), '/');
        }

        return $configured;
    }

    private function buildPaytrMerchantReturnUrl(string $type, string $merchantOid): string
    {
        $apiBase = $this->paytrApiBase();
        $path = $type === 'ok'
            ? (string) config('paytr.merchant_return_ok_path', '/api/payment/return/ok')
            : (string) config('paytr.merchant_return_fail_path', '/api/payment/return/fail');

        return $apiBase.$path.'?merchant_oid='.urlencode($merchantOid);
    }

    private function redirectToFrontendPaymentPage(Request $request, string $type): RedirectResponse
    {
        $merchantOid = $request->input('merchant_oid') ?? $request->query('merchant_oid');
        $frontendUrl = rtrim((string) config('paytr.frontend_url'), '/');
        $path = $type === 'ok'
            ? (string) config('paytr.merchant_ok_path', '/odeme/basarili')
            : (string) config('paytr.merchant_fail_path', '/odeme/hata');

        $url = $frontendUrl.$path;
        if (is_string($merchantOid) && trim($merchantOid) !== '') {
            $url .= '?merchant_oid='.urlencode(trim($merchantOid));
        }

        return redirect()->away($url, 303);
    }

    /**
     * Ayarlar > Abonelik: aktif paket (1 / 3 / 6 / 12 aylık), ödenen tutar, bitiş tarihi ve kalan gün;
     * altında son ödemeler.
     */
    public function subscription(Request $request, SubscriptionService $subscriptions)
    {
        $user = $request->user();
        $tz = SubscriptionService::TZ;
        $now = Carbon::now($tz);

        $current = $subscriptions->current($user);
        $end = $current ? Carbon::parse($current->ends_at)->timezone($tz) : null;
        $active = $end !== null && $end->greaterThan($now);
        // Aktif paket yoksa son paket (bitiş bilgisi için)
        $last = $current ?? UserSubscription::where('user_id', $user->id)->where('type', 'package')->orderByDesc('id')->first();
        $lastEnd = $last ? Carbon::parse($last->ends_at)->timezone($tz) : null;

        $daysLeft = $active ? (int) ceil($now->diffInSeconds($end) / 86400) : 0;
        $totalDays = $active ? (int) ceil(Carbon::parse($current->starts_at)->timezone($tz)->diffInSeconds($end) / 86400) : 0;

        $row = fn (UserSubscription $s) => [
            'id' => (int) $s->id,
            'type' => (string) $s->type,
            'tier' => $s->package_tier,
            'vehicle_limit' => $s->vehicle_limit !== null ? (int) $s->vehicle_limit : null,
            'name' => $s->package_name ?: self::subscriptionSourceLabel($s->source),
            'months' => (int) ($s->plan_months ?? 0),
            'price' => $s->price !== null ? (float) $s->price : null,
            'source' => (string) $s->source,
            'status' => (string) $s->status,
            'auto_renew' => (bool) $s->auto_renew,
            'paid_at' => Carbon::parse($s->starts_at)->timezone($tz)->toIso8601String(),
            'ends_at' => Carbon::parse($s->ends_at)->timezone($tz)->toIso8601String(),
        ];

        // Araçlar: ilk araç pakete dahil; sonraki her araç aylık ücretli (her ay otomatik yenilenir)
        $billableIds = $subscriptions->billableCars($user)->pluck('id')->all();
        $vehicles = UserCar::with('car')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(function (UserCar $c) use ($billableIds, $subscriptions, $tz, $now) {
                $billable = in_array($c->id, $billableIds, true);
                $sub = $billable ? $subscriptions->vehicleSubscription((int) $c->id) : null;
                $subEnd = $sub ? Carbon::parse($sub->ends_at)->timezone($tz) : null;

                return [
                    'id' => (int) $c->id,
                    'plate' => (string) $c->plate,
                    'name' => $c->car?->name,
                    // null = pakete dahil
                    'price' => $billable ? (float) SubscriptionService::VEHICLE_PRICE : null,
                    // paid: bu ay ödendi, unpaid: ödeme bekleniyor, included: pakete dahil
                    'payment_status' => ! $billable ? 'included' : ($subEnd && $subEnd->greaterThan($now) ? 'paid' : 'unpaid'),
                    'ends_at' => $subEnd?->toIso8601String(),
                    'auto_renew' => $sub ? (bool) $sub->auto_renew : null,
                ];
            })
            ->values();
        $vehiclesTotal = (float) $vehicles->sum(fn ($v) => $v['price'] ?? 0);
        $packagePrice = $last?->price !== null ? (float) $last->price : 0.0;

        return $this->success([
            'active' => $active,
            'package' => $last ? $row($last) : null,
            'vehicles' => $vehicles,
            // Paketin kapsadığı araç sayısı (sonrakiler araç başı aylık)
            'included_vehicles' => $subscriptions->includedVehicles($user),
            'vehicles_total' => $vehiclesTotal,
            'vehicle_price' => SubscriptionService::withVat(SubscriptionService::VEHICLE_PRICE),
            // Paket + ek araçlar
            'total_price' => $packagePrice + $vehiclesTotal,
            // KDV %20 (paket + ek araçların toplamı üzerinden) ve KDV dahil toplam
            'vat_rate' => self::VAT_RATE,
            'vat_amount' => round(($packagePrice + $vehiclesTotal) * self::VAT_RATE / 100, 2),
            'total_with_vat' => round(($packagePrice + $vehiclesTotal) * (1 + self::VAT_RATE / 100), 2),
            // Otomatik talimat: aktif paketin tercihi; kayıtlı kart var mı
            'auto_renew' => $current ? (bool) $current->auto_renew : false,
            'has_saved_card' => (string) ($user->paytr_utoken ?? '') !== '',
            'end_date' => $lastEnd?->toIso8601String(),
            'days_left' => $daysLeft,
            'total_days' => max($totalDays, $daysLeft),
            'history' => UserSubscription::where('user_id', $user->id)->orderByDesc('id')->limit(30)->get()->map($row)->values(),
        ]);
    }

    /**
     * Paket kataloğu: hesap türüne göre (oturum varsa kullanıcının türü, yoksa `account_type`).
     */
    public function packages(Request $request, PricingCatalog $catalog)
    {
        $type = $request->user('sanctum')?->type ?? $request->query('account_type');
        $type = in_array($type, ['vehicle_owner', 'cargo_owner'], true) ? $type : null;

        return $this->success([
            'packages' => $catalog->forAccountType($type),
            'extra_vehicle_price' => (float) config('pricing.extra_vehicle_price', 299),
            'vat_rate' => (int) config('pricing.vat_rate', 20),
        ]);
    }

    /**
     * Otomatik talimatı aç / kapat (aktif paket ve araç kayıtları).
     */
    public function setAutoRenew(Request $request, SubscriptionService $subscriptions)
    {
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);
        $enabled = (bool) $validated['enabled'];
        $user = $request->user();

        if ($enabled && (string) ($user->paytr_utoken ?? '') === '') {
            return $this->error(
                ['needs_card' => true],
                'Otomatik talimat için önce kartla bir ödeme yapmalısınız; kartınız PayTR\'de güvenle saklanır.',
                [],
                422
            );
        }

        $subscriptions->setAutoRenew($user, $enabled);

        return $this->success(['auto_renew' => $enabled], $enabled ? 'Otomatik talimat açıldı.' : 'Otomatik talimat kapatıldı.');
    }

    /**
     * Ek araç aylık ücreti: kayıtlı kart varsa doğrudan çekilir; yoksa `needs_card` döner ve kart formu açılır.
     */
    public function chargeVehicle(Request $request, int $carId, SubscriptionService $subscriptions, PaytrRecurringService $paytr)
    {
        $user = $request->user();
        $car = UserCar::where('id', $carId)->where('user_id', $user->id)->where('status', 'active')->first();
        if (! $car) {
            return $this->error([], 'Araç bulunamadı.', [], 404);
        }
        if (! $subscriptions->isBillableCar($user, (int) $car->id)) {
            return $this->success(['status' => 'included'], 'Bu araç paketinize dahil.');
        }

        $amounts = SubscriptionService::withVat(SubscriptionService::VEHICLE_PRICE);
        $existing = $subscriptions->vehicleSubscription((int) $car->id);
        if ($existing && Carbon::parse($existing->ends_at)->isFuture()) {
            return $this->success(['status' => 'paid', 'ends_at' => Carbon::parse($existing->ends_at)->toIso8601String()], 'Bu aracın ödemesi yapılmış.');
        }

        if ((string) ($user->paytr_utoken ?? '') === '') {
            return $this->success(['needs_card' => true, 'amount' => $amounts], 'Kart bilgisi gerekli.');
        }

        $merchantOid = $this->generateMerchantOid($user->id);
        $name = 'Araç aboneliği ('.$car->plate.')';
        $order = UserOrder::create([
            'user_id' => $user->id,
            'order_type' => 'vehicle',
            'user_car_id' => $car->id,
            'merchant_oid' => $merchantOid,
            'package_name' => $name,
            'package_price' => (string) $amounts['total'],
            'plan_months' => 1,
            'status' => PaymentResultStatus::PENDING->value,
            'message' => 'Kayıtlı karttan ödeme',
        ]);

        $result = $paytr->charge($user, $merchantOid, (float) $amounts['total'], $name);

        if ($result['status'] === 'success') {
            $order->update(['status' => PaymentResultStatus::PAID->value, 'message' => 'Paid successfully']);
            $sub = $subscriptions->grantFromOrder($user, $order);

            return $this->success(['status' => 'paid', 'ends_at' => Carbon::parse($sub->ends_at)->toIso8601String()], 'Araç ödemesi alındı.');
        }

        if ($result['status'] === 'wait_callback') {
            return $this->success(['status' => 'pending', 'merchant_oid' => $merchantOid], 'Ödeme kontrol ediliyor.');
        }

        $order->update(['status' => PaymentResultStatus::FAILED->value, 'message' => $result['message']]);

        // Kayıtlı karttan alınamadı: kullanıcı yeni kart girebilsin
        return $this->error(['needs_card' => true, 'amount' => $amounts], $result['message'] ?: 'Ödeme alınamadı.', [], 422);
    }

    /** Abonelik toplamına eklenen KDV oranı (%) */
    public const VAT_RATE = 20;

    private static function subscriptionSourceLabel(?string $source): string
    {
        return match ($source) {
            'trial' => 'Deneme süresi',
            'admin' => 'Yönetici tanımı',
            default => 'Abonelik',
        };
    }

    private function generateMerchantOid(int $userId): string
    {
        return 'TB'.$userId.'T'.time().Str::upper(Str::random(6));
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function finalizeSuccessfulPayment(
        UserOrder $order,
        User $user,
        SlackService $slackService,
        array $plan,
        bool $notifySlack = true
    ): void {
        $wasAlreadyPaid = $order->status === PaymentResultStatus::PAID->value;

        if (! $wasAlreadyPaid) {
            $order->update([
                'status' => PaymentResultStatus::PAID->value,
                'message' => 'Paid successfully',
            ]);

            if ($notifySlack) {
                $this->notifySlackPayment($slackService, $user, $plan, 'slack.payment_paid', SlackService::COLOR_SUCCESS);
            }
        }

        // Aynı sipariş için tek abonelik kaydı (callback + sync ikisi de gelse bile)
        app(SubscriptionService::class)->grantFromOrder($user, $order);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPlanFromOrder(UserOrder $order): array
    {
        return [
            'name' => $order->package_name,
            'total_numeric' => $order->package_price,
            'months' => (int) ($order->plan_months ?? 1),
        ];
    }

    /**
     * @return array{paid: bool, failed: bool, pending: bool, message?: string}
     */
    private function queryPaytrPaymentStatus(string $merchantOid): array
    {
        $merchantId = (string) config('paytr.store_no');
        $merchantKey = (string) config('paytr.store_key');
        $merchantSalt = (string) config('paytr.store_secret_key');

        if ($merchantId === '' || $merchantKey === '' || $merchantSalt === '' || $merchantOid === '') {
            return ['paid' => false, 'failed' => false, 'pending' => true];
        }

        $paytrToken = base64_encode(hash_hmac(
            'sha256',
            $merchantId.$merchantOid.$merchantSalt,
            $merchantKey,
            true
        ));

        $postFields = [
            'merchant_id' => $merchantId,
            'merchant_oid' => $merchantOid,
            'paytr_token' => $paytrToken,
        ];

        $apiUrl = rtrim((string) config('paytr.url'), '/')
            .(string) config('paytr.status_inquiry_path', '/odeme/durum-sorgu');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (bool) config('paytr.verify_ssl', true));
        $response = curl_exec($ch);
        curl_close($ch);

        if (! is_string($response) || trim($response) === '') {
            Log::warning('PayTR status inquiry empty response', ['merchant_oid' => $merchantOid]);

            return ['paid' => false, 'failed' => false, 'pending' => true];
        }

        $json = json_decode($response, true);
        if (! is_array($json)) {
            Log::warning('PayTR status inquiry invalid JSON', [
                'merchant_oid' => $merchantOid,
                'snippet' => Str::limit($response, 200),
            ]);

            return ['paid' => false, 'failed' => false, 'pending' => true];
        }

        if (($json['status'] ?? '') === 'success') {
            return ['paid' => true, 'failed' => false, 'pending' => false];
        }

        $errMsg = trim((string) ($json['err_msg'] ?? ''));
        $errNo = trim((string) ($json['err_no'] ?? ''));

        Log::info('PayTR status inquiry not paid yet', [
            'merchant_oid' => $merchantOid,
            'err_no' => $errNo,
            'err_msg' => $errMsg,
        ]);

        if (
            str_contains(mb_strtolower($errMsg), 'basarili odeme bulunamadi')
            || str_contains(mb_strtolower($errMsg), 'islem bulunamadi')
            || $errNo === '005'
        ) {
            return ['paid' => false, 'failed' => false, 'pending' => true];
        }

        return [
            'paid' => false,
            'failed' => true,
            'pending' => false,
            'message' => $errMsg !== '' ? $errMsg : 'PayTR ödeme durumu alınamadı.',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $plan
     * @return string|array{type: 'error', message: string}
     */
    private function sendPaytrDirectPayment(
        Request $request,
        array $validated,
        array $plan,
        string $merchantOid,
        float $paymentAmount
    ): string|array {
        $merchantId = (string) config('paytr.store_no');
        $merchantKey = (string) config('paytr.store_key');
        $merchantSalt = (string) config('paytr.store_secret_key');

        $merchantOkUrl = $this->buildPaytrMerchantReturnUrl('ok', $merchantOid);
        $merchantFailUrl = $this->buildPaytrMerchantReturnUrl('fail', $merchantOid);

        $userIp = $this->resolvePaytrUserIp($request);
        $email = (string) $request->user()->email;
        $currency = 'TL';
        $paymentType = 'card';
        $testMode = (string) config('paytr.test_mode', '1');
        $non3d = (string) config('paytr.non_3d', '1');
        $installmentCount = '0';
        $paymentAmountStr = $this->formatPaytrAmount($paymentAmount);

        // curl POST: ham JSON (htmlentities yalnızca tarayıcı formu için; aksi halde PayTR sepeti okuyamaz)
        $userBasket = json_encode([
            [$plan['name'], $paymentAmountStr, 1],
        ], JSON_UNESCAPED_UNICODE);

        $hashStr = $merchantId
            .$userIp
            .$merchantOid
            .$email
            .$paymentAmountStr
            .$paymentType
            .$installmentCount
            .$currency
            .$testMode
            .$non3d;

        $paytrToken = base64_encode(
            hash_hmac('sha256', $hashStr.$merchantSalt, $merchantKey, true)
        );

        $postFields = [
            'merchant_id' => $merchantId,
            'paytr_token' => $paytrToken,
            'user_ip' => $userIp,
            'merchant_oid' => $merchantOid,
            'email' => $email,
            'payment_type' => $paymentType,
            'payment_amount' => $paymentAmountStr,
            'installment_count' => $installmentCount,
            'currency' => $currency,
            'test_mode' => $testMode,
            'non_3d' => $non3d,
            'merchant_ok_url' => $merchantOkUrl,
            'merchant_fail_url' => $merchantFailUrl,
            'user_name' => (string) $request->user()->full_name,
            'user_address' => 'Türkiye',
            'user_phone' => (string) ($request->user()->phone ?? ''),
            'user_basket' => $userBasket,
            'client_lang' => 'tr',
            'debug_on' => (string) config('paytr.debug_on', '0'),
            'cc_owner' => $validated['cc_owner'],
            'card_number' => $validated['card_number'],
            'expiry_month' => $validated['expiry_month'],
            'expiry_year' => $validated['expiry_year'],
            'cvv' => $validated['cvv'],
        ];

        // Kayıtlı kart: otomatik talimat için kart PayTR'de saklanır; kullanıcının kart grubu varsa aynı utoken
        if (filter_var($request->input('store_card', true), FILTER_VALIDATE_BOOLEAN)) {
            $postFields['store_card'] = '1';
            $utoken = (string) ($request->user()->paytr_utoken ?? '');
            if ($utoken !== '') {
                $postFields['utoken'] = $utoken;
            }
        }

        $apiUrl = rtrim((string) config('paytr.url'), '/').(string) config('paytr.direct_api_path', '/odeme');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (bool) config('paytr.verify_ssl', true));

        $response = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            Log::error('PayTR curl failed', [
                'merchant_oid' => $merchantOid,
                'curl_errno' => $curlErrno,
                'curl_error' => $curlError,
                'api_url' => $apiUrl,
            ]);

            return [
                'type' => 'error',
                'message' => $curlError !== ''
                    ? 'PayTR bağlantı hatası: '.$curlError
                    : 'PayTR yanıt vermedi. Sunucunun internete çıkışını ve PayTR ayarlarını kontrol edin.',
            ];
        }

        if ($httpCode >= 400) {
            $bodyMsg = $this->extractPaytrErrorMessage((string) $response);
            Log::warning('PayTR HTTP error', [
                'merchant_oid' => $merchantOid,
                'http_code' => $httpCode,
                'api_url' => $apiUrl,
                'snippet' => Str::limit($this->normalizePaytrResponseBody((string) $response), 500),
            ]);

            return [
                'type' => 'error',
                'message' => $bodyMsg !== ''
                    ? $bodyMsg
                    : 'PayTR HTTP '.$httpCode.' hatası döndürdü. Mağaza bilgileri ve Direkt API yetkisini kontrol edin.',
            ];
        }

        return (string) $response;
    }

    private function assertPaytrConfigured(): void
    {
        $missing = [];

        if (trim((string) config('paytr.store_no')) === '') {
            $missing[] = 'STORE_NO';
        }
        if (trim((string) config('paytr.store_key')) === '') {
            $missing[] = 'STORE_KEY';
        }
        if (trim((string) config('paytr.store_secret_key')) === '') {
            $missing[] = 'STORE_SECRET_KEY';
        }

        if ($missing !== []) {
            throw new \RuntimeException(
                'PayTR mağaza bilgileri eksik (.env: '.implode(', ', $missing).').'
            );
        }
    }

    private function resolvePaytrUserIp(Request $request): string
    {
        $override = trim((string) config('paytr.user_ip_override', ''));
        if ($override !== '') {
            return $override;
        }

        $ip = (string) $request->ip();

        if ($ip === '127.0.0.1' || $ip === '::1') {
            throw new \RuntimeException(
                'PayTR yerel IP (127.0.0.1) kabul etmez. Geliştirme için .env dosyasına PAYTR_USER_IP=gerçek_dış_ip ekleyin veya sunucuyu dışarıdan erişilebilir çalıştırın.'
            );
        }

        return $ip;
    }

    /** PayTR örnekleri: 100.99, 150, 1500.35 */
    private function formatPaytrAmount(float $amount): string
    {
        if (fmod($amount, 1.0) === 0.0) {
            return (string) (int) $amount;
        }

        return number_format($amount, 2, '.', '');
    }

    private function normalizePaytrResponseBody(string $response): string
    {
        $body = ltrim($response, "\xEF\xBB\xBF");

        if (str_starts_with($body, "\x1f\x8b") && function_exists('gzdecode')) {
            $decoded = @gzdecode($body);
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        return $body;
    }

    private function isPaytrHtmlResponse(string $body): bool
    {
        foreach (['<html', '<form', '<!doctype', '<body', '<head', '<input', '<script', '<meta'] as $needle) {
            if (stripos($body, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function paytrJsonErrorMessage(array $json): string
    {
        foreach (['reason', 'err_msg', 'msg', 'message', 'failed_reason_msg'] as $key) {
            if (! empty($json[$key]) && is_string($json[$key])) {
                return $json[$key];
            }
        }

        return 'Ödeme başarısız.';
    }

    private function extractPaytrErrorMessage(string $response): string
    {
        $trimmed = trim($this->normalizePaytrResponseBody($response));
        if ($trimmed === '') {
            return '';
        }

        $json = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return $this->paytrJsonErrorMessage($json);
        }

        if ($this->isPaytrHtmlResponse($trimmed) && preg_match('/<body[^>]*>(.*?)<\/body>/is', $trimmed, $m)) {
            $text = trim(strip_tags($m[1]));

            return $text !== '' ? Str::limit($text, 300) : '';
        }

        if (! $this->isPaytrHtmlResponse($trimmed)) {
            return Str::limit($trimmed, 300);
        }

        return '';
    }

    /**
     * @return array{type: string, message?: string, html?: string, url?: string}
     */
    private function parsePaytrCreateResponse(string|false $response): array
    {
        if ($response === false || trim((string) $response) === '') {
            return ['type' => 'error', 'message' => 'PayTR yanıt vermedi.'];
        }

        $trimmed = trim($this->normalizePaytrResponseBody((string) $response));
        $json = json_decode($trimmed, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            $status = $json['status'] ?? null;

            if ($status === 'failed') {
                return [
                    'type' => 'error',
                    'message' => $this->paytrJsonErrorMessage($json),
                ];
            }

            if (! empty($json['paytr_redirect_url']) && is_string($json['paytr_redirect_url'])) {
                return ['type' => 'redirect', 'url' => $json['paytr_redirect_url']];
            }

            if ($status === 'success') {
                return [
                    'type' => 'completed',
                    'message' => (string) ($json['msg'] ?? 'Ödeme başarılı.'),
                ];
            }

            if ($status === 'wait_callback') {
                return [
                    'type' => 'pending',
                    'message' => (string) ($json['msg'] ?? 'Ödeme işlemi devam ediyor. Onay sonrası premium aktif olacaktır.'),
                ];
            }

            return ['type' => 'error', 'message' => 'PayTR geçersiz yanıt döndürdü.'];
        }

        if ($this->isPaytrHtmlResponse($trimmed)) {
            return ['type' => 'html', 'html' => $trimmed];
        }

        $plainText = $this->extractPaytrErrorMessage($trimmed);
        if ($plainText !== '') {
            return ['type' => 'error', 'message' => $plainText];
        }

        Log::warning('PayTR unparseable response', [
            'snippet' => Str::limit($trimmed, 200),
            'starts_with' => bin2hex(substr($trimmed, 0, 4)),
        ]);

        return ['type' => 'error', 'message' => 'PayTR beklenmeyen yanıt formatı.'];
    }

    private function notifySlackPayment(
        SlackService $slackService,
        User $user,
        array $plan,
        string $translationKey,
        ?string $attachmentColor = null,
        ?string $failureReason = null
    ): void {
        $channel = (string) config('slack.notifications.payment');
        if ($channel === '') {
            return;
        }

        $amount = number_format((float) ($plan['total_numeric'] ?? 0), 0, ',', '.').' ₺';

        $params = [
            'full_name' => trim((string) ($user->full_name ?? '')) ?: '—',
            'phone' => $user->phone ?? '—',
            'email' => $user->email ?? '—',
            'package_name' => $plan['name'] ?? '—',
            'amount' => $amount,
            'months' => (string) ($plan['months'] ?? '—'),
            'paid_at' => now()->format('d.m.Y H:i:s'),
            'reason' => trim((string) $failureReason) !== '' ? trim((string) $failureReason) : '—',
        ];

        $message = __($translationKey, $params, 'tr');

        $slackService->sendMessage($message, $channel, $attachmentColor);
    }

    /**
     * @param  array<string, mixed>  $post
     */
    private function formatPaytrFailureReason(array $post): string
    {
        $msg = trim((string) ($post['failed_reason_msg'] ?? ''));
        $code = trim((string) ($post['failed_reason_code'] ?? ''));

        if ($msg !== '' && $code !== '') {
            return '['.$code.'] '.$msg;
        }

        if ($msg !== '') {
            return $msg;
        }

        if ($code !== '') {
            return 'Hata kodu: '.$code;
        }

        return 'Bilinmeyen hata';
    }
}
