<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Önceden gezilen ürünler ve o zamandan beri fiyatı düşenler (anasayfa carousel'i).
 */
class ProductViewController extends Controller
{
    /** Kullanıcı başına tutulan en fazla kayıt; eskiler silinir */
    private const KEEP = 50;

    /** En son bakılan önce; price_dropped: bakıldığından beri ucuzladı mı */
    public function index(Request $request): JsonResponse
    {
        $views = ProductView::query()
            ->where('user_id', $request->user()->id)
            ->with(['product' => fn ($query) => $query->with('seller')->withStats()])
            ->latest('viewed_at')
            ->limit(20)
            ->get()
            ->filter(fn (ProductView $view) => $view->product !== null);

        return response()->json([
            'products' => $views->values()->map(fn (ProductView $view) => [
                ...(new ProductResource($view->product))->resolve($request),
                'viewed_price' => $view->viewed_price,
                'price_dropped' => $view->product->price < $view->viewed_price,
            ]),
        ]);
    }

    /** Ürün sayfası açıldığında: bakış zamanı ve o anki fiyat kaydedilir */
    public function store(Request $request, string $productId): Response
    {
        $product = Product::published()->findOrFail($productId);
        $userId = $request->user()->id;

        ProductView::updateOrCreate(
            ['user_id' => $userId, 'product_id' => $product->id],
            ['viewed_price' => $product->price, 'viewed_at' => now()],
        );

        // En son bakılan KEEP kayıt dışındakiler silinir
        $keep = ProductView::where('user_id', $userId)->latest('viewed_at')->limit(self::KEEP)->pluck('id');
        ProductView::where('user_id', $userId)->whereNotIn('id', $keep)->delete();

        return response()->noContent();
    }
}
