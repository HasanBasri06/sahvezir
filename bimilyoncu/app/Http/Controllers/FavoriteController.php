<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Kullanıcının favori ürünleri (Favorilerim); isteğe bağlı olarak kendi kategorileri altında.
 */
class FavoriteController extends Controller
{
    /** Favori ürünler, son eklenen önce; her ürün hangi kategoride olduğuyla */
    public function index(Request $request): JsonResponse
    {
        $products = $request->user()->favoriteProducts()
            ->with('seller')
            ->withStats()
            ->orderByPivot('created_at', 'desc')
            ->get();

        return response()->json([
            'lists' => $this->lists($request),
            'products' => $products->map(fn (Product $product) => [
                ...(new ProductResource($product))->resolve($request),
                'favorite_list_id' => $product->pivot->favorite_list_id,
            ]),
        ]);
    }

    /** Favori ekleme modalındaki kategori seçenekleri */
    public function listIndex(Request $request): JsonResponse
    {
        return response()->json(['lists' => $this->lists($request)]);
    }

    /**
     * Ürünü favorilere ekler: kategorisiz, var olan bir kategoriye ya da yeni açılan bir kategoriye.
     * Ürün zaten favorideyse kategorisi güncellenir.
     */
    public function store(Request $request, string $productId): JsonResponse
    {
        $product = Product::published()->findOrFail($productId);
        $user = $request->user();

        $data = $request->validate([
            'list_id' => ['nullable', 'integer', Rule::exists('favorite_lists', 'id')->where('user_id', $user->id)],
            'new_list' => ['nullable', 'string', 'min:2', 'max:60'],
        ], [
            'list_id.exists' => 'Kategori bulunamadı.',
            'new_list.min' => 'Kategori adı en az 2 karakter olmalıdır.',
            'new_list.max' => 'Kategori adı en fazla 60 karakter olabilir.',
        ]);

        $listId = $data['list_id'] ?? null;
        if (! empty($data['new_list'])) {
            // Aynı adla açılmış kategori varsa ona eklenir
            $listId = $user->favoriteLists()->firstOrCreate(['name' => trim($data['new_list'])])->id;
        }

        $user->favoriteProducts()->syncWithoutDetaching([$product->id => ['favorite_list_id' => $listId]]);

        return $this->ids($request);
    }

    public function destroy(Request $request, string $productId): JsonResponse
    {
        $request->user()->favoriteProducts()->detach($productId);

        return $this->ids($request);
    }

    /** Kartlardaki kalplerin dolu/boş olması için yalnızca id listesi */
    public function ids(Request $request): JsonResponse
    {
        return response()->json(['ids' => $request->user()->favoriteProducts()->pluck('products.id')]);
    }

    /**
     * @return array<int, array{id: int, name: string, count: int}>
     */
    private function lists(Request $request): array
    {
        return $request->user()->favoriteLists()
            ->withCount('favorites')
            ->orderBy('name')
            ->get()
            ->map(fn ($list) => ['id' => $list->id, 'name' => $list->name, 'count' => $list->favorites_count])
            ->all();
    }
}
