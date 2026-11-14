<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDevice;

class UserDeviceService
{
    /**
     * Giriş / bildirim izni sonrası: kullanıcının tüm cihaz kayıtlarını sil, ardından güncel token ekle.
     */
    public function register(User $user, string $expoPushToken, string $platform): UserDevice
    {
        $this->clearAllForUser($user);

        return UserDevice::create([
            'user_id' => $user->id,
            'platform' => $platform,
            'expo_push_token' => trim($expoPushToken),
            'status' => 'active',
        ]);
    }

    /** Bildirim izni reddedildi — önce tüm kayıtları sil, ardından deny satırı ekle. */
    public function registerDenied(User $user): UserDevice
    {
        $this->clearAllForUser($user);

        return UserDevice::create([
            'user_id' => $user->id,
            'platform' => null,
            'expo_push_token' => null,
            'status' => 'deny',
        ]);
    }

    /** Bildirim izni kapatıldığında ilgili kullanıcının tüm user_devices satırlarını kalıcı siler. */
    public function clearAllForUser(User $user): int
    {
        return UserDevice::query()->where('user_id', $user->id)->delete();
    }
}
