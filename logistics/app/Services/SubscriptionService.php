<?php

namespace App\Services;

use App\Enums\PaymentResultStatus;
use App\Models\User;
use App\Models\UserCar;
use App\Models\UserOrder;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Tüm satın alımlar (`user_subscriptions`) için tek giriş noktası; bitiş tarihleri buradan okunur.
 *
 * - type=package: üyelik paketi (1 / 3 / 6 / 12 ay). Kullanıcı başına tek `active`; yeni paket öncekini `inactive`
 *   yapar, bitmemiş süre yeni paketin üstüne eklenir.
 * - type=vehicle: Çok Araç Kontrol Modunda ek araç başı aylık ücret (her zaman 1 ay). Araç başına tek `active`.
 * - Otomatik talimat (`auto_renew`): bitişe yaklaşınca kayıtlı karttan (PayTR) yenilenir.
 * - `users.payment_confirm` / `users.end_payment_date` eski kodla uyum için paketle eşlenir (kaynak değildir).
 */
class SubscriptionService
{
    public const TZ = 'Europe/Istanbul';

    /** Ek araç başı aylık ücret (KDV hariç) */
    public const VEHICLE_PRICE = 299;

    public const VAT_RATE = 20;

    /** Yenileme bitişten bu kadar önce denenir */
    public const RENEW_BEFORE_HOURS = 24;

    /** Başarısız yenileme en fazla bu kadar denenir (günde bir) */
    public const MAX_RENEW_ATTEMPTS = 3;

    public static function withVat(float $amount): array
    {
        $vat = round($amount * self::VAT_RATE / 100, 2);

        return ['net' => round($amount, 2), 'vat' => $vat, 'total' => round($amount + $vat, 2)];
    }

    /** Kullanıcının aktif paketi (yoksa null) */
    public function current(User $user): ?UserSubscription
    {
        return UserSubscription::query()
            ->where('user_id', $user->id)
            ->where('type', 'package')
            ->where('status', UserSubscription::STATUS_ACTIVE)
            ->orderByDesc('id')
            ->first();
    }

    /** Aktif paketin bitiş tarihi (yoksa null) */
    public function currentEnd(User $user): ?Carbon
    {
        $ends = $this->current($user)?->ends_at;

        return $ends ? Carbon::parse($ends)->timezone(self::TZ) : null;
    }

    /** Aracın aktif aylık kaydı (yoksa null) */
    public function vehicleSubscription(int $userCarId): ?UserSubscription
    {
        return UserSubscription::query()
            ->where('type', 'vehicle')
            ->where('user_car_id', $userCarId)
            ->where('status', UserSubscription::STATUS_ACTIVE)
            ->orderByDesc('id')
            ->first();
    }

    /** Aktif paketin kapsadığı araç sayısı (Bireysel 1; İşletme paketlerinde paket sınırı) */
    public function includedVehicles(User $user): int
    {
        $current = $this->current($user);
        if ($current && $current->vehicle_limit) {
            return max(1, (int) $current->vehicle_limit);
        }

        return app(PricingCatalog::class)->vehicleLimit($current?->package_tier);
    }

    /**
     * Ücretli araçlar: paketin kapsadığı ilk N araç dahil, sonraki her araç aylık ücretli.
     *
     * @return \Illuminate\Support\Collection<int, UserCar>
     */
    public function billableCars(User $user)
    {
        return UserCar::where('user_id', $user->id)
            ->where('status', 'active')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->slice($this->includedVehicles($user))
            ->values();
    }

    public function isBillableCar(User $user, int $userCarId): bool
    {
        return $this->billableCars($user)->contains('id', $userCarId);
    }

