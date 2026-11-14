<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Satıcının yalnızca kendi ürünleri ve bu ürünlerin satışları.
 */
class SellerDashboardController extends Controller
{
    private const TIMEZONE = 'Europe/Istanbul';

    public function __invoke(Request $request): JsonResponse
    {
        $seller = $request->user()->seller;

        // Panelde yeni eklenen ürün en üstte; aynı anda eklenenler mağazadaki sırasıyla
        $products = $seller->products()->withStats()->latest('created_at')->orderBy('sort')->get();

        // Siparişlerdeki, bu satıcının ürünlerine ait satırlar; kargolamak için teslimat adresiyle
        $sales = OrderItem::query()
            ->whereIn('product_id', $products->pluck('id'))
            ->with('order')
            ->latest('id')
            // Siparişler sayfası 20'şer gösterir (sonsuz kaydırma); sekme ve arama tarayıcıda yapılır
            ->limit(1000)
            ->get();

        $allSales = OrderItem::query()->whereIn('product_id', $products->pluck('id'));

        return response()->json([
            'stats' => [
                'product_count' => $products->count(),
                'order_count' => (clone $allSales)->distinct('order_id')->count('order_id'),
                'items_sold' => (int) (clone $allSales)->sum('quantity'),
                'revenue' => round((float) (clone $allSales)->sum('line_total'), 2),
            ],
            'products' => ProductResource::collection($products),
            'sales' => $sales->map(fn (OrderItem $item) => $this->salePayload($item)),
        ]);
    }

    /**
     * Genel Bakış: tarih aralığındaki (yoksa tüm zamanlardaki) satışlar, ciro ve kâr.
     * İade edilen satırlar ciro ve kâra girmez. Kâr = satış tutarı − maliyet × adet; maliyet satış anında
     * kaydedilir, eski satışlarda ürünün şimdiki maliyeti kullanılır. Maliyeti hiç girilmemiş satırlar
     * kâra katılmaz, sayısı ayrıca döner.
     */
    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d', 'required_with:to'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_with:from', 'after_or_equal:from'],
        ], [
            'to.after_or_equal' => 'Bitiş tarihi başlangıçtan önce olamaz.',
        ]);

        $sales = OrderItem::query()
            ->whereIn('product_id', $request->user()->seller->products()->select('id'))
            ->when(isset($data['from']), fn ($query) => $query->whereHas('order', fn ($order) => $order->whereBetween(
                'created_at',
                [
                    Carbon::parse($data['from'], self::TIMEZONE)->startOfDay()->utc(),
                    Carbon::parse($data['to'], self::TIMEZONE)->endOfDay()->utc(),
                ],
            )))
            ->with(['order', 'product:id,cost'])
            ->latest('id')
            ->get();

        $counted = $sales->where('status', '!=', OrderItem::STATUS_RETURNED);
        $costOf = fn (OrderItem $item) => $item->unit_cost ?? $item->product?->cost;
        $withCost = $counted->filter(fn (OrderItem $item) => $costOf($item) !== null);

        return response()->json([
            'stats' => [
                'order_count' => $counted->unique('order_id')->count(),
                'items_sold' => (int) $counted->sum('quantity'),
                'revenue' => round((float) $counted->sum('line_total'), 2),
                'profit' => round((float) $withCost->sum(
                    fn (OrderItem $item) => $item->line_total - $costOf($item) * $item->quantity
                ), 2),
                'missing_cost_count' => $counted->count() - $withCost->count(),
            ],
            'sales' => $sales->take(50)->values()->map(fn (OrderItem $item) => [
                ...$this->salePayload($item),
                // Satırın toplam maliyeti ve kârı; maliyeti bilinmiyorsa null
                'cost' => $costOf($item) === null ? null : round($costOf($item) * $item->quantity, 2),
                'profit' => $costOf($item) === null ? null : round($item->line_total - $costOf($item) * $item->quantity, 2),
            ]),
        ]);
    }

    /** Satış satırı; kargolamak için teslimat adresiyle */
    private function salePayload(OrderItem $item): array
    {
        return [
            ...$item->toApiArray(),
            'order_number' => $item->order->number,
            'ordered_at' => $item->order->created_at->toIso8601String(),
            'product_name' => $item->name,
            'variant' => $item->variant,
            'image' => $item->image,
            'quantity' => $item->quantity,
            'line_total' => $item->line_total,
            'shipping_address' => $item->order->shipping_address,
        ];
    }
}
