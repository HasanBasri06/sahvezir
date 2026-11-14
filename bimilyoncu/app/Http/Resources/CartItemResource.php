<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CartItem
 */
class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->item_key,
            'product_id' => $this->product_id,
            // Güncel ad ve fiyat üründen gelir; ürün kaldırıldıysa eklendiği andaki bilgiler gösterilir
            'name' => $this->product?->name ?? $this->name,
            'price' => $this->product?->price ?? $this->price,
            'image' => $this->image,
            'variant' => $this->variant,
            'quantity' => $this->quantity,
        ];
    }
}
