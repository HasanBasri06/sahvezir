<?php

namespace App\Events;

use App\Models\Requests;
use Carbon\Carbon;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RequestSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Requests $request
    ) {}

    public function broadcastOn(): array
    {
        $shipment = $this->request->shipment;
        $receiverId = $shipment ? (int) $shipment->creater_id : null;
        if (!$receiverId) {
            return [];
        }
        return [
            new PrivateChannel('user.' . $receiverId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'offer.sent';
    }

    private static function toIso8601(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }
        return Carbon::parse($value)->format(\DateTimeInterface::ATOM);
    }

    public function broadcastWith(): array
    {
        $req = $this->request->loadMissing(['car', 'shipment']);
        $shipment = $req->shipment;
        $priceRaw = $req->getRawOriginal('price') ?? $req->price;
        $priceFormatted = is_numeric($priceRaw) ? number_format((float) $priceRaw, 2, ',', '.') . ' ₺' : (string) $req->price;

        return [
            'id' => $req->id,
            'user_id' => $req->user_id,
            'sender_id' => $req->user_id,
            'receiver_id' => $shipment ? (int) $shipment->creater_id : null,
            'shipment_id' => $req->shipment_id,
            'shipment_slug' => $shipment?->slug,
            'car_name' => $req->car?->name ?? '',
            'price' => $priceFormatted,
            'message' => $req->message ?? '',
            'created_at' => self::toIso8601($req->getRawOriginal('created_at')),
            'created_at_raw' => $req->getRawOriginal('created_at'),
            'status' => $req->status ?? 'pending',
        ];
    }
}
