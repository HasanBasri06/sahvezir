<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Liste ve kartlar için ürün özeti.
 *
 * @mixin \App\Models\Product
 */
class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Sistemin verdiği 10 karakterlik ürün kodu
            'code' => $this->code,
            'slug' => $this->slug,
            'name' => $this->name,
            'price' => $this->price,
            'old_price' => $this->old_price,
            'stock' => $this->stock,
            'image' => $this->image,
            'rating' => round((float) ($this->reviews_avg_rating ?? 0), 1),
            'review_count' => (int) ($this->reviews_count ?? 0),
            'sold_count' => (int) ($this->sold_count ?? 0),
            'path' => $this->categoryPath(),
            // Çoklu kategori: ürün bu yolların hepsindeki kategori sayfalarında listelenir
            'paths' => $this->categoryPaths(),
            'category_id' => $this->category_id,
            'category_ids' => $this->categories->pluck('id'),
            'featured' => $this->featured,
            'ai_images' => $this->ai_images,
            // Kartta ürün adının üstünde satıcının kendi renginde gösterilir
            'seller' => $this->whenLoaded('seller', fn () => [
                'slug' => $this->seller->slug,
                'name' => $this->seller->name,
                'brand_color' => $this->seller->brand_color,
            ]),
        ];
    }
}
