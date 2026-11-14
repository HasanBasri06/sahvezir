<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Recaptcha implements ValidationRule
{
    /**
     * Google reCAPTCHA v2 ("Ben robot değilim") token'ını Google'a doğrulatır.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $response = Http::asForm()
            ->timeout(10)
            ->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => config('services.recaptcha.secret'),
                'response' => $value,
                'remoteip' => request()->ip(),
            ]);

        if (! $response->successful() || ! $response->json('success')) {
            // invalid-input-secret görünürse .env'deki RECAPTCHA_SECRET_KEY hatalıdır
            Log::warning('reCAPTCHA doğrulaması başarısız', [
                'status' => $response->status(),
                'error-codes' => $response->json('error-codes'),
            ]);

            $fail('Robot doğrulaması başarısız oldu, lütfen tekrar deneyin.');
        }
    }
}
