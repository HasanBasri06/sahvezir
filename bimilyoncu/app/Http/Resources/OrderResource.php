<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Order
 */
class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'vat' => $this->vat,
            'shipping' => $this->shipping,
            'total' => $this->total,
            'shipping_address' => $this->shipping_address,
            'card_brand' => $this->card_brand,
            'card_last4' => $this->card_last4,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'items' => $this->items->map(fn (OrderItem $item) => $item->toApiArray()),
        ];
    }
}
