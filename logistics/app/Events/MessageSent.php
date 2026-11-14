<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message
    ) {}

    public function broadcastOn(): array
    {
        $m = $this->message;
        $channels = [
            new PrivateChannel('user.'.$m->receiver_id),
        ];
        /** Sistem / teklif (request): hem alıcı hem gönderen anlık görsün */
        $type = (string) ($m->type ?? 'normal');
        if (in_array($type, ['system', 'request'], true) && (int) $m->sender_id !== (int) $m->receiver_id) {
            $channels[] = new PrivateChannel('user.'.$m->sender_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        $m = $this->message;
        /** Mobil rozet: alıcı için güncel okunmamış sayısı (Pusher ile anlık senkron) */
        $unreadForReceiver = (int) Message::query()
            ->where('receiver_id', $m->receiver_id)
            ->where('is_read', false)
            ->where('status', 'accepted')
            ->count();

        return [
            'id' => $m->id,
            'message' => $m->message,
            'sender_message' => $m->sender_message,
            'receiver_message' => $m->receiver_message,
            'sender_id' => $m->sender_id,
            'receiver_id' => $m->receiver_id,
            'shipment_id' => $m->shipment_id,
            'type' => $m->type ?? 'normal',
            'created_at' => $m->created_at?->toIso8601String(),
            'unread_count' => $unreadForReceiver,
        ];
    }
}
