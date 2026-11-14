<?php

namespace App\Http\Controllers;

use App\Events\JobProgressUpdated;
use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\User;
use App\Models\UserCar;
use App\Services\DriveFinishPushService;
use App\Services\JobStartService;
use App\Services\RouteCitiesService;
use App\Support\CatalogAssetUrl;
use App\Support\PublicStorageUrl;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use App\Support\ShipmentAttachmentStorage;
use App\Models\ShipmentAttachment;

class VehicleOwnerController extends Controller
{
    use ResponseTrait;
    
    public function index(Request $request)
    {
        $vehicles = UserCar::with('car.details', 'carDetail')
            ->where('user_id', $request->user()->id)
            ->get();
        
        $shipmentOrders = ShipmentOrder::with([
            'shipment' => function ($q) {
                $q->with(['creator', 'car.details', 'getCarDetail', 'postType', 'requests']);
            },
        ])
            ->where('buyer_user', $request->user()->id)
            ->get();

    
        return $this->success([
            'vehicles' => $vehicles,
            'shipmentOrders' => $shipmentOrders,
        ]);
    }

    public function getOrders(Request $request)
    {
        $raw = (string) $request->query('status', 'accepted');

        /*
         * Teklif kabulünde (ShipmentController::acceptRequest) shipment.status = accepted olur; İlan ancak o an oluşur.
         * "Devam eden" için accepted (+ nadir edge: active) kullanılır; active→accepted eşlemesi devam eden listesini boşaltırdı.
         * Tamamlanan: uygulamada done atanmıyor; CloseShipmentJob shipment’ı inactive yapıyor — ikisi de "Tamamlanan" sekmesinde.
         */
        $statuses = match ($raw) {
            'accepted', 'active' => ['accepted', 'active'],
            'done' => ['done', 'inactive'],
            'cancelled', 'canceled' => ['canceled'],
            default => ['accepted', 'active'],
        };

        $buyerId = (int) $request->user()->id;

        $shipmentOrders = ShipmentOrder::with([
            'shipment' => function ($q) use ($raw) {
                $q->with(['creator', 'car.details', 'getCarDetail', 'postType', 'requests']);
                if ($raw === 'done') {
                    $q->with([
                        'attachments' => fn ($attachmentQuery) => $attachmentQuery
                            ->select('id', 'shipment_id', 'title', 'created_at')
                            ->orderByDesc('created_at'),
                    ]);
                }
            },
        ])
            ->with('shipment.creator', 'shipment.car.details', 'shipment.getCarDetail', 'shipment.postType', 'shipment.requests')
            ->withWhereHas('shipment', function ($query) use ($statuses) {
                $query->whereIn('status', $statuses);
            })
            ->where('buyer_user', $buyerId)
            ->get();

        $statusCounts = [
            'accepted' => ShipmentOrder::where('buyer_user', $buyerId)
                ->whereHas('shipment', fn ($q) => $q->whereIn('status', ['accepted', 'active']))
                ->count(),
            'done' => ShipmentOrder::where('buyer_user', $buyerId)
                ->whereHas('shipment', fn ($q) => $q->whereIn('status', ['done', 'inactive']))
                ->count(),
            'cancelled' => ShipmentOrder::where('buyer_user', $buyerId)
                ->whereHas('shipment', fn ($q) => $q->whereIn('status', ['canceled']))
                ->count(),
        ];

        return $this->success([
            'shipmentOrders' => $shipmentOrders,
            'status_counts' => $statusCounts,
        ]);
    }

