<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Satış raporu: seçilen tarih aralığında, seçilen ürünlerin her satışı ve zamanı (timeline için).
 */
class SellerReportController extends Controller
{
    /** Tarihler mağazanın saatine göre gün olarak yorumlanır */
    private const TIMEZONE = 'Europe/Istanbul';

    /** Tek raporda en fazla bu kadar gün */
    private const MAX_DAYS = 366;

    public function sales(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'products' => ['nullable', 'array', 'max:100'],
            'products.*' => ['string', 'max:20'],
        ], [
            'to.after_or_equal' => 'Bitiş tarihi başlangıçtan önce olamaz.',
        ]);

        $from = Carbon::parse($data['from'], self::TIMEZONE)->startOfDay();
        $to = Carbon::parse($data['to'], self::TIMEZONE)->endOfDay();
        if ($from->diffInDays($to) > self::MAX_DAYS) {
            throw ValidationException::withMessages(['to' => 'En fazla '.self::MAX_DAYS.' günlük rapor alınabilir.']);
        }

        // Yalnızca satıcının kendi ürünleri; seçim yoksa hepsi
        $productIds = $request->user()->seller->products()
            ->when($data['products'] ?? null, fn ($query, $ids) => $query->whereIn('id', $ids))
            ->pluck('id');

        $sales = OrderItem::query()
            ->whereIn('product_id', $productIds)
            ->whereHas('order', fn ($query) => $query
                ->where('status', Order::STATUS_PAID)
                ->whereBetween('created_at', [$from->copy()->utc(), $to->copy()->utc()]))
            ->with('order:id,number,created_at')
            ->get()
            ->sortBy(fn (OrderItem $item) => $item->order->created_at)
            ->values();

        return response()->json([
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'sales' => $sales->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->name,
                'variant' => $item->variant,
                'quantity' => $item->quantity,
                'line_total' => $item->line_total,
                'order_number' => $item->order->number,
                'ordered_at' => $item->order->created_at->toIso8601String(),
            ]),
            'totals' => [
                'items' => (int) $sales->sum('quantity'),
                'revenue' => round((float) $sales->sum('line_total'), 2),
                'orders' => $sales->pluck('order.number')->unique()->count(),
            ],
        ]);
    }
}