    /**
     * Ödenen siparişi işler: paket, araç veya otomatik yenileme. Aynı sipariş ikinci kez işlenirse yeni kayıt açılmaz.
     */
    public function grantFromOrder(User $user, UserOrder $order): UserSubscription
    {
        $existing = UserSubscription::query()->where('user_order_id', $order->id)->first();
        if ($existing) {
            return $existing;
        }

        $type = (string) ($order->order_type ?: 'package');
        if ($type === 'renewal' && $order->renews_subscription_id) {
            $previous = UserSubscription::find($order->renews_subscription_id);
            $type = $previous?->type ?? 'package';
            if ($type === 'vehicle' && $previous) {
                return $this->grantVehicle($user, (int) $previous->user_car_id, $order);
            }
        } elseif ($type === 'vehicle' && $order->user_car_id) {
            return $this->grantVehicle($user, (int) $order->user_car_id, $order);
        }

        $months = max(1, (int) ($order->plan_months ?? 1));
        // Yenilemede paket türü önceki kayıttan
        $tier = (string) ($order->package_tier
            ?: ($order->renews_subscription_id ? UserSubscription::whereKey($order->renews_subscription_id)->value('package_tier') : null)
            ?: PricingCatalog::DEFAULT_TIER);

        return $this->grant($user, [
            'type' => 'package',
            'source' => 'payment',
            'user_order_id' => $order->id,
            'package_tier' => $tier,
            'vehicle_limit' => app(PricingCatalog::class)->vehicleLimit($tier),
            'package_name' => (string) $order->package_name,
            'plan_months' => $months,
            // Siparişteki tutar ödenen (KDV dahil) tutardır
            'price' => (float) $order->package_price,
            'total_amount' => (float) $order->package_price,
        ], months: $months);
    }

    /**
     * Araç başı aylık ücret: her zaman 1 ay; aracın bitmemiş süresi varsa üstüne eklenir.
     */
    public function grantVehicle(User $user, int $userCarId, ?UserOrder $order = null): UserSubscription
    {
        return DB::transaction(function () use ($user, $userCarId, $order) {
            $now = Carbon::now(self::TZ);
            $current = UserSubscription::query()
                ->where('type', 'vehicle')
                ->where('user_car_id', $userCarId)
                ->where('status', UserSubscription::STATUS_ACTIVE)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $currentEnd = $current ? Carbon::parse($current->ends_at)->timezone(self::TZ) : null;
            $base = $currentEnd && $currentEnd->greaterThan($now) ? $currentEnd : $now;
            $end = $base->copy()->addMonthsNoOverflow(1);

            UserSubscription::query()
                ->where('type', 'vehicle')
                ->where('user_car_id', $userCarId)
                ->where('status', UserSubscription::STATUS_ACTIVE)
                ->update(['status' => UserSubscription::STATUS_INACTIVE]);

            $plate = UserCar::whereKey($userCarId)->value('plate');
            $amounts = self::withVat(self::VEHICLE_PRICE);

            $sub = UserSubscription::create([
                'user_id' => $user->id,
                'type' => 'vehicle',
                'user_car_id' => $userCarId,
                'user_order_id' => $order?->id,
                'source' => 'payment',
                'package_name' => 'Araç aboneliği'.($plate ? " ({$plate})" : ''),
                'plan_months' => 1,
                'price' => $amounts['net'],
                'vat_amount' => $amounts['vat'],
                'total_amount' => $amounts['total'],
                'starts_at' => $now,
                'ends_at' => $end,
                'status' => UserSubscription::STATUS_ACTIVE,
                // Önceki kaydın talimat tercihi korunur
                'auto_renew' => $current ? (bool) $current->auto_renew : true,
            ]);

            Log::info('subscription.vehicle_granted', ['user_id' => $user->id, 'user_car_id' => $userCarId, 'ends_at' => $end->toDateTimeString()]);

            return $sub;
        });
    }

