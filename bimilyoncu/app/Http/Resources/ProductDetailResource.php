<?php

namespace App\Http\Resources;

use App\Models\ProductColor;
use App\Models\ProductImage;
use App\Models\ProductSpec;
use App\Models\Review;
use Illuminate\Http\Request;

/**
 * Ürün sayfası: özet + galeri, renkler, özellikler, açıklama, satıcı ve yorumlar.
 *
 * @mixin \App\Models\Product
 */
class ProductDetailResource extends ProductResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $seller = $this->seller;

        return [
            ...parent::toArray($request),
            'description' => $this->description ?? [],
            // Renge bağlı olmayan görseller; seçilen rengin kendi görseli yoksa bunlar gösterilir
            'images' => $this->images
                ->whereNull('product_color_id')
                ->map(fn (ProductImage $image) => $image->path)
                ->values(),
            'colors' => $this->colors->map(fn (ProductColor $color) => [
                'name' => $color->name,
                'hex' => $color->hex,
                'images' => $this->images
                    ->where('product_color_id', $color->id)
                    ->map(fn (ProductImage $image) => $image->path)
                    ->values(),
            ]),
            'specs' => $this->specs->map(fn (ProductSpec $spec) => [
                'label' => $spec->label,
                'value' => $spec->value,
            ]),
            'seller' => [
                'slug' => $seller->slug,
                'name' => $seller->name,
                'logo' => $seller->logo,
                'brand_color' => $seller->brand_color,
                // Satıcının tüm ürünlerine yapılan yorumlardan
                'rating' => round((float) $seller->reviews()->avg('rating'), 1),
                'review_count' => $seller->reviews()->count(),
                'shipping_days' => $seller->shipping_days,
            ],
            'reviews' => $this->reviews->map(fn (Review $review) => $review->toPublicArray($seller)),
        ];
    }
}
