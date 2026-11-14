<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * Tüm ürünlerin özeti; kategori filtreleme ve sıralama önyüzde yapılır.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            // Onay bekleyen mağazaların ürünleri mağaza onaylanana kadar yayınlanmaz
            'products' => ProductResource::collection(
                Product::published()->with(['seller', 'categories:id'])->withStats()->orderBy('sort')->get()
            ),
        ]);
    }

    /**
     * Web'de Öne Çıkan Ürünler: son 30 günde en çok kişinin baktığı ürünler; eşitlikte çok satan önce.
     */
    public function trending(): JsonResponse
    {
        $products = Product::published()
            ->with(['seller', 'categories:id'])
            ->withStats()
            ->withCount(['views as recent_views' => fn ($query) => $query->where('viewed_at', '>=', now()->subDays(30))])
            ->orderByDesc('recent_views')
            ->orderByDesc('sold_count')
            ->limit(12)
            ->get();

        return response()->json(['products' => ProductResource::collection($products)]);
    }

    public function show(string $slug): JsonResponse
    {
        $product = Product::published()
            ->with(['seller', 'categories:id', 'colors', 'images', 'specs', 'reviews.images', 'reviews.replies'])
            ->withStats()
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json(['product' => new ProductDetailResource($product)]);
    }
}