    /**
     * Yeni paket ekler. `months` / `days` verilirse süre, aktif paket bitmediyse onun bitişinin üstüne eklenir;
     * `endsAt` verilirse (yönetici kesin tarih) bitiş doğrudan odur.
     *
     * @param  array<string, mixed>  $attrs  source, package_name, plan_months, price, user_order_id
     */
    public function grant(User $user, array $attrs, ?int $months = null, ?int $days = null, ?Carbon $endsAt = null): UserSubscription
    {
        return DB::transaction(function () use ($user, $attrs, $months, $days, $endsAt) {
            $now = Carbon::now(self::TZ);

            // Eşzamanlı iki ödemede aynı bitişin üstüne iki kez eklenmesin
            $current = UserSubscription::query()
                ->where('user_id', $user->id)
                ->where('type', 'package')
                ->where('status', UserSubscription::STATUS_ACTIVE)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $currentEnd = $current ? Carbon::parse($current->ends_at)->timezone(self::TZ) : null;
            $base = $currentEnd && $currentEnd->greaterThan($now) ? $currentEnd : $now;

            if ($endsAt) {
                $end = $endsAt->copy()->timezone(self::TZ);
            } elseif ($days) {
                $end = $base->copy()->addDays($days);
            } else {
                $end = $base->copy()->addMonthsNoOverflow(max(1, (int) $months));
            }

            UserSubscription::query()
                ->where('user_id', $user->id)
                ->where('type', 'package')
                ->where('status', UserSubscription::STATUS_ACTIVE)
                ->update(['status' => UserSubscription::STATUS_INACTIVE]);

            $sub = UserSubscription::create($attrs + [
                'type' => 'package',
                'user_id' => $user->id,
                'starts_at' => $now,
                'ends_at' => $end,
                'status' => UserSubscription::STATUS_ACTIVE,
                'auto_renew' => $current ? (bool) $current->auto_renew : true,
            ]);

            $this->mirror($user, $end);

            Log::info('subscription.granted', [
                'user_id' => $user->id,
                'subscription_id' => $sub->id,
                'source' => $attrs['source'] ?? null,
                'carried_over_from' => $currentEnd && $currentEnd->greaterThan($now) ? $currentEnd->toDateTimeString() : null,
                'ends_at' => $end->toDateTimeString(),
            ]);

            return $sub;
        });
    }

    /** Otomatik talimatı aç / kapat (kullanıcının tüm aktif paket ve araç kayıtları) */
    public function setAutoRenew(User $user, bool $enabled): void
    {
        UserSubscription::query()
            ->where('user_id', $user->id)
            ->where('status', UserSubscription::STATUS_ACTIVE)
            ->update(['auto_renew' => $enabled, 'renew_attempts' => 0, 'last_renew_error' => null]);
    }

