<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Events\RequestSent;
use App\Mail\OfferSentMail;
use App\Models\Car;
use App\Models\Message;
use App\Models\PostType;
use App\Models\Requests;
use App\Models\SearchAnalysis;
use App\Models\Shipment;
use App\Models\ShipmentCallRequest;
use App\Models\ShipmentCar;
use App\Models\ShipmentOrder;
use App\Models\User;
use App\Models\UserCar;
use App\Services\ExpoPushService;
use App\Services\JobStartService;
use App\Services\LoadAlarmPushService;
use App\Services\RouteCitiesService;
use App\Services\SlackService;
use App\Support\CatalogAssetUrl;
use App\Support\CityNameNormalizer;
use App\Support\PublicStorageUrl;
use App\Traits\ResponseTrait;
use Carbon\Carbon;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ShipmentController extends Controller
{
    use ResponseTrait;

    private function sendOfferMails(Shipment $shipment, Requests $offerRequest): void
    {
        $owner = $shipment->creator;
        $vehicleOwner = $offerRequest->user;

        // E-posta bilgilendirme amaçlı: gönderilemezse teklif yine kaydedilmeli
        try {
            if ($owner?->email) {
                Mail::to($owner->email)->send(new OfferSentMail($shipment, $offerRequest, 'cargo_owner'));
            }

            if ($vehicleOwner?->email) {
                Mail::to($vehicleOwner->email)->send(new OfferSentMail($shipment, $offerRequest, 'vehicle_owner'));
            }
        } catch (\Throwable $e) {
            Log::warning('shipment.offer_mail.failed', [
                'shipment_id' => $shipment->id,
                'request_id' => $offerRequest->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function shipmentRouteLabel(Shipment $shipment): string
    {
        $from = trim((string) $shipment->f_where_city);
        $to = trim((string) $shipment->t_where_city);

        if ($from !== '' && $to !== '') {
            return "{$from} -> {$to}";
        }

        return 'İlanınız';
    }

    private function countShipmentOffers(int $shipmentId): int
    {
        return Requests::where('shipment_id', $shipmentId)->count();
    }

    /**
     * Yük sahibine teklif push metni.
     * Yeni teklif: yalnızca 1. ve 3. teklifte; güncelleme: her zaman.
     *
     * @return array{title: string, body: string, event: string}|null
     */
    private function resolveOfferPushToCargoOwnerMessage(Shipment $shipment, bool $isUpdate): ?array
    {
        $route = $this->shipmentRouteLabel($shipment);
        $offerCount = $this->countShipmentOffers($shipment->id);

        if ($isUpdate) {
            return [
                'title' => 'Teklif güncellendi',
                'body' => "{$route} giden yükünüz için teklif güncellendi.",
                'event' => 'offer_updated',
            ];
        }

        if ($offerCount === 1) {
            return [
                'title' => 'Yeni teklif',
                'body' => "{$route} giden yükünüz için teklif geldi.",
                'event' => 'offer_first',
            ];
        }

        if ($offerCount === 3) {
            return [
                'title' => 'Talep artıyor',
                'body' => "{$route} giden ilanınıza talep artıyor.",
                'event' => 'offer_demand_rising',
            ];
        }

        return null;
    }

    /**
     * Teklif push’u — alıcı: ilan sahibi (yük sahibi).
     */
    private function sendOfferPushToCargoOwner(Shipment $shipment, Requests $offerRequest, bool $isUpdate): void
    {
        $owner = $shipment->creator;
        $bidder = $offerRequest->user;

        if (! $owner || ! $bidder) {
            Log::warning('push.offer.skipped', [
                'reason' => 'missing_owner_or_bidder',
                'shipment_id' => $shipment->id,
                'slug' => $shipment->slug,
            ]);

            return;
        }

        if ((int) $owner->id === (int) $bidder->id) {
            Log::info('push.offer.skipped', [
                'reason' => 'owner_is_bidder_same_user',
                'shipment_id' => $shipment->id,
                'slug' => $shipment->slug,
            ]);

            return;
        }

        $owner->loadMissing('pushDevice');

        $message = $this->resolveOfferPushToCargoOwnerMessage($shipment, $isUpdate);
        if ($message === null) {
            Log::info('push.offer.skipped', [
                'reason' => 'no_message_for_offer_count',
                'shipment_id' => $shipment->id,
                'offer_count' => $this->countShipmentOffers($shipment->id),
                'is_update' => $isUpdate,
            ]);

            return;
        }

        $this->dispatchPushToUser(
            $owner,
            $message['title'],
            $message['body'],
            [
                'type' => 'offer_received',
                'shipment_id' => (string) $shipment->id,
                'slug' => (string) $shipment->slug,
            ],
            [
                'event' => $message['event'],
                'shipment_id' => $shipment->id,
                'slug' => $shipment->slug,
                'cargo_owner_id' => $owner->id,
                'vehicle_owner_id' => $bidder->id,
                'request_id' => $offerRequest->id,
                'offer_count' => $this->countShipmentOffers($shipment->id),
            ],
            'push.offer'
        );
    }

    /**
     * Teklif kabul — yük sahibi ve araç sahibine ayrı bildirim.
     */
    private function sendOfferAcceptedPushes(Shipment $shipment, Requests $offerRequest): void
    {
        $route = $this->shipmentRouteLabel($shipment);
        $cargoOwner = $shipment->creator;
        $vehicleOwner = $offerRequest->user;

        if ($vehicleOwner) {
            $vehicleOwner->loadMissing('pushDevice');
            $this->dispatchPushToUser(
                $vehicleOwner,
                'Teklifiniz onaylandı',
                "{$route} ilanına olan teklifiniz onaylandı. İşi alıp devam etmek için lütfen tıklayınız.",
                [
                    // Mobil: dokununca aktif iş ekranı (İşi Başlat penceresi)
                    'type' => 'job_assigned',
                    'shipment_id' => (string) $shipment->id,
                    'slug' => (string) $shipment->slug,
                ],
                [
                    'event' => 'offer_accepted_vehicle_owner',
                    'shipment_id' => $shipment->id,
                    'slug' => $shipment->slug,
                    'vehicle_owner_id' => $vehicleOwner->id,
                    'cargo_owner_id' => $shipment->creater_id,
                ],
                'push.offer_accepted.vehicle_owner'
            );
        }

        if ($cargoOwner) {
            $cargoOwner->loadMissing('pushDevice');
            $this->dispatchPushToUser(
                $cargoOwner,
                'Teklif kabul edildi',
                'Teklif başarılı bir şekilde kabul edildi.',
                [
                    'type' => 'offer_accepted',
                    'shipment_id' => (string) $shipment->id,
                    'slug' => (string) $shipment->slug,
                ],
                [
                    'event' => 'offer_accepted_cargo_owner',
                    'shipment_id' => $shipment->id,
                    'slug' => $shipment->slug,
                    'cargo_owner_id' => $cargoOwner->id,
                    'vehicle_owner_id' => $offerRequest->user_id,
                ],
                'push.offer_accepted.cargo_owner'
            );
        }
    }

    /**
     * Pusher: offer.accepted — OfferAccepted.php canlıda yoksa require_once + yedek AnonymousEvent.
     */
    private function broadcastOfferAccepted(Shipment $shipment, Requests $req): void
    {
        $eventPath = app_path('Events/OfferAccepted.php');
        if (is_file($eventPath) && ! class_exists(\App\Events\OfferAccepted::class, false)) {
            require_once $eventPath;
        }

        $buyerId = (int) $req->user_id;
        $createrId = (int) $shipment->creater_id;
        $payload = [
            'shipment_id' => (int) $shipment->id,
            'shipment_slug' => (string) $shipment->slug,
            'request_id' => (int) $req->id,
            'buyer_user_id' => $buyerId,
            'creater_id' => $createrId,
            'status' => (string) $shipment->status,
        ];

        try {
            if (class_exists(\App\Events\OfferAccepted::class, false)) {
                broadcast(new \App\Events\OfferAccepted($shipment, $req));

                return;
            }
        } catch (\Throwable $e) {
            Log::warning('offer.accepted.event_broadcast_failed', [
                'error' => $e->getMessage(),
                'event_file' => $eventPath,
                'event_file_exists' => is_file($eventPath),
            ]);
        }

        Broadcast::on([
            new PrivateChannel('user.'.$buyerId),
            new PrivateChannel('user.'.$createrId),
        ])
            ->as('offer.accepted')
            ->with($payload)
            ->sendNow();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $logContext
     */
    private function dispatchPushToUser(
        User $user,
        string $title,
        string $body,
        array $data,
        array $logContext,
        string $logPrefix,
    ): void {
        Log::info("{$logPrefix}.start", array_merge($logContext, [
            'user_id' => $user->id,
            'has_token' => $user->pushDevice !== null,
            'platform' => $user->pushDevice?->platform,
        ]));

        try {
            $sent = app(ExpoPushService::class)->sendToUser($user, $title, $body, $data, $logContext);

            if ($sent) {
                Log::info("{$logPrefix}.success — bildirim gönderildi", array_merge($logContext, [
                    'user_id' => $user->id,
                    'title' => $title,
                    'body' => $body,
                ]));
            } else {
                $noDevice = $user->pushDevice === null;
                Log::log(
                    $noDevice ? 'warning' : 'error',
                    "{$logPrefix}.failed — bildirim gönderilemedi",
                    array_merge($logContext, [
                        'user_id' => $user->id,
                        'has_user_devices_row' => ! $noDevice,
                        'hint' => $noDevice
                            ? 'user_id için user_devices tablosunda expo_push_token yok. Yük sahibi uygulamada giriş yapıp bildirim iznini açmalı.'
                            : 'push.expo.ticket_error veya http_error loglarina bakin',
                    ])
                );
            }
        } catch (\Throwable $e) {
            Log::error("{$logPrefix}.exception", array_merge($logContext, [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]));
        }
    }

    /**
     * Bana uygun ilanlar: önce kullanıcının araçlarına uyan, sonra bulunduğu şehirdeki ilanlar.
     *
     * Araç uyumu: aynı araç tipi (car_id) ve ilan bir alt tip istiyorsa (ör. Tır / Tenteli) kullanıcının
     * aracı da tam o alt tipte olmalı. Her dorse/kasa tipi ayrı bir araçtır: Tenteli ilanı yalnızca
     * tenteli tırı olana öne çıkar; alt tipi girilmemiş araç belirli alt tip isteyen ilana uymaz.
     * İlan alt tip belirtmemişse (yalnızca "Tır") o tipteki her araç uyar.
     * Mobil `shipmentMatchesUserCar` ile aynı kural.
     */
    private function applySuitablePostingsOrdering($query, User $user): void
    {
        if ($user->type !== 'vehicle_owner' || ($user->suitable_postings ?? 'no') !== 'yes') {
            $query->orderBy('id', 'desc');

            return;
        }

        $userId = (int) $user->id;
        $query->orderByRaw(
            'CASE WHEN EXISTS (
                SELECT 1 FROM user_cars uc
                WHERE uc.user_id = ?
                AND (
                    (
                        (shipments.car_id IS NULL OR uc.car_id = shipments.car_id)
                        AND (
                            shipments.car_detail_id IS NULL
                            OR uc.car_detail_id = shipments.car_detail_id
                        )
                    )
                    OR EXISTS (
                        SELECT 1 FROM shipment_cars sc
                        WHERE sc.shipment_id = shipments.id
                        AND sc.car_id = uc.car_id
                        AND (
                            sc.car_detail_id IS NULL
                            OR uc.car_detail_id = sc.car_detail_id
                        )
                    )
                )
            ) THEN 0 ELSE 1 END',
            [$userId]
        );

        $detail = $user->userDetail()->first();
        $prioritizeCity = CityNameNormalizer::canonicalForFilter($detail?->user_city);
        if ($prioritizeCity) {
            $cityLower = mb_strtolower($prioritizeCity, 'UTF-8');
            $query->orderByRaw(
                'CASE WHEN LOWER(TRIM(f_where_city)) = ? THEN 0 ELSE 1 END',
                [$cityLower]
            );
        }

        $query->orderBy('id', 'desc');
    }

    /**
     * İlan JSON’unda `creator.profile_image` — mobilde `UserResource::image` ile aynı tam URL (çekmece / kart tutarlı).
     */
    private function decorateShipmentCreatorProfileImage(Request $request, ?Shipment $shipment): void
    {
        if (! $shipment?->relationLoaded('creator') || ! $shipment->creator) {
            return;
        }
        $creator = $shipment->creator;
        $absolute = PublicStorageUrl::absoluteForRequest($request, $creator->profile_image);
        if ($absolute !== null) {
            $creator->setAttribute('profile_image', $absolute);
        }
        // Frontend shipment.creator içinde camelCase telefon formatını bekliyor.
        $creator->setAttribute('phoneFormatted', $creator->phoneFormatted);
    }

    /**
     * Yük sahibinin şu an yolda olan işleri (taşıyıcıya verilmiş ilanlar): panel üst şeridi için.
     */
    public function activeRoutes(Request $request, RouteCitiesService $routeCities)
    {
        $user = $request->user();
        if ($user->type !== 'cargo_owner') {
            return $this->success(['routes' => []]);
        }

        $shipments = Shipment::with([
            'requests' => fn ($q) => $q->where('status', 'accepted')->with(['user', 'car']),
            'car',
            'getCarDetail',
        ])
            ->where('creater_id', $user->id)
            ->where('status', 'accepted')
            ->orderByDesc('updated_at')
            ->get();

        $orders = ShipmentOrder::whereIn('shipment_id', $shipments->pluck('id'))->get()->keyBy('shipment_id');

        $routes = $shipments->map(function (Shipment $s) use ($request, $routeCities, $orders) {
            $accepted = $s->requests->first();
            $order = $orders->get($s->id);
            $driver = $accepted?->user;
            // Taşıyıcının teklif verdiği araç; yoksa ilanın ana aracı
            $car = $accepted?->car ?? $s->car;
            $carImage = $accepted?->car ? $car?->image : ($s->getCarDetail?->image ?? $car?->image);

            $fixedPrice = (float) $s->getRawOriginal('price');
            $offerPrice = (float) ($accepted?->getRawOriginal('price') ?? 0);
            if ($fixedPrice > 0) {
                $dealType = 'fixed';
                $amount = $fixedPrice;
            } elseif ($offerPrice > 0) {
                $dealType = 'offer';
                $amount = $offerPrice;
            } else {
                $dealType = 'negotiated';
                $amount = null;
            }

            return [
                'shipment_id' => (int) $s->id,
                'slug' => $s->slug,
                // Araç sahibi işi başlattıysa konumuna göre canlı ilerleme (0–100); başlamadıysa 0
                'started_at' => $order?->started_at?->toIso8601String(),
                'progress' => $order?->progress !== null ? (float) $order->progress : 0.0,
                'current_city' => $order?->current_city,
                // İlerleme çubuğunu bölen ara iller (rota sırasıyla, başlangıç / bitiş hariç)
                'stops' => $routeCities->citiesBetween($s->f_where_city, $s->t_where_city),
                'from' => implode(' / ', array_filter([$s->f_where_city, $s->f_where_district])),
                'to' => implode(' / ', array_filter([$s->t_where_city, $s->t_where_district])),
                'deal_type' => $dealType,
                'amount' => $amount,
                'car' => $car ? [
                    'name' => $car->name,
                    'detail' => $accepted?->car ? null : $s->getCarDetail?->value,
                    'image' => CatalogAssetUrl::vehicle($request, $carImage),
                ] : null,
                'driver' => $driver ? [
                    'name' => trim((string) $driver->full_name) ?: 'Taşıyıcı',
                    'image' => PublicStorageUrl::absoluteForRequest($request, $driver->profile_image),
                ] : null,
            ];
        })->values()->all();

        // Güzergah karuselinin altındaki kısayol kartları
        $comments = $user->comments();
        $stats = [
            'active_jobs' => count($routes),
            'active_listings' => Shipment::where('creater_id', $user->id)->where('status', 'active')->count(),
            'reviews_count' => (clone $comments)->count(),
            'reviews_avg' => round((float) ((clone $comments)->avg('score') ?? 0), 1),
        ];

        return $this->success(['routes' => $routes, 'stats' => $stats]);
    }

    public function getAll(Request $request)
    {
        $attributes = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1',
            'offset' => 'nullable|integer|min:0',
            'type' => 'nullable|string|in:cargo_owner,vehicle_owner,all|default:all',
            'f_where_city' => 'nullable|string',
            'f_where_district' => 'nullable|string',
            't_where_city' => 'nullable|string',
            't_where_district' => 'nullable|string',
        ]);
        if ($attributes->fails()) {
            return $this->error([], 'Validation hatası', $attributes->errors(), 422);
        }

        $user = $request->user();
        if (!$user) {
            return $this->error([], 'Unauthorized', [], 401);
        }

        $fWhereCity = $request->f_where_city
            ? CityNameNormalizer::canonicalForFilter($request->f_where_city)
            : null;
        $fWhereDistrict = $request->f_where_district;

        $shipments = Shipment::with([
            'creator' => function ($query) use ($request) {
                if ($request->type && $request->type !== 'all') {
                    $query->where('type', $request->type);
                }
                $query->with('comments')->withAvg('comments', 'score');
            },
        ])
            ->when($fWhereCity, fn ($q) => $q->where('f_where_city', $fWhereCity))
            ->when($fWhereDistrict && $fWhereDistrict !== 'Her yer', fn ($q) => $q->where('f_where_district', $fWhereDistrict))
            ->when($request->t_where_city, fn ($q) => $q->where('t_where_city', $request->t_where_city))
            ->when($request->t_where_district && $request->t_where_district !== 'Her yer', fn ($q) => $q->where('t_where_district', $request->t_where_district))
            ->when($request->filters, function ($query) use ($request) {
                $filters = $request->filters ?? [];
                if (isset($filters['order']) && $filters['order'] === 'earliest') {
                    $query->orderBy('departure_time', 'asc');
                }
                if (isset($filters['verified']) && $filters['verified'] === 'verified') {
                    $query->whereHas('creator', fn ($q) => $q->where('verified', 1));
                }
                if (isset($filters['departureTime']) && $filters['departureTime'] === 'morning') {
                    $query->where(function ($q) {
                        $q->whereTime('departure_time', '>=', '06:00:00')
                            ->whereTime('departure_time', '<', '12:00:00');
                    });
                }
                if (isset($filters['departureTime']) && $filters['departureTime'] === 'afternoon') {
                    $query->where(function ($q) {
                        $q->whereTime('departure_time', '>=', '12:00:00')
                            ->whereTime('departure_time', '<', '18:00:00');
                    });
                }
                if (isset($filters['departureTime']) && $filters['departureTime'] === 'evening') {
                    $query->where(function ($q) {
                        $q->whereTime('departure_time', '>=', '18:00:00')
                            ->orWhereTime('departure_time', '<', '06:00:00');
                    });
                }
            })
            ->with('car.details', 'requests', 'getCarDetail', 'postType', 'shipmentCars.car', 'shipmentCars.carDetail')
            ->whereHas('creator', fn ($q) => $q->where('payment_confirm', 1))
            ->where('status', 'active');

        $this->applySuitablePostingsOrdering($shipments, $user);

        $ctrl = $this;
        $transform = function ($shipment) use ($request, $ctrl) {
            $shipment->time_arrival = $shipment->time_arrival ? Carbon::parse($shipment->time_arrival)->format('H:i') : null;
            $shipment->departure_time = $shipment->departure_time ? Carbon::parse($shipment->departure_time)->format('H:i') : null;
            $ctrl->decorateShipmentCreatorProfileImage($request, $shipment);

            return $shipment;
        };

        if ($request->has('offset')) {
            $total = (clone $shipments)->count();
            $data = $shipments->skip((int) $request->offset)->take($request->limit ?? 20)->get();
            $data->each($transform);
            [$my_post, $other_post] = $data->partition(fn ($s) => (int) $s->creater_id === (int) $user->id);

            return $this->success([
                'my_post' => $my_post->values()->all(),
                'other_post' => $other_post->values()->all(),
                'total' => $total,
            ]);
        }

        $total = (clone $shipments)->count();
        $limit = max(1, min((int) ($request->limit ?? 20), 100));
        $uid = (int) $user->id;

        /** Önce kendi ilanları (aynı filtreler), sonra kalan kotayı başkalarına — aksi halde limit son 40 kayıtta kullanıcı ilanı yoksa my_post boş kalıyordu. */
        $my_post = (clone $shipments)->where('creater_id', $uid)->limit($limit)->get();
        $my_post->each($transform);

        $remaining = max(0, $limit - $my_post->count());
        $other_post = $remaining > 0
            ? (clone $shipments)->where('creater_id', '!=', $uid)->limit($remaining)->get()
            : collect();
        $other_post->each($transform);

        if ($request->f_where_city || $request->f_where_district || $request->t_where_city || $request->t_where_district) {
            $this->logSearchAnalysis($request, (int) $user->id, $total);
        }
   
        return $this->success([
            'my_post' => $my_post->values()->all(),
            'other_post' => $other_post->values()->all(),
            'total' => $total,
        ]);
    }

    private function logSearchAnalysis(Request $request, int $userId, int $count): void
    {
        if ($request->has('offset')) {
            return;
        }

        try {
            SearchAnalysis::create([
                'calling_user_id' => $userId,
                'f_where_city' => $this->nullableSearchField($request->input('f_where_city')),
                'f_where_district' => $this->nullableSearchField($request->input('f_where_district')),
                't_where_city' => $this->nullableSearchField($request->input('t_where_city')),
                't_where_district' => $this->nullableSearchField($request->input('t_where_district')),
                'count' => $count,
            ]);
        } catch (\Throwable $e) {
            Log::warning('search_analysis kaydı oluşturulamadı', [
                'user_id' => $userId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function nullableSearchField(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '' || $normalized === 'Her yer') {
            return null;
        }

        return $normalized;
    }

    public function getByCreatorId(Request $request, $creatorId)
    {
        $attributes = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1',
            'type' => 'nullable|string|in:cargo_owner,vehicle_owner,all',
            'status' => 'nullable|string|in:active,inactive,pending,accepted,done,canceled',
        ]);
        if ($attributes->fails()) {
            return $this->error([], 'Validation hatası', $attributes->errors(), 422);
        }
        $query = Shipment::query()
            ->where('creater_id', $creatorId)
            ->with([
                'car.details',
                'requests',
                'postType',
                'getCarDetail',
                'shipmentCars.car',
                'shipmentCars.carDetail',
                'creator' => function ($q) use ($request) {
                    if ($request->filled('type') && in_array($request->type, ['cargo_owner', 'vehicle_owner'], true)) {
                        $q->where('type', $request->type);
                    }
                },
            ]);

        if ($request->filled('status') && $request->status === 'done') {
            $query->with([
                'attachments' => fn ($q) => $q
                    ->select('id', 'shipment_id', 'title', 'created_at')
                    ->orderByDesc('created_at'),
            ]);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $shipments = $query->orderBy('created_at', 'desc')
            ->paginate($request->limit ?? 20);

        $ctrl = $this;
        $shipments->getCollection()->transform(function ($shipment) use ($request, $ctrl) {
            $shipment->time_arrival = $shipment->time_arrival ? Carbon::parse($shipment->time_arrival)->format('H:i') : null;
            $shipment->departure_time = $shipment->departure_time ? Carbon::parse($shipment->departure_time)->format('H:i') : null;
            $ctrl->decorateShipmentCreatorProfileImage($request, $shipment);

            return $shipment;
        });

        $tabStatuses = ['active', 'accepted', 'done', 'canceled'];
        $statusCounts = array_fill_keys($tabStatuses, 0);
        $countRows = Shipment::query()
            ->where('creater_id', $creatorId)
            ->whereIn('status', $tabStatuses)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');
        foreach ($tabStatuses as $key) {
            $statusCounts[$key] = (int) ($countRows[$key] ?? 0);
        }

        return $this->success(array_merge($shipments->toArray(), [
            'status_counts' => $statusCounts,
        ]));
    }
    public function getPostTypes(Request $request)
    {
        $postTypes = PostType::where('status', 'active')->get();
        $postTypes->each(function (PostType $postType) use ($request) {
            $postType->setAttribute('image', CatalogAssetUrl::postType($request, $postType->image));
        });

        return $this->success(['post_types' => $postTypes]);
    }
    public function create(Request $request, SlackService $slackService)
    {
        // Gönderildiyse: kalkış tarihi bugün ile 2 ay sonrası arasında (web/mobil tarih seçicisi ile aynı; ay sonu taşmaz), saatler HH:mm.
        // (Zorunluluk web/mobil formda; eski mobil sürümler bu alanları göndermeyebildiği için burada zorunlu değil.)
        $request->validate([
            'departure_date' => 'nullable|date|after_or_equal:today|before_or_equal:'.now()->addMonthsNoOverflow(2)->toDateString(),
            'shipment_date' => 'nullable|date|after_or_equal:today|before_or_equal:'.now()->addMonthsNoOverflow(2)->toDateString(),
            'departure_time' => ['nullable', 'regex:/^([01]?\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'],
            'time_arrival' => ['nullable', 'regex:/^([01]?\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'],
        ], [
            'departure_date.after_or_equal' => 'Kalkış tarihi bugünden önce olamaz.',
            'shipment_date.after_or_equal' => 'Kalkış tarihi bugünden önce olamaz.',
            'departure_date.before_or_equal' => 'Kalkış tarihi en fazla 2 ay sonrası olabilir.',
            'shipment_date.before_or_equal' => 'Kalkış tarihi en fazla 2 ay sonrası olabilir.',
            'departure_time.regex' => 'Kalkış saati geçersiz.',
            'time_arrival.regex' => 'Varış saati geçersiz.',
        ]);

        $bosaltilanYer = $request->bosaltilanYer['city'];
        $bosaltilanYerDistrict = $request->bosaltilanYer['district'];
        $yuklenecekYer = $request->yuklenecekYer['city'];
        $yuklenecekYerDistrict = $request->yuklenecekYer['district'];
        $routeInfo = $request->routeInfo;
        $hours = (is_array($routeInfo) && ! empty($routeInfo['duration']))
            ? $routeInfo['duration']
            : 'belirlenecek';
        $userId = $request->user['id'];
        $selectedCars = $this->normalizeSelectedCars($request);
        if (empty($selectedCars)) {
            return $this->error([], 'En az bir araç seçilmelidir.', [], 422);
        }
        // Birden fazla araçta ilanın ana aracı fiyatı en yüksek olan (fiyat da web/mobilde ona göre hesaplanıyor)
        $selectedCars = $this->sortCarsByRoutePriceDesc($selectedCars, $routeInfo);
        $carId = $selectedCars[0]['car_id'];
        $carDetailId = $selectedCars[0]['car_detail_id'];
        $postTypeId = $request->selectedPostType['id'];
        $priceType = (string) $request->input('selectedPriceType', 'teklif');
        $calculatedPrice = (float) $request->input('calculatedPrice', 0);
        $selectedPriceType = in_array($priceType, ['sabit', 'ozel'], true)
            ? max(0, $calculatedPrice)
            : 0;
        $departureTime = $request->input('departure_time');
        $timeArrival = $request->input('time_arrival');
        $rawDeparture = $request->input('departure_date') ?? $request->input('shipment_date');
        $departureDate = ($rawDeparture !== null && $rawDeparture !== '') ? $rawDeparture : null;
        $callVerify = $request->input('call_verify', false);
        $weight = $request->input('weight') . " " . $request->input('weight_unit');
        $explanationRaw = $request->input('explanation');
        $explanation = is_string($explanationRaw) ? trim($explanationRaw) : null;
        if ($explanation === '') {
            $explanation = null;
        }
        if ($explanation !== null && mb_strlen($explanation) > 500) {
            $explanation = mb_substr($explanation, 0, 500);
        }
        if ($departureTime && strlen($departureTime) === 5) {
            $departureTime .= ':00';
        }
        // Kalkış bugünse saati şu andan önce olamaz (web/mobil formdaki kural; 5 dk tolerans)
        if ($departureDate && $departureTime && substr((string) $departureDate, 0, 10) === now()->toDateString()) {
            try {
                $departureAt = \Illuminate\Support\Carbon::parse(now()->toDateString().' '.substr($departureTime, 0, 5));
                if ($departureAt->lt(now()->subMinutes(5))) {
                    return $this->error([], 'Kalkış saati şu anki saatten önce olamaz.', [], 422);
                }
            } catch (\Throwable) {
                // saat biçimi yukarıdaki doğrulamadan geçti; burada ayrıştırılamazsa kontrol atlanır
            }
        }
        if ($timeArrival && strlen($timeArrival) === 5) {
            $timeArrival .= ':00';
        }

        $slug = Str::random(25);
        $shipment = Shipment::create([
            'slug' => $slug,
            'call_access' => (bool) $callVerify,
            'f_where_city' => $yuklenecekYer,
            'f_where_district' => $yuklenecekYerDistrict,
            'f_where_location' => $this->sanitizeExactLocation($request->input('yuklenecekYer.exact_location')),
            't_where_city' => $bosaltilanYer,
            't_where_district' => $bosaltilanYerDistrict,
            't_where_location' => $this->sanitizeExactLocation($request->input('bosaltilanYer.exact_location')),
            'hours' => $hours,
            'creater_id' => $userId,
            'car_id' => $carId,
            'car_detail_id' => $carDetailId,
            'post_type_id' => $postTypeId,
            'price' => $selectedPriceType,
            'is_kdv' => (bool) $request->input('is_kdv', false),
            'part_load' => (bool) $request->input('part_load', false),
            'is_fast' => $this->isFastListing($departureDate, $departureTime),
            'departure_time' => $departureTime,
            'time_arrival' => $timeArrival,
            'departure_date' => $departureDate,
            'weight' => $weight,
            'explanation' => $explanation,
            'status' => 'active',
        ]);

        foreach ($selectedCars as $selected) {
            ShipmentCar::create([
                'shipment_id' => $shipment->id,
                'car_id' => $selected['car_id'],
                'car_detail_id' => $selected['car_detail_id'],
            ]);
        }

        $shipment = Shipment::with('car', 'getCarDetail', 'shipmentCars.car', 'shipmentCars.carDetail')
            ->where('id', $shipment->id)
            ->first();
        $user = User::where('id', $userId)->first();
        $carsLabel = $shipment->shipmentCars
            ->map(fn ($sc) => $sc->car?->name . ($sc->carDetail?->value ? ' - ' . $sc->carDetail->value : ''))
            ->filter()
            ->implode(', ');

        $channel = config('slack.notifications.shipments', null);
        $message = __('slack.shipment_load', [
            'full_name' => $user->first_name . " " . $user->last_name,
            'phone' => $user->phone,
            'f_where' => $yuklenecekYer . " / " . $yuklenecekYerDistrict,
            't_where' => $bosaltilanYer . " / " . $bosaltilanYerDistrict,
            'price' => $shipment->price,
            'car' => $carsLabel,
            'total_count' => Shipment::where('status', 'active')->count(),
        ]);
        $slackService->sendMessage($message, $channel, SlackService::COLOR_SUCCESS);

        // Yük alarmı kuran uygun araç sahiplerine push — yanıtı bekletmemek için yanıttan sonra
        $shipmentId = $shipment->id;
        dispatch(function () use ($shipmentId) {
            try {
                app(LoadAlarmPushService::class)->notifyForShipment($shipmentId);
            } catch (\Throwable $e) {
                Log::error('push.load_alarm.failed', ['shipment_id' => $shipmentId, 'error' => $e->getMessage()]);
            }
        })->afterResponse();

        return $this->success(['shipment' => $shipment]);
    }

    /**
     * Haritadan seçilen detaylı konumu (istemciden gelen) güvenli biçime getirir.
     * Sadece bilinen alanlar alınır, koordinat Türkiye sınırında olmalı; değilse null (ilan yine oluşur).
     * Eski istemciler / Adreslerim'den seçimde bu alan gelmez → null.
     */
    private function sanitizeExactLocation(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }
        $lat = filter_var($raw['lat'] ?? null, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($raw['lng'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lng === false || $lat < 35.5 || $lat > 42.5 || $lng < 25.5 || $lng > 45.0) {
            return null;
        }

        $text = function (mixed $value, int $max): ?string {
            if (! is_string($value) && ! is_numeric($value)) {
                return null;
            }
            $value = trim(strip_tags((string) $value));

            return $value === '' ? null : mb_substr($value, 0, $max);
        };

        $address = is_array($raw['address'] ?? null) ? $raw['address'] : [];

        return [
            'lat' => round($lat, 7),
            'lng' => round($lng, 7),
            'display_name' => $text($raw['display_name'] ?? null, 500),
            'address' => array_filter([
                'province' => $text($address['province'] ?? null, 100),
                'district' => $text($address['district'] ?? null, 100),
                'neighbourhood' => $text($address['neighbourhood'] ?? null, 150),
                'road' => $text($address['road'] ?? null, 200),
                'house_number' => $text($address['house_number'] ?? null, 30),
                'postcode' => $text($address['postcode'] ?? null, 10),
            ], fn ($v) => $v !== null),
        ];
    }

    /**
     * Onaylanmış işin aracı (araç sahibinin `user_cars` kaydı, car + carDetail ile).
     * Sıra aktif iş ekranı ile aynı: işe seçilen araç → teklifteki araç → teklifteki araç türündeki ilk araç.
     */
    private function jobVehicleForOwner(Shipment $shipment, ShipmentOrder $order): ?array
    {
        $accepted = $shipment->requests->first(fn ($r) => strtolower((string) $r->status) === 'accepted');
        $id = $order->user_car_id ?: $accepted?->user_car_id;
        if (! $id && $accepted && (float) $accepted->getRawOriginal('price') > 0 && $accepted->car_type) {
            $id = UserCar::where('user_id', $order->buyer_user)->where('car_id', $accepted->car_type)->orderBy('id')->value('id');
        }
        if (! $id) {
            return null;
        }
        $car = UserCar::with('car', 'carDetail')->find($id);
        if (! $car) {
            return null;
        }

        // Kasası açık görsel: `public/assets/vehicles/opened_images` içinde aynı adla varsa (ör. tır_tenteli.png)
        $openedImage = \App\Support\CatalogAssetUrl::openedVehicle(request(), $car->carDetail?->image ?: $car->car?->image);

        return $car->only(['id', 'car_id', 'car_detail_id', 'plate', 'capacity_kg', 'volume_m3']) + [
            'car' => $car->car?->only(['id', 'name', 'image']),
            'car_detail' => $car->carDetail?->only(['id', 'name', 'value', 'image']),
            'opened_image' => $openedImage,
        ];
    }

    /**
     * Fast İlan: kalkış bugün ve şu andan itibaren en geç 5 saat içinde (uygulama saat dilimi: Europe/Istanbul).
     * Kalkış saati geçmişse ya da 5 saatten sonraysa normal ilandır.
     */
    private function isFastListing(?string $departureDate, ?string $departureTime): bool
    {
        if (! $departureDate || ! $departureTime) {
            return false;
        }

        try {
            $departure = \Illuminate\Support\Carbon::parse(substr($departureDate, 0, 10).' '.substr($departureTime, 0, 5));
        } catch (\Throwable) {
            return false;
        }

        $now = now();

        return $departure->isSameDay($now)
            && $departure->greaterThanOrEqualTo($now->copy()->subMinutes(5))
            && $departure->lessThanOrEqualTo($now->copy()->addHours(5));
    }

    /**
     * Seçilen araçları rota fiyatına göre pahalıdan ucuza sıralar; ilk eleman ilanın ana aracı olur.
     * Fiyat, web `CreatePostModal` ve mobil `priceForShipmentApi` ile aynı: mesafe ve km başı ücret
     * biliniyorsa `cost_per_km × mesafe`, yoksa aracın taban `price`'ı. Eşit fiyatta seçim sırası korunur.
     *
     * @param  array<int, array{car_id: int, car_detail_id: ?int}>  $selectedCars
     */
    private function sortCarsByRoutePriceDesc(array $selectedCars, mixed $routeInfo): array
    {
        if (count($selectedCars) < 2) {
            return $selectedCars;
        }

        $distance = is_array($routeInfo) ? (float) str_replace(',', '.', (string) ($routeInfo['distance'] ?? 0)) : 0.0;
        $cars = Car::whereIn('id', array_column($selectedCars, 'car_id'))->get(['id', 'price', 'cost_per_km'])->keyBy('id');

        $priceOf = function (array $item) use ($cars, $distance): float {
            $car = $cars->get($item['car_id']);
            if (! $car) {
                return 0.0;
            }
            if ($distance > 0 && (float) $car->cost_per_km > 0) {
                return (float) $car->cost_per_km * $distance;
            }

            return (float) $car->price;
        };

        // usort kararlı (PHP 8+): eşit fiyatlarda kullanıcının seçim sırası kalır
        usort($selectedCars, fn (array $a, array $b) => $priceOf($b) <=> $priceOf($a));

        return array_values($selectedCars);
    }

    /**
     * İlan için seçilen araçlar: [{ car_id, car_detail_id }, ...]
     * Yeni istemciler `selectedCars` gönderir; eski istemciler (mobil) tek `selectedCar` + `selectedDetailValues`.
     */
    private function normalizeSelectedCars(Request $request): array
    {
        $firstDetailId = function ($raw): ?int {
            $first = trim(explode(',', (string) ($raw ?? ''))[0]);

            return is_numeric($first) ? (int) $first : null;
        };

        $items = [];
        $selectedCars = $request->input('selectedCars');
        if (is_array($selectedCars) && ! empty($selectedCars)) {
            foreach ($selectedCars as $item) {
                $items[] = [
                    'car_id' => (int) ($item['id'] ?? 0),
                    'car_detail_id' => $firstDetailId($item['detail_id'] ?? null),
                ];
            }
        } elseif (! empty($request->selectedCar['id'])) {
            $items[] = [
                'car_id' => (int) $request->selectedCar['id'],
                'car_detail_id' => $firstDetailId($request->selectedDetailValues['id'] ?? null),
            ];
        }

        $validIds = Car::whereIn('id', array_column($items, 'car_id'))->pluck('id')->all();

        return collect($items)
            ->filter(fn ($i) => in_array($i['car_id'], $validIds, true))
            ->unique('car_id')
            ->values()
            ->all();
    }
    public function getBySlug(Request $request, $slug)
    {
        $shipment = Shipment::with([
            'creator' => fn ($q) => $q->withAvg('comments', 'score'),
            'car',
            'getCarDetail',
            'postType',
            'shipmentCars.car',
            'shipmentCars.carDetail',
        ])
            ->where('slug', $slug)
            ->first();

        if (!$shipment) {
            return $this->error([], 'İlan bulunamadı', [], 404);
        }

        $this->decorateShipmentCreatorProfileImage($request, $shipment);

        return $this->success(['shipment' => $shipment]);
    }
    public function intercityShipments(Request $request)
    {
        $attributes = Validator::make($request->all(), [
            'cities' => 'required|array',
            'cities.*' => 'string|max:255',
        ]);
        if ($attributes->fails()) {
            return $this->error([], 'Validation hatası', $attributes->errors(), 422);
        }

        $cities = array_map('trim', array_filter($request->cities));
        if (empty($cities)) {
            return $this->success(['shipments' => []]);
        }

        // f_where_city ve t_where_city ikisi de gelen şehirler listesinde olan ilanlar (aralarındaki rota)
        $shipments = Shipment::with(['car.details', 'getCarDetail'])
            ->whereIn('f_where_city', $cities)
            ->whereIn('t_where_city', $cities)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->where('status', 'active')
            ->get();

        $list = $shipments->map(function ($s) {
            $from = implode(' / ', array_filter([$s->f_where_city, $s->f_where_district]));
            $to = implode(' / ', array_filter([$s->t_where_city, $s->t_where_district]));
            $time = trim(($s->departure_time ?? '') . ' – ' . ($s->time_arrival ?? ''));
            if ($time === '–') {
                $time = 'Belirtilmemiş';
            }
            $capacity = $s->getCarDetail?->value ?? $s->car?->details?->firstWhere('type', 'capacity')?->value ?? null;
            $loadAmount = $capacity ? (is_numeric($capacity) ? $capacity . ' Ton' : $capacity) : '—';
            $postType = $s->postType?->value ?? null;
            $vehicle = $s->car?->name ?? null;
            return [
                'slug' => $s->slug,
                'from' => $from ?: '—',
                'to' => $to ?: '—',
                'time' => $time,
                'loadAmount' => $loadAmount,
                'price' => $s->price ?: 'Fiyat görüşülecektir',
                'postType' => $postType,
                'vehicle' => $vehicle,
            ];
        })->values()->all();

        return $this->success(['shipments' => $list]);
    }
    public function getBySlugWithRequests(Request $request, string $slug)
    {
        $shipment = Shipment::with(['requests', 'requests.user.comments', 'requests.car', 'creator', 'car', 'getCarDetail', 'postType', 'shipmentCars.car', 'shipmentCars.carDetail'])
            ->where('slug', $slug)
            ->first();

        if (! $shipment) {
            return $this->error([], 'İlan bulunamadı', [], 404);
        }

        $shipment->requests->each(function ($reqRow) {
            $reqRow->user->comments_avg_score = $reqRow->user->comments->avg('score');
            $reqRow->created_at_raw = $reqRow->getRawOriginal('created_at');
        });

        $shipment->setRelation(
            'requests',
            $shipment->requests
                ->sort(function ($a, $b) {
                    $acceptedOrder = fn ($row) => strtolower((string) $row->status) === 'accepted' ? 0 : 1;
                    $acceptedCmp = $acceptedOrder($a) <=> $acceptedOrder($b);
                    if ($acceptedCmp !== 0) {
                        return $acceptedCmp;
                    }

                    $verifiedOrder = fn ($row) => (int) ($row->user->verified ?? 0) === 1 ? 0 : 1;
                    $verifiedCmp = $verifiedOrder($a) <=> $verifiedOrder($b);
                    if ($verifiedCmp !== 0) {
                        return $verifiedCmp;
                    }

                    $timeA = $a->created_at_raw ?? $a->getRawOriginal('created_at') ?? '';
                    $timeB = $b->created_at_raw ?? $b->getRawOriginal('created_at') ?? '';

                    return strcmp((string) $timeB, (string) $timeA);
                })
                ->values()
        );

        $this->decorateShipmentCreatorProfileImage($request, $shipment);

        // Onaylanmış işte aracın canlı konumu: yalnızca ilan sahibine (yük sahibi). Anlık güncellemeler
        // `job.progress` socket olayıyla gelir; bu ilk değerdir.
        $viewerId = (int) ($request->user()?->id ?? 0);
        $liveLocation = null;
        if ($viewerId === (int) $shipment->creater_id && in_array((string) $shipment->status, ['accepted', 'done'], true)) {
            $order = ShipmentOrder::where('shipment_id', $shipment->id)->first();
            if ($order) {
                $liveLocation = [
                    'started_at' => $order->started_at?->toIso8601String(),
                    'delivered' => (string) $shipment->status === 'done',
                    'lat' => $order->last_lat !== null ? (float) $order->last_lat : null,
                    'lng' => $order->last_lng !== null ? (float) $order->last_lng : null,
                    'at' => $order->progress_updated_at?->toIso8601String(),
                    'current_city' => $order->current_city,
                    'progress' => $order->progress !== null ? (float) $order->progress : null,
                    // Yükü taşıyacak araç (plaka, kapasite, görsel) — yük sahibi aracı ve yükünün kapladığı yeri görür
                    'vehicle' => $this->jobVehicleForOwner($shipment, $order),
                ];
            }
        }
        $shipment->setAttribute('live_location', $liveLocation);

        return $this->success(['shipment' => $shipment]);
    }

    public function cancel(Request $request, int $id)
    {
        $user = $request->user();
        if (!$user) {
            return $this->error([], 'Unauthorized', [], 401);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors(), 422);
        }

        $shipment = Shipment::find($id);
        if (!$shipment) {
            return $this->error([], 'İlan bulunamadı', [], 404);
        }

        if ((int) $shipment->creater_id !== (int) $user->id) {
            return $this->error([], 'Bu ilanı iptal etme yetkiniz yok.', [], 403);
        }

        $shipment->status = 'canceled';
        $shipment->save();

        $reason = $request->input('reason') ?? '';
        Log::info('İlan iptal edildi: ' . ' - ' . $shipment->id . ' - ' . $user->id, [
            'shipment_id' => $shipment->id,
            'user_id' => $user->id,
            'reason' => $reason,
        ]);

        return $this->success([
            'shipment' => $shipment,
        ], 'İlan iptal edildi.');
    }

    public function storeRequest(Request $request, string $slug)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'nullable|string|max:5000',
            'car_type' => 'required|integer|exists:cars,id',
            // Teklifte seçilen kendi aracı (kabul edilince iş bu araca bağlanır)
            'user_car_id' => 'nullable|integer',
            'price' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors(), 422);
        }

        $shipment = Shipment::with(['creator.pushDevice'])->where('slug', $slug)->first();
        if (!$shipment) {
            return $this->error([], 'İlan bulunamadı.', [], 404);
        }

        $user = $request->user();
        if (!$user) {
            return $this->error([], 'Unauthorized', [], 401);
        }

        // Yalnızca kullanıcının kendi aracı kabul edilir
        $userCarId = $request->filled('user_car_id')
            ? UserCar::where('user_id', $user->id)->where('id', (int) $request->user_car_id)->value('id')
            : null;

        $existingRequest = Requests::where('shipment_id', $shipment->id)->where('user_id', $user->id)->first();
        if ($existingRequest) {
            $existingRequest->update(array_filter([
                'price' => $request->price,
                'message' => $request->message ?: null,
                'car_type' => (int) $request->car_type,
                'user_car_id' => $userCarId,
            ], fn ($v, $k) => $k !== 'user_car_id' || $v !== null, ARRAY_FILTER_USE_BOTH));

            $existingRequest->load('user');
            $this->sendOfferMails($shipment, $existingRequest);
            $this->sendOfferPushToCargoOwner($shipment, $existingRequest, true);

            broadcast(new RequestSent($existingRequest->fresh()))->toOthers();
            $this->createOfferRequestMessage($shipment, $user, $existingRequest->fresh(['car']), true);

            return $this->success(['request' => $existingRequest], 'Teklif güncellendi.', 200);
        }

        $req = Requests::create([
            'shipment_id' => $shipment->id,
            'user_id' => $user->id,
            'car_type' => (int) $request->car_type,
            'user_car_id' => $userCarId,
            'price' => (float) $request->price,
            'message' => $request->message ?: null,
        ]);

        $req->load('user');
        $this->sendOfferMails($shipment, $req);
        $this->sendOfferPushToCargoOwner($shipment, $req, false);

        broadcast(new RequestSent($req))->toOthers();
        $this->createOfferRequestMessage($shipment, $user, $req, false);

        return $this->success(['request' => $req], 'Teklif gönderildi.', 201);
    }

    /**
     * Teklif gönderim / güncelleme — type=request, metin receiver_message / sender_message içinde.
     */
    private function createOfferRequestMessage(Shipment $shipment, User $bidder, Requests $offerRequest, bool $isUpdate): void
    {
        $name = trim((string) $bidder->full_name);
        if ($name === '') {
            $name = 'Kullanıcı';
        }

        $attrs = $offerRequest->getAttributes();
        $price = isset($attrs['price']) ? (float) $attrs['price'] : 0.0;
        $priceLabel = $price > 0
            ? number_format($price, 0, ',', '.').' ₺'
            : null;

        $receiverMessage = $isUpdate
            ? ($priceLabel !== null
                ? "{$name} kişisi size gönderdiği teklifi {$priceLabel} olarak güncelledi"
                : "{$name} kişisi size gönderdiği teklifi güncelledi")
            : ($priceLabel !== null
                ? "{$name} kişisi size {$priceLabel} teklif gönderdi"
                : "{$name} kişisi size teklif gönderdi");
        $senderMessage = $isUpdate
            ? ($priceLabel !== null
                ? "Gönderdiğiniz teklifi {$priceLabel} olarak güncellediniz"
                : 'Gönderdiğiniz teklifi güncellediniz')
            : ($priceLabel !== null
                ? "{$priceLabel} teklif gönderdiniz"
                : 'Teklif gönderdiniz');

        $note = trim((string) ($offerRequest->message ?? ''));
        $messageBody = $note !== '' ? $note : $receiverMessage;

        $msg = Message::create([
            'sender_id' => $bidder->id,
            'receiver_id' => $shipment->creater_id,
            'shipment_id' => $shipment->id,
            'message' => $messageBody,
            'sender_message' => $senderMessage,
            'receiver_message' => $receiverMessage,
            'type' => 'request',
            'status' => 'accepted',
            'is_read' => false,
        ]);

        broadcast(new MessageSent($msg));
    }

    public function acceptRequest(Request $request, string $slug, int $requestId)
    {
        $user = $request->user();
        if (!$user) {
            return $this->error([], 'Unauthorized', [], 401);
        }

        $shipment = Shipment::where('slug', $slug)->first();
        if (!$shipment) {
            return $this->error([], 'İlan bulunamadı.', [], 404);
        }

        if ((int) $shipment->creater_id !== (int) $user->id) {
            return $this->error([], 'Bu işlem sadece ilan sahibi tarafından yapılabilir.', [], 403);
        }

        $req = Requests::with('user')->where('id', $requestId)->where('shipment_id', $shipment->id)->first();
        if (!$req) {
            return $this->error([], 'Teklif bulunamadı.', [], 404);
        }

        $req->update(['status' => 'accepted']);
        $shipment->update(['status' => 'accepted']);

        Requests::where('shipment_id', $shipment->id)
            ->where('id', '!=', $requestId)
            ->update(['status' => 'rejected']);

        $order = ShipmentOrder::firstOrCreate(
            ['shipment_id' => $shipment->id],
            [
                'creater_user' => $shipment->creater_id,
                'buyer_user'   => $req->user_id,
                'shipment_id'  => $shipment->id,
                // Teklifte seçilen araç: aktif işte "aracım" doğrudan bu olur
                'user_car_id'  => $req->user_car_id,
            ]
        );
        // Araç sahibi yoldaysa (yol üstünden alınan ek yük) iş kendiliğinden başlar
        app(JobStartService::class)->autoStartIfOnTheRoad($order);

        $acceptedInfoMessage = Message::create([
            'sender_id' => $shipment->creater_id,
            'receiver_id' => $req->user_id,
            'shipment_id' => $shipment->id,
            'message' => '',
            'sender_message' => 'Teklifi kabul ettiniz.',
            'receiver_message' => 'Teklifiniz kabul edildi.',
            'type' => 'system',
            'status' => 'accepted',
            'is_read' => false,
        ]);
        broadcast(new MessageSent($acceptedInfoMessage));

        $req->refresh();
        $req->load('user');
        $shipmentFresh = $shipment->fresh(['creator']);
        $this->sendOfferAcceptedPushes($shipmentFresh, $req);
        $this->broadcastOfferAccepted($shipmentFresh, $req);

        return $this->success(['request' => $req, 'shipment' => $shipmentFresh], 'Teklif kabul edildi.');
    }

    /**
     * Araç sahibi — arama sonrası arayanlar listesine kayıt.
     * `listing_taken` yalnızca yük sahibine bildirim gönderilsin mi için kullanılır; veritabanına yazılmaz.
     */
    public function reportCallOutcome(Request $request, string $slug)
    {
        $user = $request->user();
        if (! $user) {
            return $this->error([], 'Unauthorized', [], 401);
        }

        if ($user->type !== 'vehicle_owner') {
            return $this->error([], 'Bu işlem yalnızca araç sahipleri içindir.', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'listing_taken' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Validation hatası', $validator->errors(), 422);
        }

        $listingTaken = $request->boolean('listing_taken');

        $shipment = Shipment::with(['creator.pushDevice'])->where('slug', $slug)->first();
        if (! $shipment) {
            return $this->error([], 'İlan bulunamadı.', [], 404);
        }

        if ((int) $shipment->creater_id === (int) $user->id) {
            return $this->error([], 'Kendi ilanınız için bu işlem yapılamaz.', [], 403);
        }

        if (! (bool) $shipment->call_access) {
            return $this->error([], 'Bu ilan için arama erişimi bulunmuyor.', [], 403);
        }

        $cargoOwner = $shipment->creator;
        if (! $cargoOwner) {
            return $this->error([], 'İlan sahibi bulunamadı.', [], 404);
        }

        $callRequest = ShipmentCallRequest::updateOrCreate(
            [
                'shipment_id' => $shipment->id,
                'vehicle_owner_id' => $user->id,
                'cargo_owner_id' => $cargoOwner->id,
            ],
            [
                'caller_phone' => $this->normalizePhoneDigits($user->phone),
                'called_at' => now(),
            ]
        );

        if ($listingTaken) {
            $this->sendCallListingTakenPushToCargoOwner($shipment, $user);
        }

        return $this->success([
            'call_request' => $this->formatCallRequestRow($callRequest->loadMissing('vehicleOwner')),
        ], $listingTaken ? 'Bildirim gönderildi.' : 'Kaydedildi.');
    }

    /**
     * Yük sahibi — ilana ait arayanlar listesi.
     */
    public function listCallRequests(Request $request, string $slug)
    {
        $user = $request->user();
        if (! $user) {
            return $this->error([], 'Unauthorized', [], 401);
        }

        if ($user->type !== 'cargo_owner') {
            return $this->error([], 'Bu işlem yalnızca yük sahipleri içindir.', [], 403);
        }

        $shipment = Shipment::where('slug', $slug)->first();
        if (! $shipment) {
            return $this->error([], 'İlan bulunamadı.', [], 404);
        }

        if ((int) $shipment->creater_id !== (int) $user->id) {
            return $this->error([], 'Bu ilana erişim yetkiniz yok.', [], 403);
        }

        $assignedVehicleOwnerId = (int) (ShipmentOrder::where('shipment_id', $shipment->id)->value('buyer_user') ?? 0);

        $rows = ShipmentCallRequest::with(['vehicleOwner'])
            ->where('shipment_id', $shipment->id)
            ->orderByDesc('called_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (ShipmentCallRequest $row) use ($assignedVehicleOwnerId) {
                $formatted = $this->formatCallRequestRow($row);
                $formatted['is_assigned'] = $assignedVehicleOwnerId > 0
                    && (int) $row->vehicle_owner_id === $assignedVehicleOwnerId;

                return $formatted;
            })
            ->values();

        return $this->success([
            'count' => $rows->count(),
            'call_requests' => $rows,
            'shipment' => [
                'status' => $shipment->status,
                'route_label' => $this->shipmentRouteLabel($shipment),
                'f_where_city' => $shipment->f_where_city,
                'f_where_district' => $shipment->f_where_district,
                't_where_city' => $shipment->t_where_city,
                't_where_district' => $shipment->t_where_district,
                'assigned_vehicle_owner_id' => $assignedVehicleOwnerId > 0 ? $assignedVehicleOwnerId : null,
            ],
        ]);
    }

    /**
     * Yük sahibi — arayan kişiyi kabul ederek ilanı araç sahibine verir (teklif kabulü ile aynı sonuç).
     */
    public function acceptCallRequest(Request $request, string $slug, int $callRequestId)
    {
        $user = $request->user();
        if (! $user) {
            return $this->error([], 'Unauthorized', [], 401);
        }

        if ($user->type !== 'cargo_owner') {
            return $this->error([], 'Bu işlem yalnızca yük sahipleri içindir.', [], 403);
        }

        $shipment = Shipment::where('slug', $slug)->first();
        if (! $shipment) {
            return $this->error([], 'İlan bulunamadı.', [], 404);
        }

        if ((int) $shipment->creater_id !== (int) $user->id) {
            return $this->error([], 'Bu işlem sadece ilan sahibi tarafından yapılabilir.', [], 403);
        }

        if (! in_array((string) $shipment->status, ['active', 'pending'], true)) {
            return $this->error([], 'Bu ilan artık kabul edilemez.', [], 422);
        }

        $callRequest = ShipmentCallRequest::with('vehicleOwner')
            ->where('id', $callRequestId)
            ->where('shipment_id', $shipment->id)
            ->first();

        if (! $callRequest) {
            return $this->error([], 'Arama kaydı bulunamadı.', [], 404);
        }

        $vehicleOwner = $callRequest->vehicleOwner;
        if (! $vehicleOwner) {
            return $this->error([], 'Araç sahibi bulunamadı.', [], 404);
        }

        $existingOrder = ShipmentOrder::where('shipment_id', $shipment->id)->first();
        if ($existingOrder) {
            if ((int) $existingOrder->buyer_user === (int) $vehicleOwner->id) {
                return $this->success([], 'İlan zaten bu kişiye verilmiş.');
            }

            return $this->error([], 'Bu ilan başka bir taşıyıcıya verilmiş.', [], 422);
        }

        $req = Requests::where('shipment_id', $shipment->id)
            ->where('user_id', $vehicleOwner->id)
            ->first();

        if (! $req) {
            $carType = (int) ($shipment->car_id ?? 0);
            if ($carType <= 0) {
                $userCar = UserCar::where('user_id', $vehicleOwner->id)->first();
                $carType = (int) ($userCar?->car_id ?? 0);
            }
            if ($carType <= 0) {
                return $this->error([], 'Araç tipi belirlenemedi.', [], 422);
            }

            $req = Requests::create([
                'shipment_id' => $shipment->id,
                'user_id' => $vehicleOwner->id,
                'car_type' => $carType,
                'price' => 0,
                'message' => null,
            ]);
        }

        $req->update(['status' => 'accepted']);
        $shipment->update(['status' => 'accepted']);

        Requests::where('shipment_id', $shipment->id)
            ->where('id', '!=', $req->id)
            ->update(['status' => 'rejected']);

        $order = ShipmentOrder::firstOrCreate(
            ['shipment_id' => $shipment->id],
            [
                'creater_user' => $shipment->creater_id,
                'buyer_user' => $vehicleOwner->id,
                'shipment_id' => $shipment->id,
                // Önceden teklif verdiyse o teklifteki araç
                'user_car_id' => $req->user_car_id,
            ]
        );
        app(JobStartService::class)->autoStartIfOnTheRoad($order);

        $vehicleName = trim((string) $vehicleOwner->full_name) ?: 'Taşıyıcı';

        $acceptedInfoMessage = Message::create([
            'sender_id' => $shipment->creater_id,
            'receiver_id' => $vehicleOwner->id,
            'shipment_id' => $shipment->id,
            'message' => '',
            'sender_message' => "Aramayı kabul ettiniz. İlan {$vehicleName} kişisine verildi.",
            'receiver_message' => 'Aramanız kabul edildi, ilan size verildi.',
            'type' => 'system',
            'status' => 'accepted',
            'is_read' => false,
        ]);
        broadcast(new MessageSent($acceptedInfoMessage));

        $req->refresh();
        $req->load('user');
        $shipmentFresh = $shipment->fresh(['creator']);
        $this->sendCallRequestAcceptedPushes($shipmentFresh, $vehicleOwner);
        $this->broadcastOfferAccepted($shipmentFresh, $req);

        return $this->success(['request' => $req, 'shipment' => $shipmentFresh], 'Arama kabul edildi, ilan verildi.');
    }

    private function normalizePhoneDigits(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '90') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }

    private function formatPhoneDisplay(?string $phone): string
    {
        $digits = $this->normalizePhoneDigits($phone);
        if ($digits === null || strlen($digits) !== 10) {
            return $digits ?? '—';
        }

        return substr($digits, 0, 3).' '.substr($digits, 3, 3).' '.substr($digits, 6, 2).' '.substr($digits, 8, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCallRequestRow(ShipmentCallRequest $row): array
    {
        $calledAt = $row->called_at ?? $row->created_at;

        return [
            'id' => $row->id,
            'shipment_id' => $row->shipment_id,
            'vehicle_owner_id' => $row->vehicle_owner_id,
            'caller_phone' => $this->formatPhoneDisplay($row->caller_phone),
            'called_at' => $calledAt?->toIso8601String(),
            'called_date_label' => $calledAt?->locale('tr')->translatedFormat('d F Y'),
            'called_time_label' => $calledAt?->format('H:i'),
            'vehicle_owner' => $row->vehicleOwner ? [
                'id' => $row->vehicleOwner->id,
                'full_name' => $row->vehicleOwner->full_name,
            ] : null,
        ];
    }

    private function sendCallListingTakenPushToCargoOwner(Shipment $shipment, User $vehicleOwner): void
    {
        $cargoOwner = $shipment->creator;
        if (! $cargoOwner || (int) $cargoOwner->id === (int) $vehicleOwner->id) {
            Log::warning('push.call_listing_taken.skipped', [
                'reason' => 'missing_or_same_cargo_owner',
                'shipment_id' => $shipment->id,
                'slug' => $shipment->slug,
            ]);

            return;
        }

        $cargoOwner->loadMissing('pushDevice');
        $route = $this->shipmentRouteLabel($shipment);
        $body = "{$route} ilanınız için az önce bir görüşme gerçekleştirdiniz. Bu bildirime tıklayıp arayanları görüntüleyin.";

        $this->dispatchPushToUser(
            $cargoOwner,
            'Görüşme bildirimi',
            $body,
            [
                'type' => 'call_listing_taken',
                'shipment_id' => (string) $shipment->id,
                'slug' => (string) $shipment->slug,
            ],
            [
                'event' => 'call_listing_taken',
                'shipment_id' => $shipment->id,
                'slug' => $shipment->slug,
                'cargo_owner_id' => $cargoOwner->id,
                'vehicle_owner_id' => $vehicleOwner->id,
            ],
            'push.call_listing_taken'
        );
    }

  private function sendCallRequestAcceptedPushes(Shipment $shipment, User $vehicleOwner): void
    {
        $route = $this->shipmentRouteLabel($shipment);
        $cargoOwner = $shipment->creator;
        $vehicleName = trim((string) $vehicleOwner->full_name) ?: 'Taşıyıcı';

        $vehicleOwner->loadMissing('pushDevice');
        $this->dispatchPushToUser(
            $vehicleOwner,
            'İlan size verildi',
            "{$route} ilanı size verildi. İşi alıp devam etmek için lütfen tıklayınız.",
            [
                // Mobil: dokununca aktif iş ekranı (İşi Başlat penceresi)
                'type' => 'job_assigned',
                'shipment_id' => (string) $shipment->id,
                'slug' => (string) $shipment->slug,
            ],
            [
                'event' => 'call_accepted_vehicle_owner',
                'shipment_id' => $shipment->id,
                'slug' => $shipment->slug,
                'vehicle_owner_id' => $vehicleOwner->id,
                'cargo_owner_id' => $shipment->creater_id,
            ],
            'push.call_accepted.vehicle_owner'
        );

        if ($cargoOwner) {
            $cargoOwner->loadMissing('pushDevice');
            $this->dispatchPushToUser(
                $cargoOwner,
                'İlan verildi',
                "{$route} ilanınız {$vehicleName} kişisine verildi. panelden ilan detaylarını görüntüleyebilirsiniz",
                [
                    'type' => 'offer_accepted',
                    'shipment_id' => (string) $shipment->id,
                    'slug' => (string) $shipment->slug,
                ],
                [
                    'event' => 'call_accepted_cargo_owner',
                    'shipment_id' => $shipment->id,
                    'slug' => $shipment->slug,
                    'cargo_owner_id' => $cargoOwner->id,
                    'vehicle_owner_id' => $vehicleOwner->id,
                ],
                'push.call_accepted.cargo_owner'
            );
        }
    }
}
