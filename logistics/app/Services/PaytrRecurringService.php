<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PayTR kayıtlı kart (CAPI) ve tekrarlayan ödeme.
 *
 * - İlk ödemede `store_card=1` (+ varsa `utoken`) gönderilir; PayTR bildiriminde dönen `utoken` kullanıcıya kaydedilir.
 * - Yenilemede CAPI LIST ile kartın `ctoken`'ı alınır ve `non_3d=1`, `recurring_payment=1` ile kullanıcı etkileşimi
 *   olmadan çekim yapılır (https://dev.paytr.com/direkt-api/kart-saklama-api/kayitli-kart-tekrarlayan-odeme).
 * - Mağazada Non3D ve tekrarlayan ödeme yetkisi PayTR tarafından açılmış olmalıdır.
 */
class PaytrRecurringService
{
    /**
     * Kullanıcının kayıtlı kartları (boş dizi = kart yok / hata).
     *
     * @return list<array<string, mixed>>
     */
    public function listCards(string $utoken): array
    {
        $token = base64_encode(hash_hmac('sha256', $utoken.$this->salt(), $this->key(), true));

        try {
            $res = Http::asForm()
                ->withOptions(['verify' => (bool) config('paytr.verify_ssl', true)])
                ->timeout(30)
                ->post($this->baseUrl().'/odeme/capi/list', [
                    'merchant_id' => $this->merchantId(),
                    'utoken' => $utoken,
                    'paytr_token' => $token,
                ]);
        } catch (\Throwable $e) {
            Log::warning('paytr.capi_list.failed', ['error' => $e->getMessage()]);

            return [];
        }

        $data = $res->json();
        if (! is_array($data) || ($data['status'] ?? null) === 'error') {
            Log::warning('paytr.capi_list.error', ['response' => is_array($data) ? ($data['err_msg'] ?? null) : $res->body()]);

            return [];
        }

        // Tek kart nesne, birden fazla kart liste olarak dönebilir
        if (isset($data['ctoken'])) {
            return [$data];
        }

        return array_values(array_filter($data, fn ($c) => is_array($c) && isset($c['ctoken'])));
    }

    /**
     * Kayıtlı karttan otomatik çekim.
     *
     * @return array{status: 'success'|'wait_callback'|'failed', message: string, try_again: bool}
     */
    public function charge(User $user, string $merchantOid, float $amount, string $basketName): array
    {
        $utoken = (string) $user->paytr_utoken;
        if ($utoken === '') {
            return ['status' => 'failed', 'message' => 'Kayıtlı kart bulunamadı.', 'try_again' => false];
        }

        $cards = $this->listCards($utoken);
        // CVV isteyen kartlarla kullanıcı olmadan çekim yapılamaz
        $card = collect($cards)->first(fn ($c) => (string) ($c['require_cvv'] ?? '0') !== '1') ?? null;
        if (! $card) {
            return ['status' => 'failed', 'message' => 'Otomatik ödemeye uygun kayıtlı kart bulunamadı.', 'try_again' => false];
        }

        $userIp = trim((string) config('paytr.user_ip_override', '')) ?: (string) (request()?->ip() ?? '127.0.0.1');
        $email = (string) $user->email;
        $amountStr = $this->formatAmount($amount);
        $paymentType = 'card';
        $installment = '0';
        $currency = 'TL';
        $testMode = (string) config('paytr.test_mode', '1');
        $non3d = '1';

        $hashStr = $this->merchantId().$userIp.$merchantOid.$email.$amountStr.$paymentType.$installment.$currency.$testMode.$non3d;
        $paytrToken = base64_encode(hash_hmac('sha256', $hashStr.$this->salt(), $this->key(), true));

        $apiBase = rtrim((string) config('paytr.api_url'), '/');

        try {
            $res = Http::asForm()
                ->withOptions(['verify' => (bool) config('paytr.verify_ssl', true)])
                ->timeout(60)
                ->post($this->baseUrl().(string) config('paytr.direct_api_path', '/odeme'), [
                    'merchant_id' => $this->merchantId(),
                    'paytr_token' => $paytrToken,
                    'user_ip' => $userIp,
                    'merchant_oid' => $merchantOid,
                    'email' => $email,
                    'payment_type' => $paymentType,
                    'payment_amount' => $amountStr,
                    'installment_count' => $installment,
                    'currency' => $currency,
                    'test_mode' => $testMode,
                    'non_3d' => $non3d,
                    'merchant_ok_url' => $apiBase.'/api/payment/return/ok?merchant_oid='.urlencode($merchantOid),
                    'merchant_fail_url' => $apiBase.'/api/payment/return/fail?merchant_oid='.urlencode($merchantOid),
                    'user_name' => mb_substr((string) $user->full_name ?: 'TasiBul Uyesi', 0, 60),
                    'user_address' => 'Türkiye',
                    'user_phone' => (string) ($user->phone ?? ''),
                    'user_basket' => json_encode([[$basketName, $amountStr, 1]], JSON_UNESCAPED_UNICODE),
                    'client_lang' => 'tr',
                    'debug_on' => (string) config('paytr.debug_on', '0'),
                    'utoken' => $utoken,
                    'ctoken' => (string) $card['ctoken'],
                    'recurring_payment' => '1',
                ]);
        } catch (\Throwable $e) {
            Log::warning('paytr.recurring.http_failed', ['merchant_oid' => $merchantOid, 'error' => $e->getMessage()]);

            return ['status' => 'failed', 'message' => 'Ödeme sağlayıcısına ulaşılamadı.', 'try_again' => true];
        }

        $data = $res->json();
        $status = is_array($data) ? (string) ($data['status'] ?? '') : '';

        Log::info('paytr.recurring.response', ['merchant_oid' => $merchantOid, 'status' => $status]);

        if ($status === 'success' || $status === 'wait_callback') {
            return ['status' => $status, 'message' => '', 'try_again' => false];
        }

        return [
            'status' => 'failed',
            'message' => is_array($data) ? (string) ($data['err_msg'] ?? $data['reason'] ?? 'Ödeme alınamadı.') : 'Ödeme alınamadı.',
            // Kart kapatılmışsa PayTR tekrar denenmemesini söyler
            'try_again' => is_array($data) ? filter_var($data['try_again'] ?? true, FILTER_VALIDATE_BOOLEAN) : true,
        ];
    }

    private function merchantId(): string
    {
        return (string) config('paytr.store_no');
    }

    private function key(): string
    {
        return (string) config('paytr.store_key');
    }

    private function salt(): string
    {
        return (string) config('paytr.store_secret_key');
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('paytr.url'), '/');
    }

    private function formatAmount(float $amount): string
    {
        return fmod($amount, 1.0) === 0.0 ? (string) (int) $amount : number_format($amount, 2, '.', '');
    }
}
