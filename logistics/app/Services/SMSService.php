<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SMSService
{
    private string $HEADER_TYPE = 'TASIBUL LOJ';

    /**
     * Vatan SMS OTP endpoint (/otp) — tüm doğrulama kodları bu metotla gönderilir.
     */
    public function sendOTP(string $phone, string $message): ?string
    {
        $phone = $this->normalizePhone($phone);
        if ($phone === '') {
            return null;
        }

        $ch = curl_init();

        $params = [
            'api_id' => config('sms.api_id'),
            'api_key' => config('sms.api_key'),
            'phones' => [$phone],
            'message' => $message,
            'sender' => $this->HEADER_TYPE,
            'message_type' => 'otp',
        ];

        $curl_options = [
            CURLOPT_URL => config('sms.api_url').'/otp',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
        ];

        curl_setopt_array($ch, $curl_options);
        $response = curl_exec($ch);

        if ($response === false) {
            Log::error('SMS OTP gönderilemedi', ['error' => curl_error($ch), 'phone' => $phone]);
        }

        Log::info('SMS response', [
            'response' => $response,
            'phones' => $params['phones'],
            'sender' => $params['sender'],
        ]);
        
        curl_close($ch);

        return $response !== false ? (string) $response : null;
    }

    public function sendVerificationCode(string $phone, string $code, string $purpose = 'verification'): ?string
    {
        $message = match ($purpose) {
            'password', 'forgot_password', 'otp' => "Tasibul sifre degistirme kodunuz: {$code}. Bu kodu kimseyle paylasmayin.",
            default => "Tasibul dogrulama kodunuz: {$code}. Bu kodu kimseyle paylasmayin.",
        };

        return $this->sendOTP($phone, $message);
    }

    /**
     * Vatan SMS 1-N gönderim (/1toN) — bilgilendirme / normal mesajlar (OTP değil).
     *
     * @param  string|array<int, string>  $phones
     */
    public function send1toN(string|array $phones, string $message): ?string
    {
        $normalized = [];
        foreach ((array) $phones as $phone) {
            $p = $this->normalizePhone((string) $phone);
            if ($p !== '') {
                $normalized[] = $p;
            }
        }
        $normalized = array_values(array_unique($normalized));
        if ($normalized === []) {
            return null;
        }

        $ch = curl_init();

        $params = [
            'api_id' => config('sms.api_id'),
            'api_key' => config('sms.api_key'),
            'phones' => $normalized,
            'message' => $message,
            'sender' => $this->HEADER_TYPE,
            'message_content_type' => 'bilgi',
            'message_type' => 'normal',
        ];

        $curl_options = [
            CURLOPT_URL => config('sms.api_url').'/1toN',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
        ];

        curl_setopt_array($ch, $curl_options);
        $response = curl_exec($ch);
        curl_close($ch);

        Log::info('SMS response', [
            'response' => $response,
            'phones' => $params['phones'],
            'sender' => $params['sender'],
        ]);

        return $response !== false ? (string) $response : null;
    }

    /** Vatan SMS: 10 haneli, başında 0/90/+ olmadan (örn. 5061192539). */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '90')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '5')) {
            return $digits;
        }

        return '';
    }
}
