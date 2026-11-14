<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

final class Geo
{
    public static function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    public static function parseExactLocation(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_array($raw)) {
            $lat = $raw['lat'] ?? $raw['latitude'] ?? null;
            $lng = $raw['lng'] ?? $raw['longitude'] ?? $raw['lon'] ?? null;
            if (is_numeric($lat) && is_numeric($lng)) {
                return ['lat' => (float) $lat, 'lng' => (float) $lng];
            }

            return null;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return self::parseExactLocation($decoded);
            }
            if (str_contains($raw, ',')) {
                [$lat, $lng] = array_map('trim', explode(',', $raw, 2));
                if (is_numeric($lat) && is_numeric($lng)) {
                    return ['lat' => (float) $lat, 'lng' => (float) $lng];
                }
            }
        }

        return null;
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    public static function encodeExactLocation(?float $lat, ?float $lng): ?array
    {
        if ($lat === null || $lng === null) {
            return null;
        }
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return ['lat' => round($lat, 7), 'lng' => round($lng, 7)];
    }

    /**
     * İl / ilçe adından yaklaşık koordinat (Nominatim). Yükleme noktası referansı için.
     *
     * @return array{lat: float, lng: float}|null
     */
    public static function geocodeCityDistrict(?string $city, ?string $district = null): ?array
    {
        $cityTrim = trim((string) $city);
        if ($cityTrim === '') {
            return null;
        }

        $districtTrim = trim((string) $district);
        $query = implode(', ', array_filter([$cityTrim, $districtTrim, 'Türkiye']));

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'Accept-Language' => 'tr',
                    'User-Agent' => 'TasibulLogistics/1.0 (close-users geocode)',
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'json',
                    'limit' => 1,
                    'countrycodes' => 'tr',
                ]);

            if (! $response->successful()) {
                return null;
            }

            $item = $response->json()[0] ?? null;
            if (! is_array($item) || ! is_numeric($item['lat'] ?? null) || ! is_numeric($item['lon'] ?? null)) {
                return null;
            }

            return [
                'lat' => (float) $item['lat'],
                'lng' => (float) $item['lon'],
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
