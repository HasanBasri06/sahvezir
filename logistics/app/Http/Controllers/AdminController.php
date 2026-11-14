<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Models\Admin;
use App\Models\Car;
use App\Models\CarDetail;
use App\Models\City;
use App\Models\District;
use App\Models\Message; 
use App\Models\PostType;
use App\Models\Requests;
use App\Models\SearchAnalysis;
use App\Models\Shipment;
use App\Models\ShipmentCallRequest;
use App\Models\ShipmentOrder;
use App\Models\User;
use App\Services\SMSService;
use App\Support\CatalogAssetStorage;
use App\Support\CatalogAssetUrl;
use App\Support\CityNameNormalizer;
use App\Support\PublicStorageUrl;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    use ResponseTrait;

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors());
        }

        $admin = Admin::where('email', $request->email)->first();
        if (! $admin) {
            return $this->error([], 'Admin bulunamadı', [], 401);
        }

        if (! Hash::check($request->password, $admin->password)) {
            return $this->error([], 'Şifre hatalı', [], 401);
        }

        $token = $admin->createToken('admin-token')->plainTextToken;

        return $this->success([
            'token' => $token,
        ], 'Admin girişi başarılı');
    }

    public function check(Request $request)
    {
        $admin = $request->user();

        if (! $admin instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        return $this->success([
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
            ],
        ], 'Token geçerli');
    }

    public function dashboard(Request $request)
    {
        if (! $request->user() instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $mapRecentUser = fn (User $user) => [
            'id' => $user->id,
            'full_name' => trim($user->first_name.' '.$user->last_name),
            'phone' => $user->phone,
            'created_at' => $user->created_at?->toDateTimeString(),
        ];

        $todayUsers = User::query()
            ->select(['id', 'first_name', 'last_name', 'phone', 'created_at'])
            ->whereDate('created_at', today())
            ->orderByDesc('created_at')
            ->get()
            ->map($mapRecentUser)
            ->values();

        $yesterdayUsers = User::query()
            ->select(['id', 'first_name', 'last_name', 'phone', 'created_at'])
            ->whereDate('created_at', today()->subDay())
            ->orderByDesc('created_at')
            ->get()
            ->map($mapRecentUser)
            ->values();

        $totalUsers = User::query()->count();

        $totalShipments = Shipment::query()->count();

        $totalCallRequests = ShipmentCallRequest::query()->count();

        $totalSearches = SearchAnalysis::query()->count();

        $recentShipments = Shipment::query()
            ->with(['creator:id,first_name,last_name'])
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn (Shipment $shipment) => [
                'id' => $shipment->id,
                'from_city' => $shipment->f_where_city,
                'from_district' => $shipment->f_where_district,
                'to_city' => $shipment->t_where_city,
                'to_district' => $shipment->t_where_district,
                'creator_name' => $shipment->creator
                    ? trim($shipment->creator->first_name.' '.$shipment->creator->last_name)
                    : '—',
                'created_at' => $shipment->created_at?->toDateTimeString(),
            ])
            ->values();

        $todayRequests = Requests::query()
            ->with([
                'user:id,first_name,last_name,phone',
                'shipment:id,creater_id,f_where_city,f_where_district,t_where_city,t_where_district',
                'shipment.creator:id,first_name,last_name,phone',
            ])
            ->whereDate('created_at', today())
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Requests $requestRow) {
                $shipment = $requestRow->shipment;

                return [
                    'id' => $requestRow->id,
                    'sender_name' => $requestRow->user
                        ? trim($requestRow->user->first_name.' '.$requestRow->user->last_name)
                        : '—',
                    'sender_phone' => $requestRow->user?->phone,
                    'receiver_name' => $shipment?->creator
                        ? trim($shipment->creator->first_name.' '.$shipment->creator->last_name)
                        : '—',
                    'receiver_phone' => $shipment?->creator?->phone,
                    'from_city' => $shipment?->f_where_city,
                    'from_district' => $shipment?->f_where_district,
                    'to_city' => $shipment?->t_where_city,
                    'to_district' => $shipment?->t_where_district,
                    'created_at' => $requestRow->getRawOriginal('created_at'),
                ];
            })
            ->values();

        return $this->success([
            'today_users' => $todayUsers,
            'today_users_count' => $todayUsers->count(),
            'yesterday_users' => $yesterdayUsers,
            'yesterday_users_count' => $yesterdayUsers->count(),
            'total_users' => $totalUsers,
            'total_shipments' => $totalShipments,
            'total_call_requests' => $totalCallRequests,
            'total_searches' => $totalSearches,
            'recent_shipments' => $recentShipments,
            'today_requests' => $todayRequests,
            'today_requests_count' => $todayRequests->count(),
        ]);
    }

    public function searchUsers(Request $request)
    {
        if (! $request->user() instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $search = trim((string) $request->query('search', ''));

        $query = User::query()
            ->select(['id', 'first_name', 'last_name', 'email', 'phone', 'type'])
            ->where('with_admin', 1)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(20);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%'.$search.'%']);
            });
        }

        $users = $query->get()->map(fn (User $user) => [
            'id' => $user->id,
            'full_name' => trim($user->first_name.' '.$user->last_name),
            'email' => $user->email,
            'phone' => $user->phone,
            'type' => $user->type,
        ]);

        return $this->success(['users' => $users]);
    }

    /**
     * Seçilen kullanıcının telefon numarasına bilgilendirme SMS'i gönderir (maks. 155 karakter).
     */
    public function sendSms(Request $request, SMSService $smsService)
    {
        $admin = $request->user();
        if (! $admin instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'message' => 'required|string|max:155',
            'password' => 'required|string',
        ], [
            'user_id.required' => 'Kullanıcı seçin.',
            'user_id.integer' => 'Geçersiz kullanıcı.',
            'user_id.exists' => 'Kullanıcı bulunamadı.',
            'message.required' => 'Mesaj içeriği gerekli.',
            'message.max' => 'Mesaj en fazla 155 karakter olabilir.',
            'password.required' => 'Admin şifrenizi girmeniz gerekir.',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Doğrulama hatası', $validator->errors()->toArray(), 422);
        }

        $hash = $admin->password;
        if (! is_string($hash) || $hash === '' || ! Hash::check($request->input('password'), $hash)) {
            return $this->error([], 'Admin şifresi hatalı.', ['password' => ['Admin şifresi hatalı.']], 422);
        }

        $user = User::find((int) $request->input('user_id'));
        if (! $user || empty($user->phone)) {
            return $this->error([], 'Kullanıcının telefon numarası bulunamadı.', [], 422);
        }

        $response = $smsService->send1toN($user->phone, (string) $request->input('message'));
        if ($response === null) {
            return $this->error([], 'SMS gönderilemedi. Telefon numarası geçersiz olabilir.', [], 422);
        }

        

        return $this->success([
            'user_id' => $user->id,
            'phone' => $user->phone,
        ], 'SMS gönderildi.');
    }

    public function listUserDocuments(Request $request)
    {
        if (! $request->user() instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $search = trim((string) $request->query('search', ''));
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);

        $query = User::query()
            ->select([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
                'type',
                'email_verify',
                'verified',
                'driving_licance_doc',
                'psikoteknik_doc',
                'profile_image',
                'id_image',
                'src_doc',
            ])
            ->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%'.$search.'%']);
            });
        }

        $paginator = $query->paginate($perPage);

        $users = $paginator->getCollection()->map(fn (User $user) => [
            'id' => $user->id,
            'full_name' => trim($user->first_name.' '.$user->last_name),
            'email' => $user->email,
            'phone' => $user->phone,
            'type' => $user->type,
            'email_verify' => (bool) $user->email_verify,
            'verified' => (int) $user->verified === 1,
            'driving_licance_doc' => PublicStorageUrl::absoluteForRequest($request, $user->driving_licance_doc),
            'psikoteknik_doc' => PublicStorageUrl::absoluteForRequest($request, $user->psikoteknik_doc),
            'profile_image' => PublicStorageUrl::absoluteForRequest($request, $user->profile_image),
            'id_image' => PublicStorageUrl::absoluteForRequest($request, $user->id_image),
            'src_doc' => PublicStorageUrl::absoluteForRequest($request, $user->src_doc),
        ])->values();

        return $this->success([
            'users' => $users,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

public function listUsers(Request $request)
{
    if (! $request->user() instanceof Admin) {
        return $this->error([], 'Yetkisiz', [], 401);
    }

    $search = trim((string) $request->query('search', ''));
    $perPage = min(max((int) $request->query('per_page', 15), 1), 50);
    
    // Laravel'in boolean() metodu "true"/"false", 1/0 string ifadelerini doğru parse eder
    $isAnalysis = $request->boolean('isAnalysis');

    $query = User::query()
        ->select([
            'id',
            'first_name',
            'last_name',
            'email',
            'phone',
            'type',
            'payment_confirm',
            'end_payment_date',
            'created_at',
        ]);

    if ($isAnalysis) {
        // 1. Önce NULL olmayanları öne al
        // 2. Bugünün tarihi ile end_payment_date arasındaki farkın mutlak değerine (en yakın güne) göre sırala
        $query
            ->orderByRaw('end_payment_date IS NULL ASC')
            ->orderByRaw('ABS(DATEDIFF(end_payment_date, CURDATE())) ASC');
    } else {
        $query->orderByDesc('id');
    }

    if ($search !== '') {
        $query->where(function ($builder) use ($search) {
            $builder
                ->where('first_name', 'like', '%'.$search.'%')
                ->orWhere('last_name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')
                ->orWhere('phone', 'like', '%'.$search.'%')
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%'.$search.'%']);
        });
    }

    $paginator = $query->paginate($perPage);

    $today = now()->startOfDay();

    $users = $paginator->getCollection()->map(function (User $user) use ($today) {
        $endPaymentDate = $user->end_payment_date
            ? \Illuminate\Support\Carbon::parse($user->end_payment_date)
            : null;

        $daysLeft = $endPaymentDate
            ? $today->diffInDays($endPaymentDate->copy()->startOfDay(), false)
            : null;

        return [
            'id' => $user->id,
            'full_name' => trim($user->first_name.' '.$user->last_name),
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'type' => $user->type,
            'payment_confirm' => (int) $user->payment_confirm === 1,
            'end_payment_date' => optional($endPaymentDate)->toDateString(),
            'days_left' => $daysLeft !== null ? (int) $daysLeft : null,
            'created_at' => optional($user->created_at)->toDateTimeString(),
        ];
    })->values();

    return $this->success([
        'users' => $users,
        'current_page' => $paginator->currentPage(),
        'last_page' => $paginator->lastPage(),
        'per_page' => $paginator->perPage(),
        'total' => $paginator->total(),
    ]);
}

    public function approveUser(Request $request, int $user)
    {
        if (! $request->user() instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $target = User::query()->find($user);
        if (! $target) {
            return $this->error([], 'Kullanıcı bulunamadı.', [], 404);
        }

        $target->verified = 1;
        $target->save();

        return $this->success([
            'user' => [
                'id' => $target->id,
                'verified' => (bool) $target->verified,
            ],
        ], 'Kullanıcı hesabı onaylandı.');
    }

    public function revokeUserApproval(Request $request, int $user)
    {
        if (! $request->user() instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $target = User::query()->find($user);
        if (! $target) {
            return $this->error([], 'Kullanıcı bulunamadı.', [], 404);
        }

        $target->verified = 0;
        $target->save();

        return $this->success([
            'user' => [
                'id' => $target->id,
                'verified' => (bool) $target->verified,
            ],
        ], 'Kullanıcı onayı geri alındı.');
    }

    public function rejectUser(Request $request, int $user)
    {
        if (! $request->user() instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $target = User::query()->find($user);
        if (! $target) {
            return $this->error([], 'Kullanıcı bulunamadı.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'description' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors(), 422);
        }

        return $this->success([
            'user_id' => $target->id,
            'description' => $request->input('description'),
        ], 'Red talebi alındı.');
    }

    public function extendUserPayment(Request $request, int $user)
    {
        if (! $request->user() instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $target = User::query()->find($user);
        if (! $target) {
            return $this->error([], 'Kullanıcı bulunamadı.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'months' => 'required_without:end_payment_date|nullable|integer|in:1,3,6,12',
            'end_payment_date' => 'required_without:months|nullable|date|after_or_equal:today',
        ], [
            'end_payment_date.after_or_equal' => 'Bitiş tarihi bugünden önce olamaz.',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors(), 422);
        }

        $subscriptions = app(\App\Services\SubscriptionService::class);
        if ($request->filled('end_payment_date')) {
            // Kesin bitiş tarihi
            $sub = $subscriptions->grant($target, ['source' => 'admin'], endsAt: \Carbon\Carbon::parse($request->input('end_payment_date'), \App\Services\SubscriptionService::TZ)->endOfDay());
        } else {
            // Ay ekleme: aktif paket bitmediyse üstüne eklenir
            $months = (int) $request->input('months');
            $sub = $subscriptions->grant($target, ['source' => 'admin', 'package_name' => $months.' Aylık', 'plan_months' => $months], months: $months);
        }
        $newEndDate = \Carbon\Carbon::parse($sub->ends_at);
        $target->refresh();

        return $this->success([
            'user' => [
                'id' => $target->id,
                'payment_confirm' => (bool) $target->payment_confirm,
                'end_payment_date' => $newEndDate->toDateString(),
            ],
        ], 'Kullanıcı hesabı uzatıldı.');
    }

    public function deleteUser(Request $request, int $user)
    {
        $admin = $request->user();
        if (! $admin instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ], [
            'password.required' => 'Admin şifrenizi girmeniz gerekir.',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors(), 422);
        }

        $hash = $admin->password;
        if (! is_string($hash) || $hash === '' || ! Hash::check($request->input('password'), $hash)) {
            return $this->error([], 'Admin şifresi hatalı.', ['password' => ['Admin şifresi hatalı.']], 422);
        }

        $target = User::query()->find($user);
        if (! $target) {
            return $this->error([], 'Kullanıcı bulunamadı.', [], 404);
        }

        try {
            $target->tokens()->delete();
            $target->userDetail()->delete();
            $target->devices()->delete();
            $target->delete();
        } catch (\Throwable $exception) {
            return $this->error([], 'Kullanıcı silinemedi. İlişkili kayıtlar bulunuyor olabilir.', [], 409);
        }

        return $this->success(['user_id' => $user], 'Kullanıcı hesabı silindi.');
    }

    public function storeUser(Request $request)
    {
        if (! $request->user() instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string|max:20|unique:users,phone',
            'password' => 'required|string|min:6',
            'type' => 'required|string|in:cargo_owner,vehicle_owner',
        ], [
            'phone.unique' => 'Bu telefon numarası zaten kullanılıyor.',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors(), 422);
        }

        $phone = $this->normalizePhoneInput($request->input('phone'));

        $user = User::create([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'email' => $request->input('email'),
            'phone' => $phone,
            'password' => $request->input('password'),
            'type' => $request->input('type'),
            'status' => UserStatus::ACTIVE->value,
            'phone_verify' => true,
            'email_verify' => true,
            'with_admin' => 1,
        ]);
        app(\App\Services\SubscriptionService::class)->grant($user, ['source' => 'admin', 'package_name' => '1 Aylık', 'plan_months' => 1], months: 1);

        return $this->success([
            'user' => [
                'id' => $user->id,
                'full_name' => trim($user->first_name.' '.$user->last_name),
                'email' => $user->email,
                'phone' => $user->phone,
                'type' => $user->type,
            ],
        ], 'Kullanıcı başarıyla oluşturuldu', 201);
    }

    public function storeShipment(Request $request)
    {
        return $this->success($request);
        if (! $request->user() instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $validator = Validator::make($request->all(), [
            'from_city' => 'nullable|string|max:255',
            'from_district' => 'nullable|string|max:255',
            'to_city' => 'nullable|string|max:255',
            'to_district' => 'nullable|string|max:255',
            'weight' => 'nullable|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
            'call_access' => 'nullable|in:0,1',
            'departure_time' => 'nullable|date_format:H:i',
            'time_arrival' => 'nullable|date_format:H:i',
            'departure_date' => 'nullable|date',
            'car_id' => 'required|integer|exists:cars,id',
            'car_detail_id' => 'nullable|integer|exists:car_detail,id',
            'creater_id' => 'required|integer|exists:users,id',
            'post_type_id' => 'required|integer|exists:post_types,id',
            'isPart' => 'required|integer'
        ], [
            'creater_id.required' => 'Ekleyen kullanıcı seçilmelidir.',
            'car_id.required' => 'Araç seçilmelidir.',
            'post_type_id.required' => 'Taşınacak yükün türü seçilmelidir.',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors(), 422);
        }

        $carId = $request->input('car_id');
        $carDetailId = $request->input('car_detail_id');

        if ($carDetailId) {
            $detail = CarDetail::query()->find($carDetailId);
            if (! $detail) {
                return $this->error([], 'Araç detayı bulunamadı.', ['car_detail_id' => ['Geçersiz araç detayı.']], 422);
            }
            if ($carId && (int) $detail->car_id !== (int) $carId) {
                return $this->error([], 'Seçilen araç detayı bu araca ait değil.', ['car_detail_id' => ['Araç detayı seçilen araç ile uyuşmuyor.']], 422);
            }
            $carId = $carId ?: $detail->car_id;
        }

        $defaults = $this->resolveShipmentReferences(
            (int) $request->input('creater_id'),
            (int) $carId,
            (int) $request->input('post_type_id'),
        );
        if (isset($defaults['error'])) {
            return $this->error([], $defaults['error'], [], 422);
        }

        $departureTime = $this->normalizeTime($request->input('departure_time'));
        $timeArrival = $this->normalizeTime($request->input('time_arrival'));
        $departureDate = $request->filled('departure_date') ? $request->input('departure_date') : null;

        $weight = $request->filled('weight')
            ? trim((string) $request->input('weight')).' kg'
            : null;

        $shipment = Shipment::create([
            'slug' => Str::random(25),
            'call_access' => (int) ($request->input('call_access', 0)),
            'f_where_city' => (string) ($request->input('from_city') ?? ''),
            'f_where_district' => (string) ($request->input('from_district') ?? ''),
            't_where_city' => (string) ($request->input('to_city') ?? ''),
            't_where_district' => (string) ($request->input('to_district') ?? ''),
            'hours' => 'belirlenecek',
            'creater_id' => $defaults['creater_id'],
            'car_id' => $defaults['car_id'],
            'car_detail_id' => $carDetailId ? (int) $carDetailId : null,
            'post_type_id' => $defaults['post_type_id'],
            'price' => (float) ($request->input('price', 0)),
            'departure_time' => $departureTime,
            'time_arrival' => $timeArrival,
            'departure_date' => $departureDate,
            'weight' => $weight,
            'status' => 'active',
            'part_load' => (int) $request->get('isPart')
        ]);

        return $this->success([
            'shipment' => $shipment,
        ], 'İlan başarıyla oluşturuldu');
    }

    public function shipmentAnalytics(Request $request)
    {
        if (! $request->user() instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $mostLoading = Shipment::query()
            ->selectRaw('f_where_city as city, COUNT(*) as count')
            ->where('f_where_city', '!=', '')
            ->groupBy('f_where_city')
            ->orderByDesc('count')
            ->first();

        $mostUnloading = Shipment::query()
            ->selectRaw('t_where_city as city, COUNT(*) as count')
            ->where('t_where_city', '!=', '')
            ->groupBy('t_where_city')
            ->orderByDesc('count')
            ->first();

        $cities = City::query()->orderBy('name')->pluck('name')->all();

        $loadingCitiesWithListings = Shipment::query()
            ->where('f_where_city', '!=', '')
            ->distinct()
            ->pluck('f_where_city')
            ->map(fn ($name) => CityNameNormalizer::canonicalForFilter($name))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $unloadingCitiesWithListings = Shipment::query()
            ->where('t_where_city', '!=', '')
            ->distinct()
            ->pluck('t_where_city')
            ->map(fn ($name) => CityNameNormalizer::canonicalForFilter($name))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $loadingSet = array_flip(array_map(
            fn ($name) => mb_strtolower((string) $name, 'UTF-8'),
            $loadingCitiesWithListings
        ));
        $unloadingSet = array_flip(array_map(
            fn ($name) => mb_strtolower((string) $name, 'UTF-8'),
            $unloadingCitiesWithListings
        ));

        $emptyLoadingCities = array_values(array_filter(
            $cities,
            fn ($city) => ! isset($loadingSet[mb_strtolower((string) $city, 'UTF-8')])
        ));
        $emptyUnloadingCities = array_values(array_filter(
            $cities,
            fn ($city) => ! isset($unloadingSet[mb_strtolower((string) $city, 'UTF-8')])
        ));

        $formatCityStat = fn ($row) => $row
            ? ['city' => $row->city, 'count' => (int) $row->count]
            : null;

        return $this->success([
            'most_loading_city' => $formatCityStat($mostLoading),
            'most_unloading_city' => $formatCityStat($mostUnloading),
            'empty_loading_cities' => $emptyLoadingCities,
            'empty_loading_cities_count' => count($emptyLoadingCities),
            'empty_unloading_cities' => $emptyUnloadingCities,
            'empty_unloading_cities_count' => count($emptyUnloadingCities),
        ]);
    }

    public function listSearchAnalysis(Request $request)
    {
        if (! $request->user() instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $search = trim((string) $request->query('search', ''));
        $listingStatus = trim((string) $request->query('listing_status', ''));
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);

        $query = SearchAnalysis::query()
            ->with('callingUser:id,first_name,last_name,phone')
            ->orderByDesc('id');

        if ($listingStatus === 'listed') {
            $query->where('count', '>', 0);
        } elseif ($listingStatus === 'unlisted') {
            $query->where('count', 0);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('f_where_city', 'like', '%'.$search.'%')
                    ->orWhere('f_where_district', 'like', '%'.$search.'%')
                    ->orWhere('t_where_city', 'like', '%'.$search.'%')
                    ->orWhere('t_where_district', 'like', '%'.$search.'%')
                    ->orWhereHas('callingUser', function ($userQuery) use ($search) {
                        $userQuery
                            ->where('first_name', 'like', '%'.$search.'%')
                            ->orWhere('last_name', 'like', '%'.$search.'%')
                            ->orWhere('phone', 'like', '%'.$search.'%')
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%'.$search.'%']);
                    });
            });
        }

        $paginator = $query->paginate($perPage);

        $records = $paginator->getCollection()->map(function (SearchAnalysis $record) {
            $count = (int) $record->count;

            return [
                'id' => $record->id,
                'calling_user_id' => $record->calling_user_id,
                'caller_name' => $record->callingUser
                    ? trim($record->callingUser->first_name.' '.$record->callingUser->last_name)
                    : '—',
                'caller_phone' => $record->callingUser?->phone,
                'f_where_city' => $record->f_where_city,
                'f_where_district' => $record->f_where_district,
                't_where_city' => $record->t_where_city,
                't_where_district' => $record->t_where_district,
                'count' => $count,
                'has_results' => $count > 0,
                'listing_status' => $count > 0 ? 'listed' : 'unlisted',
                'created_at' => $record->created_at?->toDateTimeString(),
            ];
        })->values();

        return $this->success([
            'records' => $records,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'summary' => $this->searchAnalysisSummary(),
        ]);
    }

    private function searchAnalysisSummary(): array
    {
        $fromCounts = SearchAnalysis::query()
            ->whereNotNull('f_where_city')
            ->where('f_where_city', '!=', '')
            ->selectRaw('f_where_city as city, COUNT(*) as cnt')
            ->groupBy('f_where_city')
            ->pluck('cnt', 'city');

        $toCounts = SearchAnalysis::query()
            ->whereNotNull('t_where_city')
            ->where('t_where_city', '!=', '')
            ->selectRaw('t_where_city as city, COUNT(*) as cnt')
            ->groupBy('t_where_city')
            ->pluck('cnt', 'city');

        $combined = [];
        foreach ($fromCounts as $city => $cnt) {
            $combined[$city] = ($combined[$city] ?? 0) + (int) $cnt;
        }
        foreach ($toCounts as $city => $cnt) {
            $combined[$city] = ($combined[$city] ?? 0) + (int) $cnt;
        }

        $mostSearchedCity = null;
        if ($combined !== []) {
            arsort($combined);
            $city = array_key_first($combined);
            $mostSearchedCity = [
                'city' => $city,
                'count' => $combined[$city],
            ];
        }

        $mostZeroResult = SearchAnalysis::query()
            ->where('count', 0)
            ->selectRaw('f_where_city, f_where_district, t_where_city, t_where_district, COUNT(*) as search_count')
            ->groupBy('f_where_city', 'f_where_district', 't_where_city', 't_where_district')
            ->orderByDesc('search_count')
            ->first();

        $mostZeroResultSearch = $mostZeroResult
            ? [
                'f_where_city' => $mostZeroResult->f_where_city,
                'f_where_district' => $mostZeroResult->f_where_district,
                't_where_city' => $mostZeroResult->t_where_city,
                't_where_district' => $mostZeroResult->t_where_district,
                'search_count' => (int) $mostZeroResult->search_count,
            ]
            : null;

        $listedSearches = SearchAnalysis::query()->where('count', '>', 0)->count();
        $unlistedSearches = SearchAnalysis::query()->where('count', 0)->count();

        return [
            'most_searched_city' => $mostSearchedCity,
            'most_zero_result_search' => $mostZeroResultSearch,
            'total_searches' => $listedSearches + $unlistedSearches,
            'listed_searches' => $listedSearches,
            'unlisted_searches' => $unlistedSearches,
        ];
    }

    public function listShipments(Request $request)
    {
        if (! $request->user() instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $search = trim((string) $request->query('search', ''));
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);

        $query = Shipment::query()
            ->with([
                'creator:id,first_name,last_name,phone',
                'postType:id,value',
            ])
            ->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('f_where_city', 'like', '%'.$search.'%')
                    ->orWhere('f_where_district', 'like', '%'.$search.'%')
                    ->orWhere('t_where_city', 'like', '%'.$search.'%')
                    ->orWhere('t_where_district', 'like', '%'.$search.'%')
                    ->orWhereHas('creator', function ($creatorQuery) use ($search) {
                        $creatorQuery
                            ->where('first_name', 'like', '%'.$search.'%')
                            ->orWhere('last_name', 'like', '%'.$search.'%')
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%'.$search.'%']);
                    });
            });
        }

        $paginator = $query->paginate($perPage);

        $shipments = $paginator->getCollection()->map(fn (Shipment $shipment) => [
            'id' => $shipment->id,
            'slug' => $shipment->slug,
            'from_city' => $shipment->f_where_city,
            'from_district' => $shipment->f_where_district,
            'to_city' => $shipment->t_where_city,
            'to_district' => $shipment->t_where_district,
            'status' => $shipment->status,
            'price' => (float) $shipment->getRawOriginal('price'),
            'creator_name' => $shipment->creator
                ? trim($shipment->creator->first_name.' '.$shipment->creator->last_name)
                : '—',
            'creator_phone' => $shipment->creator?->phone,
            'post_type' => $shipment->postType?->value,
            'created_at' => $shipment->created_at?->toDateTimeString(),
        ])->values();

        return $this->success([
            'shipments' => $shipments,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function deleteShipment(Request $request, int $shipment)
    {
        if (! $request->user() instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $target = Shipment::query()->find($shipment);
        if (! $target) {
            return $this->error([], 'İlan bulunamadı.', [], 404);
        }

        try {
            Requests::query()->where('shipment_id', $target->id)->delete();
            ShipmentOrder::query()->where('shipment_id', $target->id)->delete();
            Message::query()->where('shipment_id', $target->id)->delete();
            $target->delete();
        } catch (\Throwable $exception) {
            return $this->error([], 'İlan silinemedi. İlişkili kayıtlar bulunuyor olabilir.', [], 409);
        }

        return $this->success(['shipment_id' => $shipment], 'İlan silindi.');
    }

    public function listCars(Request $request)
    {
        if (! $request->user() instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $search = trim((string) $request->query('search', ''));

        $query = Car::query()->with('details')->orderBy('order')->orderBy('id');

        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $cars = $query->get()->map(fn (Car $car) => [
            'id' => $car->id,
            'name' => $car->name,
            'image' => $car->image,
            'image_url' => CatalogAssetUrl::vehicle($request, $car->image),
            'price' => $car->price !== null ? (float) $car->price : null,
            'cost_per_km' => $car->cost_per_km !== null ? (float) $car->cost_per_km : null,
            'status' => $car->status,
            'order' => (int) $car->order,
            'width' => $car->width !== null ? (float) $car->width : null,
            'height' => $car->height !== null ? (float) $car->height : null,
            'details_count' => $car->details->count(),
            'details' => $car->details->map(fn (CarDetail $detail) => $this->mapCarDetail($request, $detail))->values(),
        ])->values();

        return $this->success(['cars' => $cars]);
    }

    public function storeCar(Request $request)
    {
        if (! $request->user() instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'cost_per_km' => 'nullable|numeric|min:0',
            'status' => 'required|string|in:active,inactive,pending',
            'order' => 'nullable|integer|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors(), 422);
        }

        $imageFilename = null;
        if ($request->hasFile('image')) {
            $imageFilename = CatalogAssetStorage::storeUploadedFile(
                $request->file('image'),
                CatalogAssetUrl::FOLDER_VEHICLES
            );
        }

        $car = Car::query()->create([
            'name' => $request->input('name'),
            'image' => $imageFilename,
            'price' => $request->input('price'),
            'cost_per_km' => $request->input('cost_per_km'),
            'status' => $request->input('status'),
            'order' => (int) ($request->input('order') ?? 0),
            'width' => $request->input('width'),
            'height' => $request->input('height'),
        ]);

        if ($request->has('details')) {
            $this->syncCarDetails($request, $car);
        }

        return $this->success([
            'car' => $car->fresh('details'),
        ], 'Araç eklendi.');
    }

    public function updateCar(Request $request, int $car)
    {
        if (! $request->user() instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $target = Car::query()->find($car);
        if (! $target) {
            return $this->error([], 'Araç bulunamadı.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'cost_per_km' => 'nullable|numeric|min:0',
            'status' => 'required|string|in:active,inactive,pending',
            'order' => 'nullable|integer|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors(), 422);
        }

        $imageFilename = $target->image;
        if ($request->hasFile('image')) {
            CatalogAssetStorage::deleteFile(CatalogAssetUrl::FOLDER_VEHICLES, $target->image);
            $imageFilename = CatalogAssetStorage::storeUploadedFile(
                $request->file('image'),
                CatalogAssetUrl::FOLDER_VEHICLES
            );
        }

        $target->update([
            'name' => $request->input('name'),
            'image' => $imageFilename,
            'price' => $request->input('price'),
            'cost_per_km' => $request->input('cost_per_km'),
            'status' => $request->input('status'),
            'order' => (int) ($request->input('order') ?? 0),
            'width' => $request->input('width'),
            'height' => $request->input('height'),
        ]);

        if ($request->has('details')) {
            $this->syncCarDetails($request, $target);
        }

        return $this->success([
            'car' => $target->fresh('details'),
        ], 'Araç güncellendi.');
    }

    public function listCities(Request $request)
    {
        if (! $request->user() instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $search = trim((string) $request->query('search', ''));

        $query = City::query()->withCount('districts')->orderBy('name');

        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $cities = $query->get()->map(fn (City $city) => [
            'id' => $city->id,
            'name' => $city->name,
            'code' => $city->code,
            'districts_count' => (int) $city->districts_count,
        ])->values();

        return $this->success(['cities' => $cities]);
    }

    public function updateCity(Request $request, int $city)
    {
        if (! $request->user() instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $target = City::query()->find($city);
        if (! $target) {
            return $this->error([], 'Şehir bulunamadı.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors(), 422);
        }

        $target->update([
            'name' => $request->input('name'),
            'code' => $request->input('code'),
        ]);

        return $this->success(['city' => $target->fresh()], 'Şehir güncellendi.');
    }

    public function listCityDistricts(Request $request, int $city)
    {
        if (! $request->user() instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $target = City::query()->find($city);
        if (! $target) {
            return $this->error([], 'Şehir bulunamadı.', [], 404);
        }

        $districts = District::query()
            ->where('city_id', $city)
            ->orderBy('name')
            ->get()
            ->map(fn (District $district) => [
                'id' => $district->id,
                'city_id' => $district->city_id,
                'name' => $district->name,
            ])
            ->values();

        return $this->success([
            'city' => [
                'id' => $target->id,
                'name' => $target->name,
                'code' => $target->code,
            ],
            'districts' => $districts,
        ]);
    }

    public function storeDistrict(Request $request, int $city)
    {
        if (! $request->user() instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $target = City::query()->find($city);
        if (! $target) {
            return $this->error([], 'Şehir bulunamadı.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors(), 422);
        }

        $name = trim((string) $request->input('name'));

        $exists = District::query()
            ->where('city_id', $city)
            ->where('name', $name)
            ->exists();

        if ($exists) {
            return $this->error([], 'Bu ilçe zaten kayıtlı.', [], 422);
        }

        $district = District::query()->create([
            'city_id' => $city,
            'name' => $name,
        ]);

        return $this->success(['district' => $district], 'İlçe eklendi.');
    }

    public function updateDistrict(Request $request, int $district)
    {
        if (! $request->user() instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $target = District::query()->find($district);
        if (! $target) {
            return $this->error([], 'İlçe bulunamadı.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors(), 422);
        }

        $name = trim((string) $request->input('name'));

        $exists = District::query()
            ->where('city_id', $target->city_id)
            ->where('name', $name)
            ->where('id', '!=', $target->id)
            ->exists();

        if ($exists) {
            return $this->error([], 'Bu ilçe adı zaten kayıtlı.', [], 422);
        }

        $target->update(['name' => $name]);

        return $this->success(['district' => $target->fresh()], 'İlçe güncellendi.');
    }

    public function deleteDistrict(Request $request, int $district)
    {
        if (! $request->user() instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $target = District::query()->find($district);
        if (! $target) {
            return $this->error([], 'İlçe bulunamadı.', [], 404);
        }

        $target->delete();

        return $this->success(['district_id' => $district], 'İlçe silindi.');
    }

    public function listPostTypes(Request $request)
    {
        if (! $request->user() instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $search = trim((string) $request->query('search', ''));

        $query = PostType::query()->orderBy('id');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('value', 'like', '%'.$search.'%');
            });
        }

        $postTypes = $query->get()->map(fn (PostType $postType) => [
            'id' => $postType->id,
            'name' => $postType->name,
            'value' => $postType->value,
            'image' => $postType->image,
            'image_url' => CatalogAssetUrl::postType($request, $postType->image),
            'status' => $postType->status,
        ])->values();

        return $this->success(['post_types' => $postTypes]);
    }

    public function storePostType(Request $request)
    {
        if (! $request->user() instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'value' => 'required|string|max:255',
            'status' => 'required|string|in:active,inactive',
            'image' => 'nullable|image|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors(), 422);
        }

        $imageFilename = null;
        if ($request->hasFile('image')) {
            $imageFilename = CatalogAssetStorage::storeUploadedFile(
                $request->file('image'),
                CatalogAssetUrl::FOLDER_POST_TYPES
            );
        }

        $postType = PostType::query()->create([
            'name' => $request->input('name'),
            'value' => $request->input('value'),
            'image' => $imageFilename,
            'status' => $request->input('status'),
        ]);

        return $this->success(['post_type' => $postType], 'Yük tipi eklendi.');
    }

    public function updatePostType(Request $request, int $postType)
    {
        if (! $request->user() instanceof Admin) {
            return $this->error([], 'Yetkisiz', [], 401);
        }

        $target = PostType::query()->find($postType);
        if (! $target) {
            return $this->error([], 'Yük tipi bulunamadı.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'value' => 'required|string|max:255',
            'status' => 'required|string|in:active,inactive',
            'image' => 'nullable|image|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors(), 422);
        }

        $imageFilename = $target->image;
        if ($request->hasFile('image')) {
            CatalogAssetStorage::deleteFile(CatalogAssetUrl::FOLDER_POST_TYPES, $target->image);
            $imageFilename = CatalogAssetStorage::storeUploadedFile(
                $request->file('image'),
                CatalogAssetUrl::FOLDER_POST_TYPES
            );
        }

        $target->update([
            'name' => $request->input('name'),
            'value' => $request->input('value'),
            'image' => $imageFilename,
            'status' => $request->input('status'),
        ]);

        return $this->success(['post_type' => $target->fresh()], 'Yük tipi güncellendi.');
    }

    private function mapCarDetail(Request $request, CarDetail $detail): array
    {
        return [
            'id' => $detail->id,
            'car_id' => $detail->car_id,
            'name' => $detail->name,
            'value' => $detail->value,
            'image' => $detail->image,
            'image_url' => CatalogAssetUrl::vehicle($request, $detail->image),
        ];
    }

    private function syncCarDetails(Request $request, Car $car): void
    {
        $details = $request->input('details', []);
        if (! is_array($details)) {
            return;
        }

        foreach ($details as $index => $detailData) {
            if (! is_array($detailData)) {
                continue;
            }

            $detailId = ! empty($detailData['id']) ? (int) $detailData['id'] : null;
            $existing = $detailId
                ? CarDetail::query()->where('car_id', $car->id)->find($detailId)
                : null;

            if (! empty($detailData['_delete'])) {
                if ($existing) {
                    CatalogAssetStorage::deleteFile(CatalogAssetUrl::FOLDER_VEHICLES, $existing->image);
                    $existing->delete();
                }

                continue;
            }

            $name = trim((string) ($detailData['name'] ?? ''));
            $value = trim((string) ($detailData['value'] ?? ''));
            if ($name === '' || $value === '') {
                continue;
            }

            $imageFilename = $existing?->image;
            $imageKey = 'details.'.$index.'.image';
            if ($request->hasFile($imageKey)) {
                CatalogAssetStorage::deleteFile(CatalogAssetUrl::FOLDER_VEHICLES, $imageFilename);
                $imageFilename = CatalogAssetStorage::storeUploadedFile(
                    $request->file($imageKey),
                    CatalogAssetUrl::FOLDER_VEHICLES
                );
            }

            if ($existing) {
                $existing->update([
                    'name' => $name,
                    'value' => $value,
                    'image' => $imageFilename,
                ]);

                continue;
            }

            CarDetail::query()->create([
                'car_id' => $car->id,
                'name' => $name,
                'value' => $value,
                'image' => $imageFilename,
            ]);
        }
    }

    private function resolveShipmentReferences(int $createrId, int $carId, int $postTypeId): array
    {
        $creater = User::query()->find($createrId);
        if (! $creater) {
            return ['error' => 'Seçilen kullanıcı bulunamadı.'];
        }

        if ((int) $creater->with_admin !== 1) {
            return ['error' => 'Seçilen kullanıcı admin panelinden eklenmiş bir kullanıcı değil.'];
        }

        $car = Car::query()->where('id', $carId)->where('status', 'active')->first();
        if (! $car) {
            return ['error' => 'Seçilen araç bulunamadı veya aktif değil.'];
        }

        $postType = PostType::query()
            ->where('id', $postTypeId)
            ->where('status', 'active')
            ->first();
        if (! $postType) {
            return ['error' => 'Seçilen yük tipi bulunamadı veya aktif değil.'];
        }

        return [
            'creater_id' => $creater->id,
            'car_id' => $car->id,
            'post_type_id' => $postType->id,
        ];
    }

    private function normalizeTime(?string $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        if (strlen($time) === 5) {
            return $time.':00';
        }

        return $time;
    }

    private function normalizePhoneInput(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }
}
