<?php

namespace App\Support;

use Illuminate\Http\Request;

class CatalogAssetUrl
{
    public const FOLDER_VEHICLES = 'vehicles';

    public const FOLDER_POST_TYPES = 'post_types';

    public static function vehicle(Request $request, ?string $filename): ?string
    {
        return self::absoluteForRequest($request, self::FOLDER_VEHICLES, $filename);
    }

    /**
     * Kasası açık araç görseli: `public/assets/vehicles/opened_images` içinde katalog görseliyle aynı adla
     * dosya varsa tam URL'si, yoksa null (ör. tır_tenteli.png). Aktif iş ve yük sahibinin ilan detayında kullanılır.
     */
    public static function openedVehicle(Request $request, ?string $filename): ?string
    {
        $basename = basename(str_replace(chr(92), '/', trim((string) $filename)));
        if ($basename === '' || ! is_file(public_path('assets/vehicles/opened_images/'.$basename))) {
            return null;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/').'/assets/vehicles/opened_images/'.rawurlencode($basename);
    }

    public static function postType(Request $request, ?string $filename): ?string
    {
        return self::absoluteForRequest($request, self::FOLDER_POST_TYPES, $filename);
    }

    public static function absoluteForRequest(Request $request, string $folder, ?string $filename): ?string
    {
        if ($filename === null || trim($filename) === '') {
            return null;
        }

        $raw = trim($filename);
        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            return $raw;
        }

        $basename = basename(str_replace('\\', '/', $raw));
        if ($basename === '') {
            return null;
        }

        $base = rtrim($request->getSchemeAndHttpHost(), '/');

        return $base.'/assets/'.$folder.'/'.$basename;
    }
}
