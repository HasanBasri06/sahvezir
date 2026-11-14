<?php

namespace App\Events;

use App\Models\ShipmentOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Aktif işin ilerlemesi değişti (araç sahibinin konumu): araç sahibi ve yük sahibinin user.{id} kanallarına gider;
 * aktif iş ekranı ve yük sahibinin güzergah şeridi aracı anlık hareket ettirir.
 */
class JobProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ShipmentOrder $order) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.(int) $this->order->buyer_user),
            new PrivateChannel('user.'.(int) $this->order->creater_user),
        ];
    }

    public function broadcastAs(): string
    {
        return 'job.progress';
    }

    public function broadcastWith(): array
    {
        return [
            'shipment_id' => (int) $this->order->shipment_id,
            'slug' => (string) ($this->order->shipment?->slug ?? ''),
            'progress' => $this->order->progress !== null ? (float) $this->order->progress : null,
            'current_city' => $this->order->current_city,
            // Aracın son konumu (İşlerin Durumu haritası)
            'lat' => $this->order->last_lat !== null ? (float) $this->order->last_lat : null,
            'lng' => $this->order->last_lng !== null ? (float) $this->order->last_lng : null,
        ];
    }
}
