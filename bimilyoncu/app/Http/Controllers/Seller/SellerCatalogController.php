<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Ürün Kataloğum: satıcının ürün geliştirdiği alt kategoriler (satıcı–kategori ara tablosu).
 */
class SellerCatalogController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['category_ids' => $request->user()->seller->categories()->pluck('category_id')]);
    }

    /**
     * Katalogdan çıkarılan alt kategorideki ürünleri başka bir alt kategoriye aktarır.
     */
    public function moveProducts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:500'],
            'product_ids.*' => ['string', 'distinct'],
            // Katalogdan çıkarılan alt kategoriler; ürünler bunlardan çıkıp hedefe geçer
            'from_category_ids' => ['required', 'array', 'min:1'],
            'from_category_ids.*' => ['integer'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! Category::find($value)?->isLeaf()) {
                        $fail('Ürünler yalnızca bir alt kategoriye aktarılabilir.');
                    }
                },
            ],
        ], [
            'product_ids.required' => 'Aktarılacak ürün yok.',
            'category_id.required' => 'Ürünlerin aktarılacağı alt kategoriyi seç.',
        ]);

        // Yalnızca satıcının kendi ürünleri
        $products = $request->user()->seller->products()->whereIn('id', $data['product_ids'])->get();
        DB::transaction(function () use ($products, $data) {
            foreach ($products as $product) {
                $product->categories()->detach($data['from_category_ids']);
                $product->categories()->syncWithoutDetaching([$data['category_id']]);
                if (in_array($product->category_id, $data['from_category_ids'], true)) {
                    $product->update(['category_id' => $data['category_id']]);
                }
            }
        });

        return response()->json(['moved' => $products->count()]);
    }

    /** Seçilen alt kategoriler kataloğun tamamı olur; listede olmayanlar çıkarılır */
    public function update(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'category_ids' => ['required', 'array', 'min:1', 'max:500'],
            'category_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('categories', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! Category::find($value)?->isLeaf()) {
                        $fail('Yalnızca alt kategoriler seçilebilir.');
                    }
                },
            ],
        ], [
            'category_ids.required' => 'Kataloğun için en az bir alt kategori seç.',
            'category_ids.min' => 'Kataloğun için en az bir alt kategori seç.',
        ])['category_ids'];

        $seller = $request->user()->seller;

        // İçinde ürün kalan alt kategori katalogdan çıkarılamaz; önce ürünler başka alt kategoriye aktarılır
        $stillUsed = DB::table('category_product')
            ->whereIn('product_id', $seller->products()->select('id'))
            ->whereNotIn('category_id', $ids)
            ->pluck('category_id')
            ->unique();
        if ($stillUsed->isNotEmpty()) {
            $names = Category::whereIn('id', $stillUsed)->pluck('name')->implode(', ');
            throw ValidationException::withMessages([
                'category_ids' => "{$names} kategorisinde ürünlerin var; önce ürünleri başka bir alt kategoriye aktar.",
            ]);
        }

        DB::transaction(function () use ($seller, $ids) {
            $seller->categories()->whereNotIn('category_id', $ids)->delete();
            foreach ($ids as $id) {
                $seller->addCategory($id);
            }
        });

        return response()->json(['category_ids' => $seller->categories()->pluck('category_id')]);
    }
}