    /**
     * Araç sahibinin şu an taşıdığı yük(ler): panel bu varsa ilan listesi yerine "Aktif İş" ekranını gösterir.
     * Yalnızca "İşi Başlat" ile başlatılmış işler; `include_pending=1` ile henüz başlatılmamış onaylı işler de
     * gelir ("Onaylanan İşler"de başlatma penceresi ve bugünkü iş uyarısı için).
     */
    public function activeJobs(Request $request, RouteCitiesService $routeCities)
    {
        $user = $request->user();
        $isDriver = (string) $user->type === 'driver';
        if ((string) $user->type !== 'vehicle_owner' && ! $isDriver) {
            return $this->success(['jobs' => []]);
        }
        // Sürücüde ilan sahibi teklifleri araç sahibinindir
        $offerOwnerId = $isDriver ? (int) $user->parent_user_id : (int) $user->id;

        $orders = ShipmentOrder::with([
            'shipment' => fn ($q) => $q->with([
                'creator',
                'postType',
                'car',
                'getCarDetail',
                'requests' => fn ($r) => $r->where('status', 'accepted')->where('user_id', $offerOwnerId)->with('car'),
            ]),
            'driver',
        ])
            ->whereHas('shipment', fn ($q) => $q->where('status', 'accepted'))
            ->when(
                $isDriver,
                fn ($q) => $q->where('driver_user_id', $user->id),
                // Araç sahibi: aktif iş ekranında başkasına verdiği işler yok; "Onaylanan İşler"de görünür
                fn ($q) => $q->where('buyer_user', $user->id)->when(! $request->boolean('include_pending'), fn ($q2) => $q2->whereNull('driver_user_id'))
            )
            ->when(! $isDriver && ! $request->boolean('include_pending'), fn ($q) => $q->whereNotNull('started_at'))
            // İlk alınan iş ana iştir (rota onun); yol üstünden sonradan alınanlar ek yük olarak arkadan gelir
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        // Sürücü: işi kendisine veren araç sahibi (aktif iş ekranında bilgisi + arama butonu)
        $assigners = $isDriver
            ? User::whereIn('id', $orders->pluck('buyer_user')->filter()->unique()->values())->get()->keyBy('id')
            : collect();

        $jobs = $orders->map(function (ShipmentOrder $order) use ($request, $routeCities, $assigners) {
            $s = $order->shipment;
            $accepted = $s->requests->first();
            $owner = $s->creator;
            $car = $accepted?->car ?? $s->car;
            $carImage = $accepted?->car ? $car?->image : ($s->getCarDetail?->image ?? $car?->image);

            $fixedPrice = (float) $s->getRawOriginal('price');
            $offerPrice = (float) ($accepted?->getRawOriginal('price') ?? 0);
            if ($fixedPrice > 0) {
                [$dealType, $amount] = ['fixed', $fixedPrice];
            } elseif ($offerPrice > 0) {
                [$dealType, $amount] = ['offer', $offerPrice];
            } else {
                [$dealType, $amount] = ['negotiated', null];
            }

            return [
                'shipment_id' => $s->id,
                // Aktif iş ekranında "Seç" ile bu işe bağlanan araç (henüz seçilmediyse null)
                'selected_user_car_id' => $this->jobUserCarId($order, $accepted),
                // "İşi Başlat" ile başlatıldıysa zaman; null ise ekranda başlatma penceresi açılır
                'started_at' => $order->started_at?->toIso8601String(),
                // İleri tarih kontrolü için (YYYY-AA-GG)
                'departure_date_ymd' => $s->departure_date?->format('Y-m-d'),
                // Canlı ilerleme (konuma göre, 0–100); iş başlamadıysa / konum gelmediyse null
                'progress' => $order->progress !== null ? (float) $order->progress : null,
                'current_city' => $order->current_city,
                // İş başkasına verildiyse sürücüsü
                'driver' => $order->driver ? [
                    'id' => (int) $order->driver->id,
                    'name' => trim($order->driver->first_name.' '.$order->driver->last_name),
                    'phone' => (string) $order->driver->phone,
                ] : null,
                // Yalnızca sürücü hesabında: işi veren araç sahibi
                'assigned_by' => ($assigner = $assigners->get((int) $order->getRawOriginal('buyer_user'))) ? [
                    'id' => (int) $assigner->id,
                    'name' => trim($assigner->first_name.' '.$assigner->last_name) ?: 'Araç sahibi',
                    'phone' => (string) $assigner->phone,
                    'image' => PublicStorageUrl::absoluteForRequest($request, $assigner->profile_image),
                ] : null,
                'slug' => $s->slug,
                'from' => implode(' / ', array_filter([$s->f_where_city, $s->f_where_district])),
                'to' => implode(' / ', array_filter([$s->t_where_city, $s->t_where_district])),
                // İlan verilirken haritadan seçilen tam yükleme / teslim noktası (yalnızca işi alan sürücüye);
                // eski ilanlarda yok (null) → uygulama il/ilçe merkezini kullanır
                'from_location' => $this->exactPoint($s->f_where_location),
                'to_location' => $this->exactPoint($s->t_where_location),
                'stops' => $routeCities->citiesBetween($s->f_where_city, $s->t_where_city),
                'deal_type' => $dealType,
                'amount' => $amount,
                'post_type' => $s->postType?->value,
                'weight' => $s->weight,
                'departure_date' => $s->departure_date_text,
                'departure_time' => $s->departure_time ? substr((string) $s->departure_time, 0, 5) : null,
                'time_arrival' => $s->time_arrival ? substr((string) $s->time_arrival, 0, 5) : null,
                'explanation' => $s->explanation,
                'car' => $car ? [
                    'name' => $car->name,
                    'detail' => $accepted?->car ? null : $s->getCarDetail?->value,
                    'image' => CatalogAssetUrl::vehicle($request, $carImage),
                ] : null,
                'owner' => $owner ? [
                    'id' => $owner->id,
                    'name' => trim((string) $owner->full_name) ?: 'Yük sahibi',
                    'image' => PublicStorageUrl::absoluteForRequest($request, $owner->profile_image),
                    // Yalnızca ilan sahibi "Arama ile iletişime geçilebilir" dediyse
                    'phone' => $s->call_access ? (string) $owner->phone : null,
                ] : null,
            ];
        })->values()->all();

        // Ana işin (ilk alınan) rotası üzerindeki açık ilanlar; alınmış tüm işler hariç (sürücü ilan almaz)
        $alongListings = [];
        if ($orders->isNotEmpty() && ! $isDriver) {
            $mainOrder = $orders->first();
            $current = $mainOrder->shipment;
            $alongListings = $this->listingsAlongRoute(
                $user->id,
                $orders->pluck('shipment_id')->map(fn ($id) => (int) $id)->all(),
                array_merge([$current->f_where_city], $routeCities->citiesBetween($current->f_where_city, $current->t_where_city), [$current->t_where_city]),
                // Araç bu ili geçtiyse alma noktası gerideki ilanlar gösterilmez
                $mainOrder->started_at ? $mainOrder->current_city : null
            );
        }

        return $this->success(['jobs' => $jobs, 'along_listings' => $alongListings]);
    }

