<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Tamamlanan ilan belgeleri: `public/shipment_attachments/{shipmentId}/`
 * DB'de göreli yol: `shipment_attachments/{shipmentId}/{filename}`
 */
class ShipmentAttachmentStorage
{
    public const PATH_PREFIX = 'shipment_attachments/';

    public static function relativePath(int $shipmentId, string $filename): string
    {
        return self::PATH_PREFIX.$shipmentId.'/'.ltrim($filename, '/');
    }

    public static function ensureDirectory(int $shipmentId): string
    {
        $dir = public_path(self::PATH_PREFIX.$shipmentId);
        if (! is_dir($dir)) {
            File::ensureDirectoryExists($dir, 0755, true);
        }

        return $dir;
    }

    public static function put(int $shipmentId, string $filename, string $bytes): string
    {
        self::ensureDirectory($shipmentId);
        $relative = self::relativePath($shipmentId, $filename);
        $full = public_path(str_replace('\\', '/', $relative));

        if (file_put_contents($full, $bytes) === false) {
            throw new \RuntimeException('Belge public dizinine yazılamadı.');
        }

        if (! is_file($full)) {
            throw new \RuntimeException('Belge public dizininde doğrulanamadı.');
        }

        return str_replace('\\', '/', $relative);
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
        }
    }
}
