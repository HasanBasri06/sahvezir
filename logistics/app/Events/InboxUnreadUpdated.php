<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Okundu işaretleme veya gelen kutusu sayısı değişince — yalnızca `unread_count`, REST ile tetiklenmez.
 */
class InboxUnreadUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public int $unreadCount,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'inbox.unread.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'unread_count' => $this->unreadCount,
        ];
    }
}
