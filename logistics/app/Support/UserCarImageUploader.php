<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UserCarImageUploader
{
    /** `public/verification/user_cars/` — DB yolu: `verification/user_cars/{filename}` */
    public const STORAGE_TYPE = 'user_cars';
    /**
     * @return array<string, mixed>
     */
    public static function resolvePayloadInput(Request $request): array
    {
        $rawPayload = $request->input('payload');
        if (is_string($rawPayload) && $rawPayload !== '') {
            $decoded = json_decode($rawPayload, true);

            return is_array($decoded) ? $decoded : [];
        }

        return $request->all();
    }

    public static function storeOptionalFromRequest(Request $request, string $field): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        $validator = Validator::make($request->all(), [
            $field => 'file|max:5120',
        ], [
            "{$field}.max" => 'Fotoğraf en fazla 5MB olabilir.',
        ]);

        if ($validator->fails()) {
            abort(response()->json([
                'status' => false,
                'message' => 'Doğrulama hatası',
                'errors' => $validator->errors()->toArray(),
            ], 422));
        }

        $file = $request->file($field);
        if (! $file instanceof UploadedFile) {
            return null;
        }

        return self::storeUploadedFile($file);
    }

    public static function storeUploadedFile(UploadedFile $file): string
    {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (! in_array($file->getMimeType(), $allowedMimes, true)) {
            abort(response()->json([
                'status' => false,
                'message' => 'Geçersiz dosya türü. Sadece JPG, PNG veya WEBP yükleyebilirsiniz.',
                'errors' => [],
            ], 422));
        }

        $ext = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg';
        $filename = Str::random(40).'.'.strtolower($ext);

        try {
            $bytes = $file->getContent();
        } catch (\Throwable) {
            abort(response()->json([
                'status' => false,
                'message' => 'Dosya okunamadı.',
                'errors' => [],
            ], 422));
        }

        if ($bytes === '') {
            abort(response()->json([
                'status' => false,
                'message' => 'Dosya boş.',
                'errors' => [],
            ], 422));
        }

        try {
            return VerificationFileStorage::put(self::STORAGE_TYPE, $filename, $bytes);
        } catch (\Throwable $e) {
            report($e);

            abort(response()->json([
                'status' => false,
                'message' => 'Fotoğraf kaydedilemedi.',
                'errors' => [],
            ], 500));
        }
    }

    public static function delete(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        $normalized = str_replace('\\', '/', ltrim($relativePath, '/'));
        if ($normalized === '') {
            return;
        }

        if (str_starts_with($normalized, VerificationFileStorage::PATH_PREFIX)) {
            VerificationFileStorage::delete($normalized);

            return;
        }

        /** Eski kayıtlar: `user_cars/{filename}` */
        if (str_starts_with($normalized, 'user_cars/')) {
            $publicFile = public_path($normalized);
            if (is_file($publicFile)) {
                @unlink($publicFile);
            }
        }
    }

    public static function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