    /**
     * Bitişi yaklaşan ve otomatik talimatı açık kayıtları kayıtlı karttan yeniler.
     * Ücretli (pakete dahil olmayan) araçlar ve ödenmiş paketler yenilenir; deneme / yönetici kayıtları yenilenmez.
     *
     * @return array{charged: int, pending: int, failed: int}
     */
    public function renewDue(PaytrRecurringService $paytr): array
    {
        $stats = ['charged' => 0, 'pending' => 0, 'failed' => 0];
        $now = Carbon::now(self::TZ);

        $due = UserSubscription::query()
            ->with('user')
            ->where('status', UserSubscription::STATUS_ACTIVE)
            ->where('auto_renew', true)
            ->where('source', 'payment')
            ->where('ends_at', '<=', $now->copy()->addHours(self::RENEW_BEFORE_HOURS))
            ->where('renew_attempts', '<', self::MAX_RENEW_ATTEMPTS)
            // Aynı gün içinde tekrar deneme
            ->where(fn ($q) => $q->whereNull('last_renew_attempt_at')->orWhere('last_renew_attempt_at', '<=', $now->copy()->subHours(20)))
            ->orderBy('ends_at')
            ->get();

        foreach ($due as $sub) {
            $user = $sub->user;
            if (! $user || ! $user->paytr_utoken) {
                $sub->update(['renew_attempts' => self::MAX_RENEW_ATTEMPTS, 'last_renew_error' => 'Kayıtlı kart yok', 'last_renew_attempt_at' => $now]);
                $stats['failed']++;

                continue;
            }

            // Pakete dahil hale gelen (ör. ilk araç silindi) araç yenilenmez
            if ($sub->type === 'vehicle' && ! $this->isBillableCar($user, (int) $sub->user_car_id)) {
                $sub->update(['auto_renew' => false]);

                continue;
            }

            // Bekleyen bir yenileme ödemesi varsa (bildirim bekleniyor) yenisini başlatma
            $pending = UserOrder::where('renews_subscription_id', $sub->id)
                ->where('status', PaymentResultStatus::PENDING->value)
                ->where('created_at', '>=', $now->copy()->subDay())
                ->exists();
            if ($pending) {
                continue;
            }

            $amount = (float) ($sub->total_amount ?? $sub->price ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $merchantOid = 'TR'.$user->id.'S'.$sub->id.'T'.time().Str::upper(Str::random(4));
            $order = UserOrder::create([
                'user_id' => $user->id,
                'order_type' => 'renewal',
                'user_car_id' => $sub->user_car_id,
                'renews_subscription_id' => $sub->id,
                'merchant_oid' => $merchantOid,
                'package_name' => (string) $sub->package_name,
                'package_price' => (string) $amount,
                'plan_months' => (int) ($sub->plan_months ?: 1),
                'status' => PaymentResultStatus::PENDING->value,
                'message' => 'Otomatik yenileme',
            ]);

            $result = $paytr->charge($user, $merchantOid, $amount, (string) $sub->package_name ?: 'Abonelik yenileme');
            $sub->update(['last_renew_attempt_at' => $now]);

            if ($result['status'] === 'success') {
                $order->update(['status' => PaymentResultStatus::PAID->value, 'message' => 'Otomatik yenileme başarılı']);
                $this->grantFromOrder($user, $order);
                $stats['charged']++;
            } elseif ($result['status'] === 'wait_callback') {
                // Sonuç PayTR bildirimiyle gelir (callback siparişi işler)
                $stats['pending']++;
            } else {
                $order->update(['status' => PaymentResultStatus::FAILED->value, 'message' => $result['message']]);
                $sub->update([
                    'renew_attempts' => $result['try_again'] ? $sub->renew_attempts + 1 : self::MAX_RENEW_ATTEMPTS,
                    'last_renew_error' => mb_substr($result['message'], 0, 250),
                ]);
                $stats['failed']++;
            }
        }

        return $stats;
    }

    /** Süresi dolan araç kayıtlarını kapatır (paket bitişini `users:end-account` işler) */
    public function expireVehicles(): int
    {
        return UserSubscription::query()
            ->where('type', 'vehicle')
            ->where('status', UserSubscription::STATUS_ACTIVE)
            ->where('ends_at', '<=', Carbon::now(self::TZ))
            ->update(['status' => UserSubscription::STATUS_INACTIVE]);
    }

    /** Süresi dolan paket: aktif paket kapatılır (yeni paket alınana kadar abonelik yok) */
    public function expire(User $user): void
    {
        UserSubscription::query()
            ->where('user_id', $user->id)
            ->where('type', 'package')
            ->where('status', UserSubscription::STATUS_ACTIVE)
            ->update(['status' => UserSubscription::STATUS_INACTIVE]);

        $user->forceFill(['payment_confirm' => 0])->save();
    }

    /** Eski kodla uyum: users tablosundaki alanlar aktif paketle aynı tutulur */
    private function mirror(User $user, Carbon $end): void
    {
        $user->forceFill([
            'payment_confirm' => 1,
            'end_payment_date' => $end->format('Y-m-d H:i:s'),
        ])->save();
    }
}