    /**
     * Rota üzerindeki açık ilanlar: alma noktası rotadaki bir ilde, bırakma noktası rotada aynı yerde ya da ileride
     * (araç sahibi yolu değiştirmeden ek yük alabilir). Kendi ilanları ve taşıdığı işler hariç.
     *
     * @param  list<int>  $excludeShipmentIds  Şu an taşınan (ana + ek) işler
     * @param  list<string|null>  $routeCities  Rota sırasıyla iller (başlangıç → ara iller → varış)
     */
    private function listingsAlongRoute(int $userId, array $excludeShipmentIds, array $routeCities, ?string $currentCity = null): array
    {
        $order = [];
        foreach ($routeCities as $i => $city) {
            $key = mb_strtolower(trim((string) $city), 'UTF-8');
            if ($key !== '' && ! isset($order[$key])) {
                $order[$key] = $i;
            }
        }
        if ($order === []) {
            return [];
        }

        $rank = fn (?string $city) => $order[mb_strtolower(trim((string) $city), 'UTF-8')] ?? null;

        // Aracın şu an bulunduğu ilden önceki illerde alınacak yükler artık geride kaldı: yalnızca bu il ve ilerisi
        $minRank = $currentCity !== null ? ($rank($currentCity) ?? 0) : 0;
        $pickupCities = array_values(array_filter(
            $routeCities,
            fn ($city, $i) => $city !== null && $city !== '' && $i >= $minRank,
            ARRAY_FILTER_USE_BOTH
        ));
        if ($pickupCities === []) {
            return [];
        }

        $candidates = Shipment::with([
            'creator' => fn ($q) => $q->withAvg('comments', 'score'),
            'car',
            'getCarDetail',
            'postType',
            'requests',
            'shipmentCars.car',
            'shipmentCars.carDetail',
        ])
            ->where('status', 'active')
            ->where('creater_id', '!=', $userId)
            ->whereNotIn('id', $excludeShipmentIds)
            ->whereIn('f_where_city', $pickupCities)
            ->orderByDesc('created_at')
            ->limit(60)
            ->get();

        // Önce varışı da rota üzerinde ileride olanlar (yol değişmez), sonra yalnızca alma noktası rotada olanlar;
        // her grupta yolda önce karşılaşılan önce
        return $candidates
            ->sortBy(function (Shipment $s) use ($rank) {
                $from = (int) $rank($s->f_where_city);
                $to = $rank($s->t_where_city);
                $onRoute = $to !== null && $to >= $from;

                return [$onRoute ? 0 : 1, $from];
            })
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * "İşi Başlat": araç sahibi işi onaylayıp yükü teslim almak için yola çıkar; yük sahibine bildirim gider.
     * Tekrar çağrılırsa (zaten başlatıldıysa) bildirim yeniden gönderilmez.
     */
    /**
     * Araç sahibinin anlık konumu: başlatılmış her aktif işin ilerlemesi rotaya göre hesaplanır, kaydedilir
     * ve `job.progress` ile araç sahibi + yük sahibine yayınlanır. İlerleme geri gitmez (GPS sapmaları).
     */
    public function updateLocation(Request $request, RouteCitiesService $routeCities)
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);
        if ($validator->fails()) {
            return $this->error([], 'Doğrulama hatası', $validator->errors()->toArray(), 422);
        }

