<?php

namespace App\Events;

use App\Models\Requests;
use App\Models\Shipment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Teklif kabul edildiğinde yük sahibi ve araç sahibinin user.{id} kanallarına gider;
 * mesajlar inactive olduğu için istemci listeyi/sohbeti anlık yeniler.
 */
class OfferAccepted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Shipment $shipment,
        public Requests $request
    ) {}

    public function broadcastOn(): array
    {
        $buyerId = (int) $this->request->user_id;
        $createrId = (int) $this->shipment->creater_id;

        return [
            new PrivateChannel('user.'.$buyerId),
            new PrivateChannel('user.'.$createrId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'offer.accepted';
    }

    public function broadcastWith(): array
    {
        $s = $this->shipment;
        $r = $this->request;

        return [
            'shipment_id' => (int) $s->id,
            'shipment_slug' => (string) $s->slug,
            'request_id' => (int) $r->id,
            'buyer_user_id' => (int) $r->user_id,
            'creater_id' => (int) $s->creater_id,
            'status' => (string) $s->status,
        ];
    }
}
