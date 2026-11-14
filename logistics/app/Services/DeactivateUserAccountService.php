<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\Message;
use App\Models\Requests;
use App\Models\Shipment;
use App\Models\User;
use App\Models\UserDetail;
use App\Models\UserDevice;
use Illuminate\Support\Facades\DB;

class DeactivateUserAccountService
{
    /**
     * Hesap silme: kullanıcıyı pasife alır; ilişkili kayıtları günceller veya siler.
     */
    public function deactivate(User $user): void
    {
        $userId = (int) $user->id;

        DB::transaction(function () use ($user, $userId) {
            Message::query()
                ->where(function ($query) use ($userId) {
                    $query->where('sender_id', $userId)
                        ->orWhere('receiver_id', $userId);
                })
                ->update(['status' => 'inactive']);

            Requests::query()
                ->where('user_id', $userId)
                ->update(['status' => 'cancelled']);

            Shipment::query()
                ->where('creater_id', $userId)
                ->update(['status' => 'inactive']);

            UserDetail::query()->where('user_id', $userId)->delete();
            UserDevice::query()->where('user_id', $userId)->delete();

            $user->tokens()->delete();

            $user->update([
                'status' => UserStatus::INACTIVE,
            ]);
        });
    }
}
