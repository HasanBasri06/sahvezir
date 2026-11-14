<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Satıcı kendi ürününün sipariş satırını ilerletir: hazırlanıyor → kargoda → teslim edildi; iade talebini onaylar.
 */
class SellerOrderController extends Controller
{
    /**
     * Panel açıkken düzenli sorulur: verilen satırdan sonra gelen yeni satışlar.
     * "after" verilmezse yalnızca son satırın id'si döner (başlangıç noktası).
     */
    public function notifications(Request $request): JsonResponse
    {
        // after yoksa ilk sorgudur (yalnızca başlangıç noktası); after=0 hiç satışı olmayan satıcının sorgusudur
        $first = ! $request->has('after');
        $after = $request->integer('after', 0);
        $sales = OrderItem::query()
            ->whereIn('product_id', $request->user()->seller->products()->select('id'))
            ->whereHas('order', fn ($query) => $query->where('status', Order::STATUS_PAID));

        $latestId = (int) (clone $sales)->max('id');

        return response()->json([
            'latest_id' => $latestId,
            // Anlık bildirim (Pusher) için dinlenecek özel kanal
            'channel' => 'private-seller.'.$request->user()->seller_id,
            'new_sales' => ! $first
                ? (clone $sales)->where('id', '>', $after)->with('order:id,number')->oldest('id')->limit(20)->get()
                    ->map(fn (OrderItem $item) => [
                        'id' => $item->id,
                        'order_number' => $item->order->number,
                        'product_name' => $item->name,
                        'variant' => $item->variant,
                        'quantity' => $item->quantity,
                        'line_total' => $item->line_total,
                    ])
                : [],
        ]);
    }

    public function advance(Request $request, int $itemId): JsonResponse
    {
        $item = OrderItem::with('product')
            ->whereIn('product_id', $request->user()->seller->products()->select('id'))
            ->findOrFail($itemId);

        // İstemci hangi duruma geçmek istediğini söyler; çift tıklamada iki adım birden atlanmasın
        $data = $request->validate([
            'status' => ['required', Rule::in(array_values(OrderItem::SELLER_TRANSITIONS))],
        ]);

        if ((OrderItem::SELLER_TRANSITIONS[$item->status] ?? null) !== $data['status']) {
            throw ValidationException::withMessages(['status' => 'Bu satırın durumu zaten değişmiş; sayfayı yenileyin.']);
        }

        DB::transaction(fn () => $item->advanceTo($data['status']));

        return response()->json(['item' => $item->fresh()->toApiArray()]);
    }
}
