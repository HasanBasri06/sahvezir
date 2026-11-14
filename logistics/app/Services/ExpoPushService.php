<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushService
{
    private const PUSH_URL = 'https://exp.host/--/api/v2/push/send';

    /** Mobil `push-notifications.ts` / app.json `defaultChannel` ile aynı olmalı */
    public const ANDROID_CHANNEL_ID = 'default';

    /**
     * Kullanıcının aktif cihazındaki Expo push token'ına bildirim gönderir.
     *
     * @param  array<string, mixed>  $data  Expo notification data payload
     * @param  array<string, mixed>  $logContext  Canlı log takibi (shipment_id, event, vb.)
     */
    public function sendToUser(User $user, string $title, string $body, array $data = [], array $logContext = []): bool
    {
        $base = array_merge([
            'user_id' => $user->id,
            'user_status' => $user->status,
            'push_type' => $data['type'] ?? null,
        ], $logContext);

        if (! $user->isActiveAccount()) {
            Log::info('push.expo.skip_inactive_user — hesap active değil; bildirim gönderilmedi', $base);

            return false;
        }

        $user->loadMissing('pushDevice');
        $device = $user->pushDevice;

        $base = array_merge($base, [
            'user_device_id' => $device?->id,
            'platform' => $device?->platform,
        ]);

        if (! $device) {
            Log::warning('push.expo.skip_no_token — user_devices kaydı yok; alıcı mobilde giriş yapıp bildirim izni vermeli', array_merge($base, [
                'action' => 'Mobil: giriş → izin ver → POST /notifications/register-token',
            ]));

            return false;
        }

        if ($device->status === 'deny' || trim((string) $device->expo_push_token) === '') {
            Log::info('push.expo.skip_denied_or_empty', array_merge($base, [
                'device_status' => $device->status,
            ]));

            return false;
        }

        return $this->sendToDevice($device, $title, $body, $data, $base);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $base
     */
    public function sendToDevice(UserDevice $device, string $title, string $body, array $data = [], array $base = []): bool
    {
        $device->loadMissing('user');
        if ($device->user && ! $device->user->isActiveAccount()) {
            Log::info('push.expo.skip_inactive_user — hesap active değil; bildirim gönderilmedi', array_merge($base, [
                'user_id' => $device->user_id,
                'user_status' => $device->user->status,
                'user_device_id' => $device->id,
            ]));

            return false;
        }

        $token = trim((string) $device->expo_push_token);

        if ($token === '') {
            Log::warning('push.expo.skip_no_token', array_merge($base, [
                'user_device_id' => $device->id,
            ]));

            return false;
        }

        if (! $this->isValidExpoToken($token)) {
            Log::warning('push.expo.skip_invalid_token', array_merge($base, [
                'user_device_id' => $device->id,
                'token_prefix' => substr($token, 0, 24),
                'token_length' => strlen($token),
            ]));

            return false;
        }

        $base = array_merge([
            'user_id' => $device->user_id,
            'user_device_id' => $device->id,
            'platform' => $device->platform,
        ], $base);

        $base['token_fingerprint'] = $this->tokenFingerprint($token);

        $message = [
            'to' => $token,
            'title' => $title,
            'body' => $body,
            'sound' => 'default',
        ];

        if ($device->platform === 'android') {
            $message['channelId'] = self::ANDROID_CHANNEL_ID;
            $message['priority'] = 'high';
        }

        if ($data !== []) {
            $message['data'] = $data;
        }

        Log::info('push.expo.send_attempt', array_merge($base, [
            'title' => $title,
            'body_length' => strlen($body),
        ]));

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->post(self::PUSH_URL, $message);

            $json = $response->json();

            if (! $response->successful()) {
                Log::error('push.expo.http_error', array_merge($base, [
                    'http_status' => $response->status(),
                    'response_body' => $json,
                    'response_raw' => $response->body(),
                ]));

                return false;
            }

            $ticket = $json['data'][0] ?? null;

            if (is_array($ticket) && ($ticket['status'] ?? '') === 'error') {
                Log::error('push.expo.ticket_error', array_merge($base, [
                    'ticket_status' => $ticket['status'] ?? null,
                    'ticket_message' => $ticket['message'] ?? null,
                    'ticket_details' => $ticket['details'] ?? null,
                    'ticket_id' => $ticket['id'] ?? null,
                    'full_ticket' => $ticket,
                    'full_response' => $json,
                ]));

                return false;
            }

            Log::info('push.expo.send_ok — bildirim Expo\'ya başarıyla iletildi', array_merge($base, [
                'success' => true,
                'title' => $title,
                'body' => $body,
                'ticket_status' => is_array($ticket) ? ($ticket['status'] ?? null) : null,
                'ticket_id' => is_array($ticket) ? ($ticket['id'] ?? null) : null,
            ]));

            return true;
        } catch (\Throwable $e) {
            Log::error('push.expo.exception', array_merge($base, [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]));

            return false;
        }
    }

    private function isValidExpoToken(string $token): bool
    {
        return str_starts_with($token, 'ExponentPushToken[')
            || str_starts_with($token, 'ExpoPushToken[');
    }

    private function tokenFingerprint(string $token): string
    {
        return substr(hash('sha256', $token), 0, 12);
    }
}
