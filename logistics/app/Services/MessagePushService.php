<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MessagePushService
{
    public function __construct(
        private ExpoPushService $expoPush,
    ) {}

    /**
     * Yük sahibinden araç sahibine normal mesaj: 1. okunmamış → bildirim;
     * alıcı okumadan 4 mesaj daha (toplam 5) → ilandaki yoğun yanıt bildirimi.
     */
    public function notifyOnNormalMessage(Message $message): void
    {
        if (($message->type ?? 'normal') !== 'normal') {
            return;
        }

        $message->loadMissing(['sender', 'receiver', 'shipment']);

        $sender = $message->sender;
        $receiver = $message->receiver;

        if (! $sender || ! $receiver || (int) $sender->id === (int) $receiver->id) {
            return;
        }

        if (! $this->isCargoOwnerToVehicleOwner($sender, $receiver)) {
            return;
        }

        $senderId = (int) $message->sender_id;
        $receiverId = (int) $message->receiver_id;
        $shipmentId = $message->shipment_id ? (int) $message->shipment_id : null;

        /** Aynı ilan (varsa) ve gönderen bazında okunmamış normal mesaj sayısı */
        $unreadFromSender = $this->countUnreadNormalFromSender($senderId, $receiverId, $shipmentId);

        $shipment = $message->shipment;
        $slug = $shipment ? (string) $shipment->slug : '';
        $route = $shipment instanceof Shipment ? $this->shipmentRouteLabel($shipment) : null;

        $receiver->loadMissing('pushDevice');

        $baseLog = [
            'message_id' => $message->id,
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'shipment_id' => $shipmentId,
            'unread_from_sender' => $unreadFromSender,
            'receiver_has_push_row' => $receiver->pushDevice !== null,
            'receiver_push_status' => $receiver->pushDevice?->status,
            'receiver_has_token' => trim((string) ($receiver->pushDevice?->expo_push_token ?? '')) !== '',
        ];

        Log::info('push.message.evaluate', $baseLog);

        /** 1. okunmamış mesaj → yeni mesaj bildirimi (geçmiş sohbet olsa da) */
        if ($unreadFromSender === 1) {
            $this->sendFirstMessagePush($receiver, $sender, $message, $slug, $baseLog);

            return;
        }

        /** 5. okunmamış mesaj (1 + 4 ek) → ilandaki yoğun yanıt bildirimi */
        if ($unreadFromSender === 5) {
            $this->sendDemandRisingPush($receiver, $sender, $route, $slug, $shipmentId, $baseLog);
        }
    }

    private function isCargoOwnerToVehicleOwner(User $sender, User $receiver): bool
    {
        return ($sender->type ?? '') === 'cargo_owner'
            && ($receiver->type ?? '') === 'vehicle_owner';
    }

    private function countUnreadNormalFromSender(int $senderId, int $receiverId, ?int $shipmentId = null): int
    {
        $query = Message::query()
            ->where('sender_id', $senderId)
            ->where('receiver_id', $receiverId)
            ->where('status', 'accepted')
            ->where('is_read', false)
            ->where(function (Builder $q) {
                $q->where('type', 'normal')
                    ->orWhereNull('type');
            });

        if ($shipmentId !== null) {
            $query->where('shipment_id', $shipmentId);
        }

        return (int) $query->count();
    }

    private function sendFirstMessagePush(
        User $receiver,
        User $sender,
        Message $message,
        string $slug,
        array $baseLog,
    ): void {
        $senderName = trim((string) $sender->full_name) ?: 'Bir kullanıcı';
        $preview = Str::limit(trim((string) $message->message), 120);

        $body = $preview !== ''
            ? "{$senderName}: {$preview}"
            : "{$senderName} size mesaj gönderdi.";

        $this->dispatch(
            $receiver,
            'Yeni mesaj',
            $body,
            [
                'type' => 'message_received',
                'shipment_id' => $message->shipment_id ? (string) $message->shipment_id : '',
                'slug' => $slug,
                'sender_id' => (string) $sender->id,
                'sender_name' => trim((string) $sender->full_name),
            ],
            array_merge($baseLog, ['event' => 'message_first']),
            'push.message.first'
        );
    }

    private function sendDemandRisingPush(
        User $receiver,
        User $sender,
        ?string $route,
        string $slug,
        ?int $shipmentId,
        array $baseLog,
    ): void {
        $routeLabel = $route ?? 'İlanınız';
        $body = "{$routeLabel} ilandaki mesajınıza çok fazla yanıt geliyor.";

        $this->dispatch(
            $receiver,
            'Çok fazla yanıt',
            $body,
            [
                'type' => 'message_demand_rising',
                'shipment_id' => $shipmentId ? (string) $shipmentId : '',
                'slug' => $slug,
                'sender_id' => (string) $sender->id,
                'sender_name' => trim((string) $sender->full_name),
            ],
            array_merge($baseLog, ['event' => 'message_demand_rising']),
            'push.message.demand_rising'
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $logContext
     */
    private function dispatch(
        User $user,
        string $title,
        string $body,
        array $data,
        array $logContext,
        string $logPrefix,
    ): void {
        Log::info("{$logPrefix}.start", array_merge($logContext, [
            'user_id' => $user->id,
            'has_token' => $user->pushDevice !== null,
        ]));

        try {
            $sent = $this->expoPush->sendToUser($user, $title, $body, $data, $logContext);

            if ($sent) {
                Log::info("{$logPrefix}.success", array_merge($logContext, [
                    'user_id' => $user->id,
                    'title' => $title,
                ]));
            } else {
                Log::warning("{$logPrefix}.failed", array_merge($logContext, [
                    'user_id' => $user->id,
                ]));
            }
        } catch (\Throwable $e) {
            Log::error("{$logPrefix}.exception", array_merge($logContext, [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]));
        }
    }

    private function shipmentRouteLabel(Shipment $shipment): string
    {
        $from = trim((string) $shipment->f_where_city);
        $to = trim((string) $shipment->t_where_city);

        if ($from !== '' && $to !== '') {
            return "{$from} -> {$to}";
        }

        return 'İlanınız';
    }
}
