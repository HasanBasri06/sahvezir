<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mobilde Google Sign-In'den gelen ID token'ı Google'a doğrulatır.
 * Token'ın `aud` alanı bizim OAuth istemcilerimizden biri olmalı (varsayılan: web client ID).
 */
class GoogleIdTokenVerifier
{
    private const TOKENINFO_URL = 'https://oauth2.googleapis.com/tokeninfo';

    private const ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    /**
     * @return array{sub: string, email: string, first_name: string, last_name: string}|null
     */
    public function verify(string $idToken): ?array
    {
        $audiences = array_values(array_filter(array_map('trim', (array) config('services.google.mobile_audiences'))));
        if ($audiences === []) {
            Log::error('auth.google.no_audience — OAUTH_GOOGLE_CLIENT_ID tanımlı değil');

            return null;
        }

        try {
            $response = Http::timeout(10)->acceptJson()->get(self::TOKENINFO_URL, ['id_token' => $idToken]);
        } catch (\Throwable $e) {
            Log::warning('auth.google.tokeninfo_exception', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::info('auth.google.invalid_token', ['http_status' => $response->status()]);

            return null;
        }

        $claims = $response->json();

        $valid = is_array($claims)
            && in_array($claims['aud'] ?? null, $audiences, true)
            && in_array($claims['iss'] ?? null, self::ISSUERS, true)
            && (int) ($claims['exp'] ?? 0) > time()
            && ! empty($claims['sub'])
            && ! empty($claims['email'])
            && filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $valid) {
            Log::info('auth.google.claims_rejected', [
                'aud' => $claims['aud'] ?? null,
                'iss' => $claims['iss'] ?? null,
                'email_verified' => $claims['email_verified'] ?? null,
            ]);

            return null;
        }

        $fullName = trim((string) ($claims['name'] ?? ''));

        return [
            'sub' => (string) $claims['sub'],
            'email' => mb_strtolower(trim((string) $claims['email'])),
            'first_name' => trim((string) ($claims['given_name'] ?? '')) ?: (explode(' ', $fullName)[0] ?? ''),
            'last_name' => trim((string) ($claims['family_name'] ?? '')),
        ];
    }
}