        $lat = (float) $request->input('lat');
        $lng = (float) $request->input('lng');

        $me = $request->user();
        $orders = ShipmentOrder::with('shipment')
            ->when(
                (string) $me->type === 'driver',
                fn ($q) => $q->where('driver_user_id', $me->id),
                fn ($q) => $q->where('buyer_user', $me->id)
            )
            ->whereNotNull('started_at')
            ->whereHas('shipment', fn ($q) => $q->where('status', 'accepted'))
            ->get();

        $jobs = [];
        foreach ($orders as $order) {
            $s = $order->shipment;
            $calc = $routeCities->progressAlong($s->f_where_city, $s->t_where_city, $lat, $lng);
            if ($calc === null) {
                continue;
            }

            $progress = max((float) ($order->progress ?? 0), $calc['progress']);
            // Geri sapma (GPS) ilerlemeyi de şehri de geri almaz
            $city = $calc['progress'] < $progress - 0.1 && $order->current_city ? $order->current_city : $calc['city'];
            $order->forceFill([
                'last_lat' => $lat,
                'last_lng' => $lng,
                'progress' => $progress,
                'current_city' => $city,
                'progress_updated_at' => now(),
            ])->save();

            // Her konumda yayınlanır: ilerleme değişmese de araç sahibinin haritasında araç hareket eder
            try {
                broadcast(new JobProgressUpdated($order));
            } catch (\Throwable $e) {
                Log::warning('broadcast.job_progress.failed', ['shipment_id' => $s->id, 'error' => $e->getMessage()]);
            }

            $jobs[] = [
                'shipment_id' => (int) $s->id,
                'progress' => $progress,
                'current_city' => $city,
            ];
        }

