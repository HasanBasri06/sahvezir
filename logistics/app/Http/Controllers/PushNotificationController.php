<?php

namespace App\Http\Controllers;

use App\Services\ExpoPushService;
use App\Services\UserDeviceService;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PushNotificationController extends Controller
{
    use ResponseTrait;

    public function __construct(
        private readonly ExpoPushService $expoPush,
        private readonly UserDeviceService $userDeviceService,
    ) {}

    /**
     * Mobil uygulamadan gelen Expo push token kaydı.
     */
    public function registerToken(Request $request)
    {
        try {
            $validated = $request->validate([
                'expo_push_token' => 'nullable|string|max:255',
                'platform' => 'nullable|string|in:ios,android',
                'status' => 'nullable|string|in:active,deny,inactive',
            ]);
        } catch (ValidationException $e) {
            Log::warning('push.register_token.validation_failed', [
                'errors' => $e->errors(),
                'user_id' => $request->user()?->id,
            ]);
            throw $e;
        }

        $user = $request->user();
        if (! $user) {
            Log::warning('push.register_token.unauthorized', [
                'ip' => $request->ip(),
            ]);
            return $this->error([], 'Yetkisiz.', 'Unauthenticated', 401);
        }

        if (($validated['status'] ?? 'active') === 'deny') {
            $device = $this->userDeviceService->registerDenied($user);

            Log::info('push.register_token.denied', [
                'user_id' => $user->id,
                'user_device_id' => $device->id,
                'status' => $device->status,
            ]);

            return $this->success([], 'Bildirim izni reddedildi olarak kaydedildi.');
        }

        $tokenValidated = validator($request->all(), [
            'expo_push_token' => 'required|string|max:255',
            'platform' => 'required|string|in:ios,android',
        ], [
            'expo_push_token.required' => 'Push token zorunludur.',
            'platform.required' => 'Platform zorunludur.',
            'platform.in' => 'Platform ios veya android olmalıdır.',
        ])->validate();

        $device = $this->userDeviceService->register(
            $user,
            $tokenValidated['expo_push_token'],
            $tokenValidated['platform'],
        );

        Log::info('push.register_token.saved', [
            'user_id' => $user->id,
            'user_device_id' => $device->id,
            'platform' => $tokenValidated['platform'],
            'status' => $device->status,
            'token_fingerprint' => $this->tokenFingerprint($tokenValidated['expo_push_token']),
            'token_length' => strlen((string) $tokenValidated['expo_push_token']),
        ]);

        return $this->success([], 'Push token kaydedildi.');
    }

    /**
     * Bildirim izni verilmediğinde — kullanıcının tüm user_devices kayıtları silinir.
     */
    public function clearDevice(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return $this->error([], 'Yetkisiz.', 'Unauthenticated', 401);
        }

        $deleted = $this->userDeviceService->clearAllForUser($user);

        Log::info('push.clear_device', [
            'user_id' => $user->id,
            'deleted' => $deleted,
        ]);

        return $this->success(['deleted' => $deleted], 'Push kayıtları silindi.');
    }

    /**
     * Yalnızca test: bildirim giriş yapan kullanıcının kendi telefonuna gider.
     * Teklif bildirimi buradan değil — ShipmentController::sendOfferPushToCargoOwner (ilan sahibine).
     */
    public function sendNotification(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:255',
        ]);

        $user = $request->user();
        if (! $user) {
            return $this->error([], 'Yetkisiz.', 'Unauthenticated', 401);
        }

        $sent = $this->expoPush->sendToUser(
            $user,
            $validated['title'],
            $validated['body'],
            [],
            ['event' => 'manual_test', 'source' => 'send_notification_endpoint']
        );

        $user->loadMissing('pushDevice');
        $device = $user->pushDevice;

        if (! $sent) {
            Log::error('push.test_send.failed', [
                'user_id' => $user->id,
                'has_token' => $device !== null,
                'platform' => $device?->platform,
                'hint' => 'push.expo.* loglarina bakin',
            ]);

            return $this->error(
                [],
                'Push token kayıtlı değil veya bildirim gönderilemedi.',
                ['expo_push_token' => ['Kayıtlı push token yok veya Expo hatası.']],
                422
            );
        }

        Log::info('push.test_send.ok — test bildirimi başarıyla gönderildi', [
            'user_id' => $user->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
        ]);

        return $this->success([], 'Bildirim başarıyla gönderildi.');
    }

    private function tokenFingerprint(string $token): string
    {
        return substr(hash('sha256', $token), 0, 12);
    }
}
