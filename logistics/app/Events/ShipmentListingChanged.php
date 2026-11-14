<?php

namespace App\Events;

use App\Models\Shipment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Açık ilan listesi değişti: yeni ilan yayınlandı (`opened`) ya da açık ilan kapandı (`closed`: alındı, iptal,
 * silindi). Herkese açık `listings` kanalına gider; aktif iş ekranı "Yol Üzerindeki İlanlar"ı anlık yeniler.
 * Yalnızca zaten herkese açık ilan bilgileri (id, şehirler) gönderilir.
 */
class ShipmentListingChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $shipmentId,
        public string $action,
        public ?string $fromCity,
        public ?string $toCity,
    ) {}

    public static function fromShipment(Shipment $shipment, string $action): self
    {
        return new self((int) $shipment->id, $action, $shipment->f_where_city, $shipment->t_where_city);
    }

    public function broadcastOn(): array
    {
        return [new Channel('listings')];
    }

    public function broadcastAs(): string
    {
        return 'listing.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'shipment_id' => $this->shipmentId,
            'action' => $this->action,
            'from_city' => $this->fromCity,
            'to_city' => $this->toCity,
        ];
    }
}
