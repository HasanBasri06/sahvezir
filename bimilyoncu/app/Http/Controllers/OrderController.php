<?php

namespace App\Http\Controllers;

use App\Events\SaleCreated;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'orders' => OrderResource::collection($request->user()->orders()->with('items.product')->latest('id')->get()),
        ]);
    }

    public function show(Request $request, string $number): JsonResponse
    {
        $order = $request->user()->orders()->with('items.product')->where('number', $number)->firstOrFail();

        return response()->json(['order' => new OrderResource($order)]);
    }

    /**
     * Sepetten sipariş oluşturur.
     *
     * TODO: Ödeme kuruluşu bağlanınca ödeme onayı buradan önce alınmalı; şimdilik ödeme başarılı sayılır.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $user = $request->user();

        $order = DB::transaction(function () use ($request, $user) {
            $cartItems = $user->cartItems()->with('product')->lockForUpdate()->get()
                // Artık satılmayan ürünler siparişe girmez
                ->filter(fn (CartItem $item) => $item->product !== null);

            if ($cartItems->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Sepetin boş.']);
            }

            // Aynı ürünün farklı renkleri ayrı satırlardır; stok ürün bazında kontrol edilir
            foreach ($cartItems->groupBy('product_id') as $items) {
                $product = $items->first()->product;
                if ($items->sum('quantity') > $product->stock) {
                    throw ValidationException::withMessages([
                        'cart' => $product->stock === 0
                            ? "{$product->name} tükendi, sepetinden çıkar."
                            : "{$product->name} için stokta yalnızca {$product->stock} adet var.",
                    ]);
                }
            }

            // Fiyatlar sepetteki kayıttan değil, ürünün güncel fiyatından hesaplanır
            $lines = $cartItems->map(fn (CartItem $item) => [
                'product_id' => $item->product_id,
                'name' => $item->product->name,
                'variant' => $item->variant,
                'image' => $item->image,
                'unit_price' => $item->product->price,
                // Kâr hesabı için satış anındaki maliyet
                'unit_cost' => $item->product->cost,
                'quantity' => $item->quantity,
                'line_total' => round($item->product->price * $item->quantity, 2),
            ]);

            $subtotal = round($lines->sum('line_total'), 2);
            $vat = round($subtotal * config('shop.vat_rate'), 2);
            $shipping = $subtotal >= config('shop.free_shipping_limit') ? 0.0 : config('shop.shipping_fee');

            $order = $user->orders()->create([
                'number' => $this->newOrderNumber(),
                'status' => Order::STATUS_PAID,
                'subtotal' => $subtotal,
                'vat' => $vat,
                'shipping' => $shipping,
                'total' => round($subtotal + $vat + $shipping, 2),
                'shipping_address' => $this->addressSnapshot(Address::with(['city', 'district'])->findOrFail($request->validated('address_id'))),
                'card_brand' => $request->validated('card_brand'),
                'card_last4' => $request->validated('card_last4'),
                'paid_at' => now(),
            ]);
            $order->items()->createMany($lines->all());

            foreach ($cartItems->groupBy('product_id') as $items) {
                $items->first()->product->decrement('stock', $items->sum('quantity'));
            }

            $user->cartItems()->delete();

            return $order;
        });

        $this->notifySellers($order);

        return response()->json(['order' => new OrderResource($order->load('items.product'))], 201);
    }

    /**
     * Her satıcıya kendi ürünlerinin satıldığını anlık bildirir (Pusher).
     * Bildirim gönderilemese de sipariş geçerlidir; panel ayrıca düzenli sorguyla da yakalar.
     */
    private function notifySellers(Order $order): void
    {
        $order->load('items.product:id,seller_id');

        foreach ($order->items->groupBy(fn (OrderItem $item) => $item->product?->seller_id) as $sellerId => $items) {
            if (! $sellerId) {
                continue;
            }
            try {
                SaleCreated::dispatch((int) $sellerId, $order, $items);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * Teslim edilen ürün için, teslimattan sonraki 14 gün içinde iade talebi.
     */
    public function requestReturn(Request $request, string $number, int $itemId): JsonResponse
    {
        $order = $request->user()->orders()->where('number', $number)->firstOrFail();
        $item = $order->items()->findOrFail($itemId);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'İade nedenini yazın.',
            'reason.min' => 'İade nedeni en az 5 karakter olmalıdır.',
        ]);

        if (! $item->canBeReturned()) {
            throw ValidationException::withMessages([
                'reason' => $item->status === OrderItem::STATUS_DELIVERED
                    ? 'İade süresi ('.OrderItem::RETURN_DAYS.' gün) doldu.'
                    : 'Yalnızca teslim edilen ürünler iade edilebilir.',
            ]);
        }

        $item->update([
            'status' => OrderItem::STATUS_RETURN_REQUESTED,
            'return_reason' => $data['reason'],
            'return_requested_at' => now(),
        ]);

        return response()->json(['order' => new OrderResource($order->load('items.product'))]);
    }

    /** "BM-20261025-4F7K2Q" */
    private function newOrderNumber(): string
    {
        do {
            $number = 'BM-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Order::where('number', $number)->exists());

        return $number;
    }

    /**
     * Adres sonradan değişse ya da silinse de sipariş, verildiği andaki adresi gösterir.
     *
     * @return array<string, mixed>
     */
    private function addressSnapshot(Address $address): array
    {
        return [
            'title' => $address->title,
            'full_name' => $address->full_name,
            'phone' => $address->phone,
            'city' => $address->city->name,
            'district' => $address->district->name,
            'neighbourhood' => $address->neighbourhood,
            'street' => $address->street,
            'building_no' => $address->building_no,
            'floor' => $address->floor,
            'apartment_no' => $address->apartment_no,
            'postal_code' => $address->postal_code,
            'directions' => $address->directions,
        ];
    }
}
