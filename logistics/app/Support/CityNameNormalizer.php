<?php

namespace App\Support;

use App\Models\City;

class CityNameNormalizer
{
    public static function normalize(?string $name): string
    {
        if ($name === null) {
            return '';
        }
        $s = trim($name);
        foreach ([' İli', ' İl', ' Province'] as $suffix) {
            if (str_ends_with($s, $suffix)) {
                $s = trim(substr($s, 0, -strlen($suffix)));
                break;
            }
        }

        return $s;
    }

    /** Geocode / GPS şehir adını `cities.name` ile hizalar; bulunamazsa normalize edilmiş ham ad. */
    public static function canonicalForFilter(?string $raw): ?string
    {
        $n = self::normalize($raw);
        if ($n === '') {
            return null;
        }

        $needle = mb_strtolower($n, 'UTF-8');
        static $cityNames = null;
        if ($cityNames === null) {
            $cityNames = City::query()->pluck('name')->all();
        }

        foreach ($cityNames as $name) {
            if (mb_strtolower(trim((string) $name), 'UTF-8') === $needle) {
                return (string) $name;
            }
        }

        foreach ($cityNames as $name) {
            $cityLower = mb_strtolower(trim((string) $name), 'UTF-8');
            if (str_contains($needle, $cityLower) || str_contains($cityLower, $needle)) {
                return (string) $name;
            }
        }

        return $n;
    }
}
