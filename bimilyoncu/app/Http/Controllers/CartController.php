<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\MergeCartRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartItemResource;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->cartResponse($request->user());
    }

    public function store(AddCartItemRequest $request): JsonResponse
    {
        $error = $this->addItem($request->user(), $request->validated());
        if ($error) {
            throw ValidationException::withMessages($error);
        }

        return $this->cartResponse($request->user());
    }

    public function update(UpdateCartItemRequest $request, string $key): JsonResponse
    {
        $request->user()->cartItems()
            ->where('item_key', $key)
            ->firstOrFail()
            ->update(['quantity' => $request->validated('quantity')]);

        return $this->cartResponse($request->user());
    }

    public function destroy(Request $request, string $key): JsonResponse
    {
        $request->user()->cartItems()->where('item_key', $key)->delete();

        return $this->cartResponse($request->user());
    }

    /**
     * Giriş yapmadan önce tarayıcıda tutulan sepeti kullanıcının sepetine ekler.
     * Artık satılmayan ürünler sessizce atlanır.
     */
    public function merge(MergeCartRequest $request): JsonResponse
    {
        DB::transaction(function () use ($request) {
            foreach ($request->validated('items') as $item) {
                $this->addItem($request->user(), $item);
            }
        });

        return $this->cartResponse($request->user());
    }

    /**
     * Ürün sepette varsa adedini arttırır; toplam adet üst sınırı ve stoğu geçemez.
     * Ürün bilgileri veritabanından alınır.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, string>|null Eklenemediyse alan => hata mesajı
     */
    private function addItem(User $user, array $item): ?array
    {
        // Yayında olmayan (mağazası onay bekleyen) ürün sepete eklenemez
        $product = Product::published()->with('colors.images')->find($item['product_id']);
        if (! $product) {
            return ['product_id' => 'Ürün bulunamadı.'];
        }
        if ($product->stock === 0) {
            return ['product_id' => 'Bu ürün tükendi.'];
        }

        $color = null;
        if (! empty($item['color'])) {
            $color = $product->colors->firstWhere('name', $item['color']);
            if (! $color) {
                return ['color' => 'Bu renk seçeneği bulunamadı.'];
            }
        }

        // Her renk sepette ayrı satırdır
        $key = $color ? "{$product->id}-{$color->name}" : $product->id;
        $cartItem = $user->cartItems()->firstOrNew(['item_key' => $key]);

        $cartItem->fill([
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'image' => $color?->images->first()?->path ?? $product->image,
            'variant' => $color ? "Renk: {$color->name}" : null,
            'quantity' => min(CartItem::MAX_QUANTITY, $product->stock, ($cartItem->quantity ?? 0) + $item['quantity']),
        ])->save();

        return null;
    }

    private function cartResponse(User $user): JsonResponse
    {
        return response()->json([
            'items' => CartItemResource::collection($user->cartItems()->with('product')->oldest('id')->get()),
            'max_quantity' => CartItem::MAX_QUANTITY,
        ]);
    }
}
