<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * API isteğinin host’u ile `storage` altındaki dosyaların tam URL’si (mobil görünürlük).
 */
class PublicStorageUrl
{
    public static function absoluteForRequest(Request $request, ?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        $p = str_replace('\\', '/', trim($path));
        if ($p === '') {
            return null;
        }
        if (str_starts_with($p, 'http://') || str_starts_with($p, 'https://')) {
            return $p;
        }
        $p = ltrim($p, '/');
        $base = rtrim($request->getSchemeAndHttpHost(), '/');
        if (str_starts_with($p, 'storage/')) {
            return $base.'/'.$p;
        }

        if (str_starts_with($p, VerificationFileStorage::PATH_PREFIX)
            || str_starts_with($p, ShipmentAttachmentStorage::PATH_PREFIX)
            || str_starts_with($p, 'user_cars/')) {
            if (is_file(public_path($p))) {
                return $base.'/'.$p;
            }

            return $base.'/storage/'.$p;
        }

        return $base.'/storage/'.$p;
    }
}
