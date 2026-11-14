<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Hesap doğrulama belgeleri doğrudan `public/verification/{type}/` altına yazılır.
 * DB'de göreli yol: `verification/{type}/{filename}`
 */
class VerificationFileStorage
{
    public const PATH_PREFIX = 'verification/';

    public static function relativePath(string $type, string $filename): string
    {
        return self::PATH_PREFIX.$type.'/'.ltrim($filename, '/');
    }

    public static function absolutePublicPath(string $relativePath): string
    {
        $normalized = str_replace('\\', '/', ltrim($relativePath, '/'));

        return public_path($normalized);
    }

    public static function ensureDirectory(string $type): string
    {
        $dir = public_path(self::PATH_PREFIX.$type);
        if (! is_dir($dir)) {
            File::ensureDirectoryExists($dir, 0755, true);
        }

        return $dir;
    }

    public static function put(string $type, string $filename, string $bytes): string
    {
        self::ensureDirectory($type);
        $relative = self::relativePath($type, $filename);
        $full = self::absolutePublicPath($relative);

        if (file_put_contents($full, $bytes) === false) {
            throw new \RuntimeException('Dosya public dizinine yazılamadı.');
        }

        if (! is_file($full)) {
            throw new \RuntimeException('Dosya public dizininde doğrulanamadı.');
        }

        return str_replace('\\', '/', $relative);
    }

    public static function exists(string $relativePath): bool
    {
        $normalized = str_replace('\\', '/', ltrim($relativePath, '/'));
        if ($normalized === '') {
            return false;
        }

        if (is_file(public_path($normalized))) {
            return true;
        }

        return Storage::disk('public')->exists($normalized);
    }

    public static function delete(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        $normalized = str_replace('\\', '/', ltrim($relativePath, '/'));
        if ($normalized === '' || ! str_starts_with($normalized, self::PATH_PREFIX)) {
            return;
        }

        $publicFile = public_path($normalized);
        if (is_file($publicFile)) {
            @unlink($publicFile);

            return;
        }

        if (Storage::disk('public')->exists($normalized)) {
            Storage::disk('public')->delete($normalized);
        }
    }
}