        return $this->success(['jobs' => $jobs]);
    }

    /**
     * İşlerin Durumu: araç sahibinin onaylanan tüm işleri — aracın son konumu, teslim durumu
     * ve teslimde çekilen fotoğraf / belgeler. Sıra: yolda (başlamış) → onaylı ama başlamamış → teslim edilen
     * (teslim edilenler son 30 gün).
     */
    public function fleetJobs(Request $request)
    {
        $user = $request->user();
        if ((string) $user->type !== 'vehicle_owner') {
            return $this->success(['jobs' => []]);
        }

        $orders = ShipmentOrder::with(['shipment', 'driver'])
            ->where('buyer_user', $user->id)
            ->whereHas('shipment', fn ($q) => $q->whereIn('status', ['accepted', 'done']))
            ->where(fn ($q) => $q->whereNull('delivered_at')->orWhere('delivered_at', '>=', now()->subDays(30)))
            ->get()
            // Bitmiş iş başlatılmış olmalı (eski kayıtlarda started_at yoksa yine de teslim edilen sayılır)
            ->sortBy(fn (ShipmentOrder $o) => [
                (string) $o->shipment?->status === 'done' ? 2 : ($o->started_at ? 0 : 1),
                // Yolda: en son başlayan üstte; onaylı: en eski onay üstte; teslim: en son teslim üstte
                $o->started_at ? -$o->started_at->getTimestamp() : $o->created_at?->getTimestamp() ?? 0,
            ])
            ->values();

        $cars = UserCar::with('car')->whereIn('id', $orders->pluck('user_car_id')->filter()->unique())->get()->keyBy('id');
        $docs = ShipmentAttachment::whereIn('shipment_id', $orders->pluck('shipment_id'))
            ->orderBy('id')
            ->get()
            ->groupBy('shipment_id');

        $jobs = $orders->map(function (ShipmentOrder $order) use ($request, $user, $cars, $docs) {
            $s = $order->shipment;
            $delivered = (string) $s->status === 'done';
            $car = $order->user_car_id ? $cars->get($order->user_car_id) : null;
            // Teslimde araç sahibi ya da sürücünün yüklediği fotoğraf / belgeler
            $uploaders = array_filter([(int) $user->id, (int) $order->driver_user_id]);
            $documents = collect($docs->get($order->shipment_id, []))
                ->filter(fn (ShipmentAttachment $a) => in_array((int) $a->creator_id, $uploaders, true))
                ->map(fn (ShipmentAttachment $a) => [
                    'id' => (int) $a->id,
                    'title' => (string) $a->title,
                    'url' => PublicStorageUrl::absoluteForRequest($request, $a->image),
                ])
                ->values();

            return [
                'shipment_id' => (int) $s->id,
                'slug' => $s->slug,
                'from' => implode(' / ', array_filter([$s->f_where_city, $s->f_where_district])),
                'to' => implode(' / ', array_filter([$s->t_where_city, $s->t_where_district])),
                // in_progress: yolda (başlatıldı), pending: onaylandı ama başlatılmadı, delivered: teslim edildi
                'status' => $delivered ? 'delivered' : ($order->started_at ? 'in_progress' : 'pending'),
                'driver' => $order->driver ? [
                    'name' => trim($order->driver->first_name.' '.$order->driver->last_name),
                    'phone' => (string) $order->driver->phone,
                ] : null,
                'car' => $car ? ['plate' => (string) $car->plate, 'name' => $car->car?->name] : null,
                'location' => $order->last_lat !== null && $order->last_lng !== null ? [
                    'lat' => (float) $order->last_lat,
                    'lng' => (float) $order->last_lng,
                    'at' => $order->progress_updated_at?->toIso8601String(),
                ] : null,
                'progress' => $order->progress !== null ? (float) $order->progress : null,
                'current_city' => $order->current_city,
                'started_at' => $order->started_at?->toIso8601String(),
                'delivered_at' => $order->delivered_at?->toIso8601String(),
                'documents' => $documents,
            ];
        })->values();

        return $this->success(['jobs' => $jobs]);
    }

    /**
     * İşi başkasına ver: sürücü hesabı (users.type = driver) oluşturulur / güncellenir, iş seçilen araçla ona verilir.
     * İş sürücü "İşi Başlat" diyene kadar başlamaz. Sürücü telefon + şifre ile girer; yalnızca bu aktif işi görür ve "Teslim Et" ile bitirir.
     * Giriş bilgileri şimdilik log'a yazılır (SMS ile gönderim daha sonra).
     */
    public function delegateJob(Request $request, int $shipmentId)
    {
        $owner = $request->user();
        if ((string) $owner->type !== 'vehicle_owner') {
            return $this->error([], 'Bu işlem yalnızca araç sahipleri içindir.', [], 403);
        }
        if ((string) $owner->is_multi_cargo_owner !== 'yes') {
            return $this->error([], 'İşi başkasına vermek için Çoklu Araç Yönetim modunda olmalısınız.', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|min:3|max:120',
            'phone' => 'required|string|max:20',
            'user_car_id' => 'required|integer',
        ], [
            'full_name.required' => 'Ad soyad zorunludur.',
            'full_name.min' => 'Ad soyad en az 3 karakter olmalıdır.',
            'phone.required' => 'Telefon zorunludur.',
            'user_car_id.required' => 'Araç seçmelisiniz.',
        ]);
        if ($validator->fails()) {
            return $this->error([], $validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $phone = preg_replace('/\D+/', '', (string) $request->input('phone'));
        if (strlen($phone) === 11 && str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }
        if (strlen($phone) === 12 && str_starts_with($phone, '90')) {
            $phone = substr($phone, 2);
        }
        if (strlen($phone) !== 10) {
            return $this->error([], 'Geçerli bir telefon numarası girin.', ['phone' => ['Geçerli bir telefon numarası girin.']], 422);
        }

        $order = ShipmentOrder::with('shipment')
            ->where('shipment_id', $shipmentId)
            ->where('buyer_user', $owner->id)
            ->first();
        if (! $order || ! $order->shipment || $order->shipment->status !== 'accepted') {
            return $this->error([], 'Onaylı iş bulunamadı.', [], 404);
        }
        if ($order->started_at) {
            return $this->error([], 'Başlatılmış bir iş başkasına verilemez.', [], 422);
        }

        $car = UserCar::where('id', (int) $request->input('user_car_id'))->where('user_id', $owner->id)->first();
        if (! $car) {
            return $this->error([], 'Araç bulunamadı.', [], 404);
        }

        // Aynı telefonla başka bir hesap varsa (sürücü değilse ya da başka sahibin sürücüsüyse) kullanılamaz
        $existing = User::where('phone', $phone)->first();
        if ($existing && ! ((string) $existing->type === 'driver' && (int) $existing->parent_user_id === (int) $owner->id)) {
            return $this->error([], 'Bu telefon numarası başka bir hesapta kayıtlı.', ['phone' => ['Bu telefon numarası başka bir hesapta kayıtlı.']], 422);
        }

        $name = trim(preg_replace('/\s+/', ' ', (string) $request->input('full_name')));
        $parts = explode(' ', $name);
        $last = count($parts) > 1 ? array_pop($parts) : '';
        $first = implode(' ', $parts);
        $password = (string) random_int(10000000, 99999999);

        $driver = DB::transaction(function () use ($existing, $first, $last, $phone, $password, $owner, $order, $car) {
            $driver = $existing ?? new User;
            $driver->forceFill([
                'first_name' => $first,
                'last_name' => $last,
                'phone' => $phone,
                // E-posta zorunlu alan: sürücüye ait olmayan yer tutucu (giriş telefonla)
                'email' => $existing?->email ?: 'surucu.'.$phone.'@surucu.tasibul.invalid',
                'password' => $password,
                'type' => 'driver',
                'parent_user_id' => $owner->id,
                'status' => \App\Enums\UserStatus::ACTIVE->value,
                'phone_verify' => true,
                'email_verify' => false,
                'payment_confirm' => 1,
            ])->save();

            $order->update(['driver_user_id' => $driver->id, 'user_car_id' => $car->id]);

            return $driver;
        });

        // İş başlatılmaz: sürücü uygulamaya girince önce "İşi Başlat" der (yük sahibine "yola çıktı" o an gider)

        Log::info('driver.credentials', [
            'shipment_id' => $order->shipment_id,
            'owner_id' => $owner->id,
            'driver_id' => $driver->id,
            'phone' => $phone,
            'password' => $password,
        ]);

        return $this->success([
            'driver' => ['id' => $driver->id, 'name' => trim($first.' '.$last), 'phone' => $phone],
        ], 'İş '.trim($first.' '.$last).' adlı sürücüye verildi.');
    }

    public function startJob(Request $request, int $shipmentId)
    {
        $user = $request->user();

        $order = ShipmentOrder::with('shipment')
            ->where('shipment_id', $shipmentId)
            ->when(
                (string) $user->type === 'driver',
                fn ($q) => $q->where('driver_user_id', $user->id),
                // Sürücüye verilmiş işi sürücü başlatır
                fn ($q) => $q->where('buyer_user', $user->id)->whereNull('driver_user_id')
            )
            ->first();
        if (! $order || ! $order->shipment || $order->shipment->status !== 'accepted') {
            return $this->error([], 'Aktif iş bulunamadı.', [], 404);
        }

        if ($order->started_at) {
            return $this->success(['started_at' => $order->started_at->toIso8601String()], 'İş zaten başlatılmış.');
        }

        app(JobStartService::class)->start($order);

        return $this->success(['started_at' => $order->fresh()->started_at?->toIso8601String()], 'İş başlatıldı.');
    }

    /**
     * İşin aracı: aktif işte "Seç" ile seçilen → teklif verirken seçilen → (eski teklifler) teklifteki araç
     * türünden kullanıcının tek aracı varsa o. Hiçbiri yoksa null (ekranda "Seç" gösterilir).
     */
    private function jobUserCarId(ShipmentOrder $order, $accepted): ?int
    {
        if ($order->user_car_id) {
            return (int) $order->user_car_id;
        }
        if ($accepted?->user_car_id) {
            return (int) $accepted->user_car_id;
        }
        // Araç kaydı tutulmadan önce verilmiş gerçek teklif: teklifteki araç türündeki ilk aracı
        // Teklif vermeden alınan iş (arama ile verilen, fiyatsız kayıt): seçim araç sahibine bırakılır
        $isRealOffer = $accepted && (float) $accepted->getRawOriginal('price') > 0;
        if ($isRealOffer && $accepted->car_type) {
            $id = UserCar::where('user_id', $order->buyer_user)->where('car_id', $accepted->car_type)->orderBy('id')->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        return null;
    }

    /**
     * Aktif iş ekranında "Seç": bu iş hangi araçla taşınıyor.
     * Araç kullanıcıya ait olmalı; kapasitesi girilmişse yükü taşıyabilmeli.
     */
    public function selectVehicle(Request $request, int $shipmentId)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'user_car_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return $this->error([], 'Doğrulama hatası', $validator->errors()->toArray(), 422);
        }

        $order = ShipmentOrder::with('shipment')
            ->where('shipment_id', $shipmentId)
            ->where('buyer_user', $user->id)
            ->first();
        if (! $order || ! $order->shipment || $order->shipment->status !== 'accepted') {
            return $this->error([], 'Aktif iş bulunamadı.', [], 404);
        }

        $car = UserCar::where('user_id', $user->id)->where('id', (int) $request->user_car_id)->first();
        if (! $car) {
            return $this->error([], 'Araç bulunamadı.', [], 404);
        }

        $loadKg = $this->weightToKg($order->shipment->weight);
        if ($car->capacity_kg && $loadKg !== null && $loadKg > (int) $car->capacity_kg) {
            return $this->error([], 'Bu araç yükün ağırlığını taşıyamaz.', [], 422);
        }

        $order->update(['user_car_id' => $car->id]);

        return $this->success(['selected_user_car_id' => $car->id], 'Araç seçildi.');
    }

    /** "250 kg", "1.500 kg", "2,5 ton" → kg; okunamazsa null */
    private function weightToKg(?string $weight): ?float
    {
        $str = mb_strtolower(trim((string) $weight), 'UTF-8');
        if (! preg_match('/[\d.,]+/', $str, $m)) {
            return null;
        }
        $n = (float) str_replace(',', '.', str_replace('.', '', $m[0]));

        return str_contains($str, 'ton') ? $n * 1000 : $n;
    }

    public function finishDrive(Request $request, int $shipmentId)
    {
        $user = $request->user();
        if (! $user) {
            return $this->error([], 'Unauthorized', [], 401);
        }

        $order = ShipmentOrder::query()
            ->where('shipment_id', $shipmentId)
            ->when(
                (string) $user->type === 'driver',
                fn ($q) => $q->where('driver_user_id', $user->id),
                fn ($q) => $q->where('buyer_user', $user->id)
            )
            ->first();

        if (! $order) {
            return $this->error([], 'İlan bulunamadı.', [], 404);
        }

        $shipment = Shipment::query()->find($shipmentId);
        if (! $shipment) {
            return $this->error([], 'İlan bulunamadı.', [], 404);
        }

        // Teslimat fotoğrafları (mobil: en az 1 zorunlu, en fazla 10). Eski istemciler fotoğrafsız çağırabilir.
        $validator = Validator::make($request->all(), [
            'photos' => 'sometimes|array|max:10',
            'photos.*' => 'file|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'photos.max' => 'En fazla 10 fotoğraf yükleyebilirsiniz.',
            'photos.*.mimes' => 'Fotoğraflar JPG, PNG veya WEBP olmalıdır.',
            'photos.*.max' => 'Her fotoğraf en fazla 5MB olabilir.',
        ]);
        if ($validator->fails()) {
            return $this->error([], 'Doğrulama hatası', $validator->errors()->toArray(), 422);
        }

        // Fotoğraflar iş bitirilmeden önce kaydedilir; biri bile kaydedilemezse iş bitirilmez
        $savedPaths = [];
        foreach ((array) $request->file('photos', []) as $i => $photo) {
            try {
                $ext = strtolower($photo->getClientOriginalExtension() ?: $photo->guessExtension() ?: 'jpg');
                $savedPaths[] = ShipmentAttachmentStorage::put($shipment->id, Str::random(40).'.'.$ext, $photo->getContent());
            } catch (\Throwable $e) {
                report($e);

                return $this->error([], 'Teslimat fotoğrafı kaydedilemedi. Lütfen tekrar deneyin.', [], 500);
            }
        }

        $shipment->update(['status' => 'done']);
        $order->forceFill(['delivered_at' => now()])->save();

        foreach ($savedPaths as $i => $path) {
            ShipmentAttachment::create([
                'shipment_id' => $shipment->id,
                'creator_id' => $user->id,
                'title' => 'Teslimat fotoğrafı '.($i + 1),
                'description' => null,
                'image' => $path,
            ]);
        }

        $cargoOwner = User::query()->find((int) $order->creater_user);

        $systemMessage = Message::create([
            'sender_id' => (int) $order->buyer_user,
            'receiver_id' => (int) $order->creater_user,
            'shipment_id' => (int) $shipment->id,
            'message' => '',
            'sender_message' => 'Ilani basarili bir sekilde bitirdiniz.',
            'receiver_message' => 'Ilaniniz basarili bir sekilde indirme bolgesine ulasti.',
            'type' => 'system',
            'status' => 'accepted',
            'is_read' => false,
        ]);
        broadcast(new MessageSent($systemMessage));

        if ($cargoOwner) {
            try {
                app(DriveFinishPushService::class)->notifyDriveFinished($shipment, $user, $cargoOwner);
            } catch (\Throwable $e) {
                Log::error('push.drive_finished.notify_failed', [
                    'shipment_id' => $shipment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->success([
            'shipment_id' => $shipment->id,
            'status' => $shipment->status,
        ], 'Surus basariyla tamamlandi.');
    }

    /**
     * Araç sahibi (İlanteki buyer) devam eden sürüşü iptal eder; ilan durumu canceled olur.
     */
    public function cancelDrive(Request $request, int $shipmentId)
    {
        $user = $request->user();
        if (! $user) {
            return $this->error([], 'Unauthorized', [], 401);
        }

        $order = ShipmentOrder::query()
            ->where('shipment_id', $shipmentId)
            ->where('buyer_user', $user->id)
            ->first();

        if (! $order) {
            return $this->error([], 'İlan bulunamadı.', [], 404);
        }

        $shipment = Shipment::query()->find($shipmentId);
        if (! $shipment) {
            return $this->error([], 'İlan bulunamadı.', [], 404);
        }

        if (! in_array((string) $shipment->status, ['accepted', 'active'], true)) {
            return $this->error([], 'Bu ilan için sürüş iptali yapılamaz.', [], 422);
        }

        $reason = trim((string) $request->input('reason', ''));
        if (mb_strlen($reason) > 500) {
            $reason = mb_substr($reason, 0, 500);
        }

        $shipment->update(['status' => 'canceled']);

        // Yük sahibinin ilan iptalindeki gibi neden kayda geçer
        Log::info('Sürüş iptal edildi (araç sahibi)', [
            'shipment_id' => $shipment->id,
            'user_id' => $user->id,
            'reason' => $reason,
        ]);

        return $this->success([
            'shipment_id' => $shipment->id,
            'status' => $shipment->status,
        ], 'Sürüş iptal edildi.');
    }

    /** Kaydedilmiş tam konumdan yalnızca navigasyon için gerekenler: { lat, lng, label } */
    private function exactPoint(mixed $location): ?array
    {
        if (! is_array($location) || ! is_numeric($location['lat'] ?? null) || ! is_numeric($location['lng'] ?? null)) {
            return null;
        }

        return [
            'lat' => (float) $location['lat'],
            'lng' => (float) $location['lng'],
            'label' => $location['display_name'] ?? null,
        ];
    }

}
