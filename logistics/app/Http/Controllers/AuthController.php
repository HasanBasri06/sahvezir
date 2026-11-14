<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Http\Resources\UserResource;
use App\Jobs\EndTrialProcess;
use App\Mail\OtpVerificationMail;
use App\Models\User;
use App\Services\SMSService;
use App\Services\DeactivateUserAccountService;
use App\Services\GoogleIdTokenVerifier;
use App\Services\UserDeviceService;
use App\Models\UserDetail;
use App\Models\UserCar;
use App\Services\SlackService;
use App\Support\CityNameNormalizer;
use App\Support\Geo;
use App\Support\UserCarImageUploader;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ResponseTrait;

    public function __construct(
        private readonly SMSService $smsService,
        private readonly UserDeviceService $userDeviceService,
        private readonly DeactivateUserAccountService $deactivateUserAccountService,
    ) {}

    /** Doğrulama kodunu SMS (OTP API) ile gönderir; kayıtlı e-posta varsa aynı kodu e-posta ile de iletir. */
    private function sendOtpToUser(User $user, string $code, string $purpose = 'verification'): void
    {
        if ($user->phone) {
            try {
                $this->smsService->sendVerificationCode((string) $user->phone, $code, $purpose);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($purpose === 'verification') {
            $this->sendOtpEmail($user, $code);
        }
    }

    /** Arayüzde e-posta ile kod gönder seçeneği için (örn. j***@… ) */
    private function maskEmail(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) {
            return null;
        }
        [$local, $domain] = explode('@', $email, 2);
        $local = (string) $local;
        $len = strlen($local);
        if ($len === 0) {
            return null;
        }
        $visible = $len <= 2 ? substr($local, 0, 1) : substr($local, 0, 2);

        return $visible.str_repeat('*', min(4, max(1, $len - strlen($visible)))).'@'.$domain;
    }

    /** Arayüzde maskelenmiş telefon (örn. 555 *** ** 67) */
    private function maskPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) < 10) {
            return null;
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) !== 10) {
            return null;
        }

        return substr($digits, 0, 3).' *** ** '.substr($digits, -2);
    }

    /** Doğrulama kodunu kayıtlı e-posta adresine gönderir. */
    private function sendOtpEmail(User $user, string $code): void
    {
        if (! $user->email) {
            return;
        }

        try {
            Mail::to($user->email)->send(new OtpVerificationMail($code));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'password' => 'required|string|min:6',
            'expo_push_token' => 'nullable|string|max:255',
            'platform' => 'nullable|string|in:ios,android',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors());
        }

        $user = $this->findUserByLoginPhone((string) $request->phone);

        if (! $user) {
            return $this->error([], 'Bu bilgilere sahip bir kullanıcı bulunamadı.', [], 401);
        }

        if (! Hash::check($request->password, $user->password)) {
            return $this->error([], 'Telefon numarası veya şifre hatalı', [], 401);
        }

        return $this->completeLogin($request, $user);
    }

    /**
     * Kimliği doğrulanmış kullanıcı için ortak giriş adımı (telefon + şifre / Google):
     * pasif hesap → hata, doğrulanmamış hesap → OTP, aktif hesap → token.
     */
    private function completeLogin(Request $request, User $user)
    {
        $user->refresh();

        if ($user->isInactiveAccount()) {
            $user->terminateAllSessions();

            return $this->loginInactiveAccountError();
        }

        if ($user->isPendingAccount()) {
            $code = ($user->code !== null && $user->code !== '')
                ? (string) $user->code
                : (string) random_int(100000, 999999);

            if ($user->code === null || $user->code === '') {
                $user->update(['code' => $code]);
            }

            $this->sendOtpToUser($user, $code);

            return $this->error([
                'need_otp' => true,
                'phone' => $user->phone,
                // Frontend alan adı "email" bekliyor; OTP'nin adresi telefondur.
                'email' => $user->phone,
                'masked_email' => $this->maskEmail($user->email),
                'masked_phone' => $this->maskPhone($user->phone),
            ], $user->email
                ? 'Telefon doğrulaması gerekli. Doğrulama kodu SMS ile gönderildi; kayıtlı e-postanıza da iletildi.'
                : 'Telefon doğrulaması gerekli. Doğrulama kodu SMS ile gönderildi.', [], 403);
        }

        if (! $user->isActiveAccount()) {
            return $this->error([], 'Hesabınız aktif değil. Giriş yapamazsınız.', [], 401);
        }

        // Mobil / client `users.type` ile aynı değer (roller tablosu ile uyumsuzluk olmasın diye öncelik users.type)
        $role = $user->roles()->first();
        $roleName = $role ? $role->name->value : null;
        $typeOut = $user->type ? (string) $user->type : $roleName;

        $token = $user->createToken('api:user:'.($typeOut ?? 'user'))->plainTextToken;

        $this->registerPushDeviceFromRequest($request, $user);

        return $this->success([
            'token' => $token,
            'type' => $typeOut,
            'user_id' => $user->id,
        ]);
    }

    public function register(Request $request, SlackService $slackService)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string|max:20|unique:users,phone',
            'password' => 'required|string|min:6',
            'password_confirm' => 'required|same:password',
            'type' => 'required|string|in:cargo_owner,vehicle_owner',
        ], [
            'email.unique' => 'Bu e-posta adresi zaten kullanılıyor.',
            'phone.unique' => 'Bu telefon numarası zaten kullanılıyor.',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors(), 422);
        }

        $existingUser = User::where('phone', $request->phone)->first();
        if ($existingUser) {
            return $this->error([], 'Bu kullanıcı zaten kayıtlı', [], 409);
        }

        $existingEmail = User::where('email', $request->email)->first();
        if ($existingEmail) {
            return $this->error([], 'Bu e-posta adresi zaten kullanılıyor.', [], 409);
        }

        $code = (string) random_int(100000, 999999);

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'type' => $request->type,
            'status' => UserStatus::PENDING,
            'code' => $code,
            'email_verify' => false,
            'phone_verify' => false,
        ]);

        $this->sendOtpToUser($user, $code);

        $this->notifySlackNewUser($slackService, $user, 'slack.new_user');

        return $this->success([
            'need_otp' => true,
            'phone' => $user->phone,
            'email' => $user->phone,
            'masked_email' => $this->maskEmail($user->email),
        ], $user->email
            ? 'Doğrulama kodu SMS ile gönderildi; e-posta adresinize de iletildi.'
            : 'Doğrulama kodu SMS ile gönderildi.', 200);
    }

    public function verifyOtp(Request $request, SlackService $slackService)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'nullable|string',
            // Frontend "email" olarak phone değerini gönderiyor.
            'email' => 'nullable|string',
            'code' => 'required|string|size:6',
            'expo_push_token' => 'nullable|string|max:255',
            'platform' => 'nullable|string|in:ios,android',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors(), 422);
        }

        if (! $request->filled('phone') && ! $request->filled('email')) {
            return $this->error([], 'Telefon/email zorunludur.', ['email' => ['Telefon/email gerekli.']], 422);
        }

        $lookup = $request->filled('phone') ? $request->phone : $request->email;
        $isEmailFormat = $request->filled('email') && str_contains((string) $request->email, '@');

        $user = $request->filled('phone')
            ? User::where('phone', $lookup)->first()
            : ($isEmailFormat ? User::where('email', $lookup)->first() : User::where('phone', $lookup)->first());

        if (! $user) {
            return $this->error([], 'Kullanıcı bulunamadı.', [], 404);
        }

        if ($user->isInactiveAccount()) {
            return $this->loginInactiveAccountError();
        }

        if ((string) $user->code !== (string) $request->code) {
            return $this->error([], 'Doğrulama kodu hatalı.', [], 422);
        }

        $wasPending = (string) $user->status === UserStatus::PENDING->value;

        $user->update([
            'code' => null,
            'status' => UserStatus::ACTIVE,
            'phone_verify' => true,
            // Eski akışlarda `email_verify` okunuyorsa sorun yaşamamak için de güncelliyoruz.
            'email_verify' => false,
        ]);

        // İlk doğrulamada 30 günlük deneme aboneliği
        if (! \App\Models\UserSubscription::where('user_id', $user->id)->exists()) {
            app(\App\Services\SubscriptionService::class)->grant($user, ['source' => 'trial', 'package_name' => 'Deneme süresi'], days: 30);
        }

        $token = $user->createToken('api:user:'.$user->type)->plainTextToken;

        $this->registerPushDeviceFromRequest($request, $user);

        if ($wasPending) {
            $this->notifySlackNewUser($slackService, $user->fresh(), 'slack.user_verified', SlackService::COLOR_SUCCESS);
        }

        return $this->success([
            'token' => $token,
            'type' => $user->type,
            'user_id' => $user->id,
        ], 'Kayıt tamamlandı.', 200);
    }

    public function resendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'nullable|string',
            // Frontend "email" olarak phone değerini gönderiyor.
            'email' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors(), 422);
        }

        if (! $request->filled('phone') && ! $request->filled('email')) {
            return $this->error([], 'Telefon/email zorunludur.', ['email' => ['Telefon/email gerekli.']], 422);
        }

        $lookup = $request->filled('phone') ? $request->phone : $request->email;
        $isEmailFormat = $request->filled('email') && str_contains((string) $request->email, '@');

        $user = $request->filled('phone')
            ? User::where('phone', $lookup)->first()
            : ($isEmailFormat ? User::where('email', $lookup)->first() : User::where('phone', $lookup)->first());

        if (! $user) {
            return $this->error([], 'Kullanıcı bulunamadı.', [], 404);
        }

        if ((bool) $user->phone_verify || (bool) $user->email_verify) {
            return $this->error([], 'Bu telefon numarası zaten doğrulanmış.', [], 400);
        }

        $code = (string) random_int(100000, 999999);
        $user->update(['code' => $code]);
        $user->refresh();

        $this->sendOtpToUser($user, $code);

        return $this->success([
            'message' => $user->email
                ? 'Yeni doğrulama kodu SMS ile gönderildi; e-posta adresinize de iletildi.'
                : 'Yeni doğrulama kodu SMS ile gönderildi.',
        ], 'Kod gönderildi.', 200);
    }

    /**
     * OTP bekleyen hesap: giriş/kayıt akışında yanlış telefon düzeltmek için (token yok; mevcut numara ile eşleşen kayıt).
     */
    public function changePhonePending(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_phone' => 'required|string|max:20',
            'new_phone' => 'required|string|max:20',
        ], [
            'current_phone.required' => 'Mevcut telefon numarası zorunludur.',
            'new_phone.required' => 'Yeni telefon numarası zorunludur.',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Doğrulama hatası', $validator->errors()->toArray(), 422);
        }

        $digits = preg_replace('/\D+/', '', (string) $request->current_phone);
        $lookupPhones = array_unique(array_values(array_filter([
            (string) $request->current_phone,
            $digits,
            (strlen($digits) === 11 && str_starts_with($digits, '0')) ? substr($digits, 1) : null,
            (strlen($digits) === 10 && str_starts_with($digits, '5')) ? '0'.$digits : null,
        ])));

        $user = User::whereIn('phone', $lookupPhones)->first();

        if (! $user) {
            return $this->error([], 'Bu telefon numarasına ait bekleyen doğrulama bulunamadı.', [], 404);
        }

        if ($user->phone_verify) {
            return $this->error([], 'Bu hesap için telefon zaten doğrulanmış.', [], 400);
        }

        $digitsNew = preg_replace('/\D+/', '', (string) $request->new_phone);
        $digitsCurrent = preg_replace('/\D+/', '', (string) $request->current_phone);
        $norm = function (string $d): string {
            if ($d === '') {
                return '';
            }
            if (strlen($d) === 11 && str_starts_with($d, '0')) {
                return $d;
            }
            if (strlen($d) === 10 && str_starts_with($d, '5')) {
                return '0'.$d;
            }

            return $d;
        };
        if ($norm($digitsNew) !== '' && $norm($digitsNew) === $norm($digitsCurrent)) {
            return $this->error([], 'Yeni numara mevcut numaradan farklı olmalıdır.', [
                'new_phone' => ['Aynı telefon numarası girildi.'],
            ], 422);
        }

        $v2 = Validator::make($request->only('new_phone'), [
            'new_phone' => [
                'required',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
        ], [
            'new_phone.unique' => 'Bu telefon numarası başka bir hesapta kayıtlı.',
        ]);

        if ($v2->fails()) {
            return $this->error([], 'Doğrulama hatası', $v2->errors()->toArray(), 422);
        }

        $code = (string) random_int(100000, 999999);
        $user->update([
            'phone' => $request->new_phone,
            'code' => $code,
            'phone_verify' => false,
        ]);

        $user->refresh();

        $this->sendOtpToUser($user, $code);

        $msg = $user->email
            ? 'Telefon numaranız güncellendi. Doğrulama kodu SMS ile gönderildi; e-posta adresinize de iletildi.'
            : 'Telefon numaranız güncellendi. Doğrulama kodu SMS ile gönderildi.';

        return $this->success([
            'phone' => $user->phone,
            'email' => $user->phone,
        ], $msg);
    }

    public function check(Request $request)
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->error([], 'Token geçersiz veya süresi dolmuş', [], 401);
        }

        return $this->success([
            'user' => new UserResource($user->loadMissing(['userDetail', 'pushDevice'])),
        ], 'Token geçerli');
    }

    /**
     * Panel ilk giriş turunu bu hesap için tamamlandı olarak işaretler (hesap bazlı).
     */
    public function markPanelTourComplete(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return $this->error([], 'Yetkisiz', [], 401);
        }
        $user->update(['panel_tour_completed_at' => now()]);
        return $this->success([
            'user' => new UserResource($user->fresh()->loadMissing('pushDevice')),
        ], 'Tur tamamlandı olarak işaretlendi.');
    }

    public function updateUserLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_city' => 'nullable|string|max:255',
            'user_district' => 'nullable|string|max:255',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors(), 422);
        }

        $user = $request->user();
        if (! $user) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $rawCity = $request->input('user_city');
        $canonicalCity = $rawCity ? CityNameNormalizer::canonicalForFilter($rawCity) : null;

        $payload = [
            'user_city' => $canonicalCity,
            'user_district' => $request->input('user_district'),
        ];
        if ($request->filled('lat') && $request->filled('lng')) {
            $payload['exact_location'] = Geo::encodeExactLocation(
                (float) $request->input('lat'),
                (float) $request->input('lng')
            );
        }

        UserDetail::query()->where('user_id', $user->id)->delete();

        $detail = UserDetail::create(array_merge(
            ['user_id' => $user->id],
            $payload
        ));

        return $this->success([
            'user_city' => $detail->user_city,
            'user_district' => $detail->user_district,
            'exact_location' => $detail->exact_location,
        ], 'Konum bilgisi güncellendi.', 200);
    }

    public function updateSuitablePostings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'suitable_postings' => 'required|in:yes,no',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors(), 422);
        }

        $user = $request->user();
        if (! $user) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        if ($user->type !== 'vehicle_owner') {
            return $this->error([], 'Bu özellik yalnızca araç sahipleri içindir.', [], 403);
        }

        $user->update([
            'suitable_postings' => $request->input('suitable_postings'),
        ]);

        return $this->success([
            'user' => new UserResource($user->fresh()->loadMissing(['userDetail', 'pushDevice'])),
        ], 'İlan tercihi güncellendi.', 200);
    }

    public function clearUserLocation(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        UserDetail::where('user_id', $user->id)->delete();

        return $this->success([], 'Konum bilgisi silindi.', 200);
    }


    /**
     * İlan yükleme şehri (user_city) ile eşleşen araç sahiplerini döner.
     * ref_lat / ref_lng (yükleme noktası) verildiğinde exact_location ile mesafe hesaplanır;
     * yalnızca 100 km içindeki en yakın 20 kullanıcı listelenir.
     */
    public function getCloseUsers(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $city = $request->query('user_city');
        $district = $request->query('user_district');
        $refLat = $request->query('ref_lat');
        $refLng = $request->query('ref_lng');

        if (! is_numeric($refLat) || ! is_numeric($refLng)) {
            $geocoded = Geo::geocodeCityDistrict($city, $district);
            if ($geocoded) {
                $refLat = $geocoded['lat'];
                $refLng = $geocoded['lng'];
            }
        }

        $hasRef = is_numeric($refLat) && is_numeric($refLng);

        if (! $hasRef || ! $city || trim((string) $city) === '') {
            return $this->success(['users' => []], 'OK', 200);
        }

        $refLat = (float) $refLat;
        $refLng = (float) $refLng;

        $maxDistanceKm = 100.0;
        $canonicalCity = CityNameNormalizer::canonicalForFilter($city);
        $cityTrimmed = mb_strtolower(trim($canonicalCity ?? $city));

        $query = UserDetail::query()
            ->with('user:id,first_name,last_name,email,type')
            ->whereHas('user', fn ($q) => $q->where('type', 'vehicle_owner'))
            ->where('user_id', '!=', $user->id)
            ->whereNotNull('user_city')
            ->where('user_city', '!=', '')
            ->whereNotNull('exact_location')
            ->whereRaw('LOWER(TRIM(user_city)) = ?', [$cityTrimmed]);

        $users = $query->get()
            ->map(function (UserDetail $detail) use ($refLat, $refLng) {
                $u = $detail->user;
                if (! $u) {
                    return null;
                }

                $exact = Geo::parseExactLocation($detail->exact_location);
                if (! $exact) {
                    return null;
                }

                $distanceKm = round(
                    Geo::haversineKm((float) $refLat, (float) $refLng, $exact['lat'], $exact['lng']),
                    1
                );

                return [
                    'id' => $u->id,
                    'first_name' => $u->first_name,
                    'last_name' => $u->last_name,
                    'full_name' => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')),
                    'user_city' => $detail->user_city,
                    'user_district' => $detail->user_district,
                    'distance_km' => $distanceKm,
                ];
            })
            ->filter()
            ->filter(fn (array $row) => $row['distance_km'] <= $maxDistanceKm)
            ->sortBy('distance_km')
            ->values()
            ->take(20)
            ->all();

        return $this->success(['users' => $users], 'OK', 200);
    }

    /** Çoklu Araç Yönetim modunda araç başı ücret (TL) */
    public const MULTI_VEHICLE_PRICE = 299;

    /**
     * Paketin kapsadığı araç sayısına kadar serbest (Bireysel 1, İşletme paketlerinde paket sınırı);
     * sınırı aşan araç için Çok Araç Kontrol Modu (araç başı aylık ücret) gerekir.
     */
    public static function canAddAnotherCar(User $user): bool
    {
        if ((string) $user->is_multi_cargo_owner === 'yes') {
            return true;
        }

        $active = UserCar::where('user_id', $user->id)->where('status', 'active')->count();

        return $active < app(\App\Services\SubscriptionService::class)->includedVehicles($user);
    }

    /**
     * Çoklu Araç Yönetim moduna geçiş: araç sahibi birden fazla araç ekleyebilir (araç başı ücretli).
     */
    public function enableMultiVehicleMode(Request $request)
    {
        $user = $request->user();
        if ((string) $user->type !== 'vehicle_owner') {
            return $this->error([], 'Bu mod yalnızca araç sahipleri içindir.', [], 403);
        }

        if ((string) $user->is_multi_cargo_owner !== 'yes') {
            $user->forceFill(['is_multi_cargo_owner' => 'yes'])->save();
        }

        return $this->success([
            'is_multi_cargo_owner' => 'yes',
            'price_per_vehicle' => self::MULTI_VEHICLE_PRICE,
        ], 'Çoklu Araç Yönetim modu açıldı.');
    }

    /**
     * Sanctum ile giriş yapan kullanıcı için UserCar kaydı oluşturur.
     */
    public function createCarByUser(Request $request)
    {
        $data = UserCarImageUploader::resolvePayloadInput($request);

        $validator = Validator::make($data, [
            'car_id' => 'required|integer|exists:cars,id',
            'car_detail_id' => 'nullable|integer|exists:car_detail,id',
            'plaka' => 'required|string|max:20',
            // Aracın yüke uygunluğu ve parça yük eşleştirmesi için
            'capacity_kg' => 'required|integer|min:1|max:60000',
            'volume_m3' => 'required|numeric|min:0.1|max:200',
        ], [
            'plaka.required' => 'Plaka alanı zorunludur.',
            'capacity_kg.required' => 'Aracın taşıyabileceği yük (kg) zorunludur.',
            'capacity_kg.integer' => 'Taşıma kapasitesi tam sayı (kg) olmalıdır.',
            'capacity_kg.min' => 'Taşıma kapasitesi en az 1 kg olmalıdır.',
            'capacity_kg.max' => 'Taşıma kapasitesi en fazla 60.000 kg olabilir.',
            'volume_m3.required' => 'Aracın yük hacmi (m³) zorunludur.',
            'volume_m3.numeric' => 'Yük hacmi sayı olmalıdır.',
            'volume_m3.min' => 'Yük hacmi en az 0,1 m³ olmalıdır.',
            'volume_m3.max' => 'Yük hacmi en fazla 200 m³ olabilir.',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors());
        }

        $user = $request->user();
        if (! $user) {
            return $this->error([], 'Oturum bulunamadı.', [], 401);
        }

        // Tek araçlı üyelik: ikinci araç için Çoklu Araç Yönetim moduna geçilmeli
        if (! self::canAddAnotherCar($user)) {
            return $this->error(
                ['code' => 'multi_vehicle_required', 'price_per_vehicle' => self::MULTI_VEHICLE_PRICE],
                'Birden fazla araç eklemek için Çoklu Araç Yönetim moduna geçmelisiniz.',
                [],
                403
            );
        }

        $imageOnePath = UserCarImageUploader::storeOptionalFromRequest($request, 'image_one');
        $imageTwoPath = UserCarImageUploader::storeOptionalFromRequest($request, 'image_two');

        $car = UserCar::create([
            'user_id' => $user->id,
            'car_id' => (int) $data['car_id'],
            'car_detail_id' => ! empty($data['car_detail_id']) ? (int) $data['car_detail_id'] : null,
            'plate' => trim((string) $data['plaka']),
            'capacity_kg' => (int) $data['capacity_kg'],
            'volume_m3' => round((float) $data['volume_m3'], 2),
            'image_one' => $imageOnePath,
            'image_two' => $imageTwoPath,
            'status' => 'active',
        ]);

        // Çok Araç Kontrol Modu: ilk araç pakete dahil, sonraki her araç aylık ücretli (otomatik ödeme)
        $subscriptions = app(\App\Services\SubscriptionService::class);
        $paymentRequired = $subscriptions->isBillableCar($user, (int) $car->id);

        return $this->success([
            'user_car_id' => (int) $car->id,
            'payment_required' => $paymentRequired,
            'amount' => $paymentRequired ? \App\Services\SubscriptionService::withVat(\App\Services\SubscriptionService::VEHICLE_PRICE) : null,
        ], 'Araç eklendi.', 201);
    }

    /**
     * Mobil Google ile giriş. Hesap varsa normal girişle aynı sonuç döner;
     * yoksa `need_registration` ile telefon + hesap tipi istenir (`googleRegister`).
     */
    public function googleLogin(Request $request, GoogleIdTokenVerifier $verifier)
    {
        $validator = Validator::make($request->all(), [
            'id_token' => 'required|string|max:4096',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors(), 422);
        }

        $google = $verifier->verify((string) $request->id_token);
        if (! $google) {
            return $this->error([], 'Google hesabınız doğrulanamadı. Lütfen tekrar deneyin.', [], 401);
        }

        $user = User::where('google_id', $google['sub'])->first();

        if (! $user) {
            $user = User::where('email', $google['email'])->first();

            if ($user && $user->google_id && $user->google_id !== $google['sub']) {
                return $this->error([], 'Bu e-posta adresi başka bir Google hesabına bağlı.', [], 409);
            }

            // Aynı e-postayla telefonla açılmış hesap: Google hesabını bağla (e-posta Google tarafından doğrulanmış)
            if ($user) {
                $user->update(['google_id' => $google['sub']]);
            }
        }

        if (! $user) {
            return $this->success([
                'need_registration' => true,
                'first_name' => $google['first_name'],
                'last_name' => $google['last_name'],
                'email' => $google['email'],
            ], 'Kaydı tamamlamak için telefon numaranızı girin.');
        }

        return $this->completeLogin($request, $user);
    }

    /**
     * Google hesabıyla yeni kayıt: ad / e-posta Google'dan, telefon + hesap tipi kullanıcıdan.
     * Telefon normal kayıttaki gibi SMS ile doğrulanır (`need_otp`).
     */
    public function googleRegister(Request $request, GoogleIdTokenVerifier $verifier, SlackService $slackService)
    {
        $validator = Validator::make($request->all(), [
            'id_token' => 'required|string|max:4096',
            'phone' => 'required|string|max:20',
            'type' => 'required|string|in:cargo_owner,vehicle_owner',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors(), 422);
        }

        $phone = $this->normalizePhoneInput($request->phone);
        if (strlen($phone) !== 10) {
            return $this->error([], 'Telefon numarası 10 haneli olmalıdır.', ['phone' => ['Telefon numarası 10 haneli olmalıdır.']], 422);
        }

        $google = $verifier->verify((string) $request->id_token);
        if (! $google) {
            return $this->error([], 'Google hesabınız doğrulanamadı. Lütfen tekrar deneyin.', [], 401);
        }

        if (User::where('google_id', $google['sub'])->orWhere('email', $google['email'])->exists()) {
            return $this->error([], 'Bu Google hesabıyla zaten bir hesap var. Lütfen giriş yapın.', [], 409);
        }

        if ($this->findUserByLoginPhone($phone)) {
            return $this->error([], 'Bu telefon numarası zaten kullanılıyor.', ['phone' => ['Bu telefon numarası zaten kullanılıyor.']], 409);
        }

        $code = (string) random_int(100000, 999999);

        $user = User::create([
            'first_name' => $google['first_name'] !== '' ? $google['first_name'] : 'Kullanıcı',
            'last_name' => $google['last_name'],
            'email' => $google['email'],
            'google_id' => $google['sub'],
            'phone' => $phone,
            // Google ile giren kullanıcı şifre kullanmaz; isterse "Şifremi unuttum" ile belirler
            'password' => Hash::make(Str::random(40)),
            'type' => $request->type,
            'status' => UserStatus::PENDING,
            'code' => $code,
            'email_verify' => false,
            'phone_verify' => false,
        ]);

        $this->sendOtpToUser($user, $code);

        $this->notifySlackNewUser($slackService, $user, 'slack.new_user');

        return $this->success([
            'need_otp' => true,
            'phone' => $user->phone,
            'email' => $user->phone,
            'masked_email' => $this->maskEmail($user->email),
        ], 'Doğrulama kodu SMS ile gönderildi.', 200);
    }

    private function normalizePhoneInput(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    private function findUserByLoginPhone(string $rawPhone): ?User
    {
        $normalized = $this->normalizePhoneInput($rawPhone);
        if (strlen($normalized) < 10) {
            return null;
        }

        $candidates = array_values(array_unique(array_filter([
            trim($rawPhone),
            $normalized,
            '0'.$normalized,
        ])));

        return User::query()->whereIn('phone', $candidates)->first();
    }

    private function loginInactiveAccountError()
    {
        return $this->error([
            'account_inactive' => true,
            'support_email' => 'destek@tasibul.com',
        ], 'Hesabınız kaldırılmış veya silinmiş olabilir. Lütfen destek ekibi ile iletişime geçiniz: destek@tasibul.com', [], 403);
    }

    private function findUserForForgotPassword(Request $request): ?User
    {
        if ($request->filled('phone')) {
            $phone = $this->normalizePhoneInput($request->phone);
            if (strlen($phone) < 10) {
                return null;
            }

            return User::where('phone', $phone)->first();
        }

        if ($request->filled('email')) {
            $email = strtolower(trim((string) $request->email));
            if (str_contains($email, '@')) {
                return User::whereRaw('LOWER(email) = ?', [$email])->first();
            }

            $phone = $this->normalizePhoneInput($email);
            if ($phone !== '') {
                return User::where('phone', $phone)->first();
            }
        }

        return null;
    }

    /**
     * Oturum açmadan şifre sıfırlama: telefon veya e-posta ile doğrulama kodu gönderir (user.code).
     */
    public function sendForgotPasswordCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required_without:email|nullable|string',
            'email' => 'required_without:phone|nullable|string',
        ], [
            'phone.required_without' => 'Telefon numarası zorunludur.',
            'email.required_without' => 'Telefon numarası veya e-posta zorunludur.',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Doğrulama hatası', $validator->errors()->toArray(), 422);
        }

        if ($request->filled('email') && str_contains(strtolower(trim((string) $request->email)), '@')) {
            $emailValidator = Validator::make($request->only('email'), [
                'email' => 'email',
            ], [
                'email.email' => 'Geçerli bir e-posta giriniz.',
            ]);
            if ($emailValidator->fails()) {
                return $this->error([], 'Doğrulama hatası', $emailValidator->errors()->toArray(), 422);
            }
        }

        $user = $this->findUserForForgotPassword($request);

        if (! $user || ! $user->phone) {
            Log::warning('Şifre sıfırlama: kullanıcı bulunamadı, SMS gönderilmedi', [
                'phone' => $request->input('phone'),
                'email' => $request->input('email'),
                'user_id' => $user?->id,
            ]);
        }

        if ($user && $user->phone) {
            $code = (string) random_int(100000, 999999);
            $user->update(['code' => $code]);
            Log::info("Şifre sıfırlama SMS'i gönderiliyor. Numara: {$user->phone} | Kod: {$code}", [
                'user_id' => $user->id,
                'phone' => $user->phone,
                'code' => $code,
            ]);
            try {
                $this->smsService->sendVerificationCode((string) $user->phone, $code, 'otp');
            } catch (\Throwable $e) {
                report($e);

                return $this->error([], 'Doğrulama kodu gönderilemedi. Lütfen daha sonra tekrar deneyin.', [], 503);
            }
        }


        return $this->success([
            'phone_masked' => $user ? $this->maskPhone($user->phone) : null,
            'email_masked' => $user ? $this->maskEmail($user->email) : null,
        ], 'Kayıtlı telefon numaranıza doğrulama kodu gönderildi.');
    }

    /**
     * Şifre sıfırlama kodunu doğrular (user.code temizlenmez; sıfırlama adımında temizlenir).
     */
    public function verifyForgotPasswordCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required_without:email|nullable|string',
            'email' => 'required_without:phone|nullable|string',
            'code' => 'required|string|size:6',
        ], [
            'phone.required_without' => 'Telefon numarası zorunludur.',
            'email.required_without' => 'Telefon numarası veya e-posta zorunludur.',
            'code.required' => 'Doğrulama kodu zorunludur.',
            'code.size' => 'Doğrulama kodu 6 haneli olmalıdır.',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Doğrulama hatası', $validator->errors()->toArray(), 422);
        }

        $inputCode = preg_replace('/\D+/', '', (string) $request->code);
        $user = $this->findUserForForgotPassword($request);

        if (! $user || $user->code === null || $user->code === '') {
            return $this->error([], 'Doğrulama kodu hatalı veya süresi dolmuş.', ['code' => ['Doğrulama kodu hatalı veya süresi dolmuş.']], 422);
        }

        if (strlen($inputCode) !== 6 || (string) $user->code !== $inputCode) {
            return $this->error([], 'Doğrulama kodu hatalı veya süresi dolmuş.', ['code' => ['Doğrulama kodu hatalı veya süresi dolmuş.']], 422);
        }

        return $this->success([], 'Doğrulama kodu onaylandı.');
    }

    /**
     * SMS doğrulama kodu ile şifreyi sıfırlar; user.code null yapılır.
     */
    public function resetForgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required_without:email|nullable|string',
            'email' => 'required_without:phone|nullable|string',
            'code' => 'required|string|size:6',
            'new_password' => 'required|string|min:6|confirmed',
        ], [
            'phone.required_without' => 'Telefon numarası zorunludur.',
            'email.required_without' => 'Telefon numarası veya e-posta zorunludur.',
            'code.required' => 'Doğrulama kodu zorunludur.',
            'code.size' => 'Doğrulama kodu 6 haneli olmalıdır.',
            'new_password.required' => 'Yeni şifre zorunludur.',
            'new_password.min' => 'Yeni şifre en az 6 karakter olmalıdır.',
            'new_password.confirmed' => 'Yeni şifre tekrarı eşleşmiyor.',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Doğrulama hatası', $validator->errors()->toArray(), 422);
        }

        $inputCode = preg_replace('/\D+/', '', (string) $request->code);
        $user = $this->findUserForForgotPassword($request);

        if (! $user || $user->code === null || $user->code === '') {
            return $this->error([], 'Doğrulama kodu hatalı veya süresi dolmuş.', ['code' => ['Doğrulama kodu hatalı veya süresi dolmuş.']], 422);
        }

        if (strlen($inputCode) !== 6 || (string) $user->code !== $inputCode) {
            return $this->error([], 'Doğrulama kodu hatalı veya süresi dolmuş.', ['code' => ['Doğrulama kodu hatalı veya süresi dolmuş.']], 422);
        }

        $user->update([
            'password' => $request->new_password,
            'code' => null,
        ]);

        $user->tokens()->delete();

        return $this->success([], 'Şifreniz güncellendi.');
    }

    /**
     * Şifre değiştirme için e-posta doğrulama kodu gönderir. Kod user.code alanına yazılır.
     */
    public function sendPasswordChangeCode(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return $this->error([], 'Oturum bulunamadı.', [], 401);
        }

        if (! $user->phone) {
            return $this->error([], 'Hesabınızda kayıtlı telefon numarası bulunamadı.', [], 422);
        }

        $code = (string) random_int(100000, 999999);
        $user->update(['code' => $code]);

        try {
            $this->smsService->sendVerificationCode((string) $user->phone, $code, 'otp');
        } catch (\Throwable $e) {
            report($e);

            return $this->error([], 'Doğrulama kodu gönderilemedi. Lütfen daha sonra tekrar deneyin.', [], 503);
        }

        return $this->success([
            'phone_masked' => $this->maskPhone($user->phone),
        ], 'Doğrulama kodu telefon numaranıza gönderildi.');
    }

    /**
     * E-posta doğrulama kodu (user.code) ile şifreyi günceller. Mevcut şifre gerekmez.
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:6',
            'new_password' => 'required|string|min:6|confirmed',
        ], [
            'code.required' => 'Doğrulama kodu zorunludur.',
            'code.size' => 'Doğrulama kodu 6 haneli olmalıdır.',
            'new_password.required' => 'Yeni şifre zorunludur.',
            'new_password.min' => 'Yeni şifre en az 6 karakter olmalıdır.',
            'new_password.confirmed' => 'Yeni şifre tekrarı eşleşmiyor.',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Doğrulama hatası', $validator->errors()->toArray(), 422);
        }

        $user = $request->user();
        if (! $user) {
            return $this->error([], 'Oturum bulunamadı.', [], 401);
        }

        if ((string) $user->code !== (string) $request->code) {
            return $this->error([], 'Doğrulama kodu hatalı veya süresi dolmuş.', ['code' => ['Doğrulama kodu hatalı veya süresi dolmuş.']], 422);
        }

        $user->update([
            'password' => $request->new_password,
            'code' => null,
        ]);

        $user->tokens()->delete();

        return $this->success([], 'Şifreniz güncellendi.');
    }

    /**
     * Hesabı fiziksel olarak silmez, pasife alır ve tüm oturumları kapatır.
     * Şifre doğrulanır.
     */
    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ], [
            'password.required' => 'Şifrenizi girmeniz gerekir.',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Doğrulama hatası', $validator->errors()->toArray(), 422);
        }

        $user = $request->user();
        if (! $user) {
            return $this->error([], 'Oturum bulunamadı.', [], 401);
        }

        $hash = $user->password;
        if (! is_string($hash) || $hash === '' || ! Hash::check($request->password, $hash)) {
            return $this->error([], 'Şifre hatalı.', ['password' => ['Şifre hatalı.']], 422);
        }

        $this->deactivateUserAccountService->deactivate($user);

        return $this->success([], 'Hesabınız pasife alındı.');
    }

    private function notifySlackNewUser(
        SlackService $slackService,
        User $user,
        string $translationKey,
        ?string $attachmentColor = null
    ): void {
        $channel = (string) config('slack.notifications.new_user');
        if ($channel === '') {
            return;
        }

        $message = __($translationKey, [
            'type' => match ($user->type) {
                'cargo_owner', 'vehicle_owner' => __('slack.user_types.'.$user->type, [], 'tr'),
                default => $user->type,
            },
            'phone' => $user->phone,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'created_at' => $user->created_at?->format('d.m.Y H:i:s') ?? '—',
        ], 'tr');

        $slackService->sendMessage($message, $channel, $attachmentColor);
    }

    private function registerPushDeviceFromRequest(Request $request, User $user): void
    {
        if (! $request->filled('expo_push_token') || ! $request->filled('platform')) {
            return;
        }

        $this->userDeviceService->register(
            $user,
            (string) $request->expo_push_token,
            (string) $request->platform,
        );
    }
}
