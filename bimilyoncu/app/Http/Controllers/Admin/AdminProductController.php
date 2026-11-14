<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Katalog İşlemleri → Ürünler: sitedeki tüm ürünler (yayında olmayanlar dahil), 30'ar sayfa, kategoriye göre filtre.
 */
class AdminProductController extends Controller
{
    private const PER_PAGE = 30;

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Ana kategori, başlık ya da alt kategori; üst seviye seçilirse altındaki tüm ürünler
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $page = Product::query()
            ->with(['seller:id,name,slug,status', 'categories:id'])
            ->when(isset($data['category_id']), fn (Builder $query) => $query->whereHas(
                'categories',
                fn (Builder $categories) => $categories->whereIn('categories.id', Category::leafIdsUnder($data['category_id']))
            ))
            ->latest('created_at')
            ->orderBy('sort')
            ->paginate(self::PER_PAGE);

        return response()->json([
            'products' => collect($page->items())->map(fn (Product $product) => [
                'id' => $product->id,
                'code' => $product->code,
                'slug' => $product->slug,
                'name' => $product->name,
                'image' => $product->image,
                'price' => $product->price,
                'old_price' => $product->old_price,
                'stock' => $product->stock,
                'seller' => $product->seller?->only(['name', 'slug', 'status']),
                // Mağazası onaylı değilse (onay bekliyor, pasif) sitede görünmez
                'published' => $product->seller?->status === Seller::STATUS_APPROVED,
                'categories' => $product->categories->map(fn (Category $category) => Category::ancestry($category->id)->pluck('name')->implode(' › ')),
            ]),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
