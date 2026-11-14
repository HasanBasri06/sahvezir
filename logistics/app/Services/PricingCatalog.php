<?php

namespace App\Services;

/**
 * Paket kataloğu (config/pricing.php): hesap türüne göre paketler, süre fiyatları ve araç sınırları.
 * Ödeme tutarı istemciden değil buradan hesaplanır.
 */
class PricingCatalog
{
    public const DEFAULT_TIER = 'individual';

    public const DURATIONS = [1, 3, 6, 12];

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return (array) config('pricing.packages', []);
    }

    public function get(string $tier): ?array
    {
        $p = $this->all()[$tier] ?? null;

        return is_array($p) ? $p : null;
    }

    public function allowedFor(string $tier, ?string $accountType): bool
    {
        $p = $this->get($tier);

        return $p !== null && in_array((string) $accountType, (array) ($p['account_types'] ?? []), true);
    }

    /** Toplam fiyat (TL); paket / süre geçersizse null */
    public function price(string $tier, int $months): ?float
    {
        $price = $this->get($tier)['prices'][$months] ?? null;

        return $price !== null ? (float) $price : null;
    }

    public function vehicleLimit(?string $tier): int
    {
        return max(1, (int) ($this->get($tier ?: self::DEFAULT_TIER)['vehicle_limit'] ?? 1));
    }

    public function label(string $tier, int $months): string
    {
        return ($this->get($tier)['name'] ?? 'Paket').' · '.$months.' Aylık';
    }

    /**
     * İstemci için paket listesi (hesap türüne göre).
     *
     * @return list<array<string, mixed>>
     */
    public function forAccountType(?string $accountType): array
    {
        $out = [];
        foreach ($this->all() as $id => $p) {
            if ($accountType && ! in_array($accountType, (array) ($p['account_types'] ?? []), true)) {
                continue;
            }
            $monthly = (float) ($p['prices'][1] ?? 0);
            $durations = [];
            foreach ((array) ($p['prices'] ?? []) as $months => $total) {
                $months = (int) $months;
                $normal = $monthly * $months;
                $discount = max(0, $normal - (float) $total);
                $durations[] = [
                    'months' => $months,
                    'total' => (float) $total,
                    'monthly' => round((float) $total / max(1, $months), 2),
                    'discount' => round($discount, 2),
                    'discount_rate' => $normal > 0 ? round($discount / $normal * 100, 2) : 0,
                ];
            }
            $out[] = [
                'id' => (string) $id,
                'name' => (string) $p['name'],
                'description' => (string) ($p['description'] ?? ''),
                'vehicle_limit' => (int) ($p['vehicle_limit'] ?? 1),
                'features' => array_values((array) ($p['features'] ?? [])),
                'durations' => $durations,
            ];
        }

        return $out;
    }
}
