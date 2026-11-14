<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Seller;
use Illuminate\Http\JsonResponse;

/**
 * Bir ana kategori için ürün geliştiren satıcılar (seller_categories); kategori sayfasındaki satıcı filtresi.
 */
class CategorySellerController extends Controller
{
    public function __invoke(string $categorySlug): JsonResponse
    {
        // Kataloğunda bu ana kategorinin alt kategorilerinden en az biri olan satıcılar
        $categoryId = Category::idForPath([$categorySlug]);
        $leafIds = $categoryId ? Category::leafIdsUnder($categoryId) : [];

        $sellers = Seller::query()
            ->where('status', Seller::STATUS_APPROVED)
            ->whereHas('categories', fn ($query) => $query->whereIn('category_id', $leafIds))
            ->orderBy('name')
            ->get(['slug', 'name', 'brand_color']);

        return response()->json(['sellers' => $sellers]);
    }
}
