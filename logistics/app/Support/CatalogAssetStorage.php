<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CatalogAssetStorage
{
    public static function storeUploadedFile(UploadedFile $file, string $folder): string
    {
        $directory = public_path('assets/'.$folder);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'png');
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $extension = 'png';
        }

        $filename = Str::uuid()->toString().'.'.$extension;
        $file->move($directory, $filename);

        return $filename;
    }

    public static function deleteFile(string $folder, ?string $filename): void
    {
        if ($filename === null || trim($filename) === '') {
            return;
        }

        if (str_starts_with($filename, 'http://') || str_starts_with($filename, 'https://')) {
            return;
        }

        $path = public_path('assets/'.$folder.'/'.basename(str_replace('\\', '/', $filename)));
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
