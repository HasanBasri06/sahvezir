<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use Illuminate\Http\JsonResponse;

/**
 * Mağazalar: anasayfadaki firma şeridi ve mağaza sayfası. Yalnızca onaylı (aktif) mağazalar.
 */
class SellerController extends Controller
{
    /** Anasayfa: yayında ürünü olan onaylı mağazalardan rastgele 10 tanesi */
    public function index(): JsonResponse
    {
        $sellers = Seller::query()
            ->where('status', Seller::STATUS_APPROVED)
            ->whereHas('products')
            ->withCount('products')
            ->inRandomOrder()
            ->limit(10)
            ->get(['id', 'slug', 'name', 'logo', 'brand_color']);

        return response()->json([
            'sellers' => $sellers->map(fn (Seller $seller) => [
                ...$seller->only(['slug', 'name', 'logo', 'brand_color']),
                'product_count' => $seller->products_count,
            ]),
        ]);
    }

    /** Mağaza sayfasının başlığı; pasif ya da onay bekleyen mağaza bulunamaz */
    public function show(string $slug): JsonResponse
    {
        $seller = Seller::query()
            ->where('status', Seller::STATUS_APPROVED)
            ->where('slug', $slug)
            ->firstOrFail(['slug', 'name', 'logo', 'brand_color']);

        return response()->json(['seller' => $seller]);
    }
}
