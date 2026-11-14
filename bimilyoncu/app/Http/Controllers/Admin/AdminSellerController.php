<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Seller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Satıcı İşlemleri: onay bekleyen satıcılar ve onaylı satıcılarımız.
 */
class AdminSellerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Birden fazla durum virgülle: Satıcılarımız aktif ve pasif satıcıları birlikte listeler
        $statuses = explode(',', (string) $request->query('status'));
        validator(['status' => $statuses], [
            'status' => ['required', 'array', 'min:1'],
            'status.*' => [Rule::in([
                Seller::STATUS_PENDING, Seller::STATUS_APPROVED, Seller::STATUS_REJECTED, Seller::STATUS_PASSIVE,
            ])],
        ])->validate();
        $status = $statuses[0];

        $sellers = Seller::query()
            ->whereIn('status', $statuses)
            ->with('users:id,seller_id,name,email')
            ->withCount('products')
            // Onay bekleyenlerde ilk başvuran önce, diğerlerinde en yeni önce
            ->orderBy('created_at', $status === Seller::STATUS_PENDING ? 'asc' : 'desc')
            ->get();

        // Satıcıların alt kategori başına ürün sayıları (birden fazla kategorideki ürün her birinde sayılır)
        $perCategory = DB::table('category_product')
            ->join('products', 'products.id', '=', 'category_product.product_id')
            ->whereIn('products.seller_id', $sellers->pluck('id'))
            ->selectRaw('products.seller_id, category_product.category_id, count(*) as total')
            ->groupBy('products.seller_id', 'category_product.category_id')
            ->get()
            ->groupBy('seller_id');

        // Satıcı başına ciro ve kâr: iade edilenler hariç; maliyet satış anındaki, yoksa ürünün şimdiki maliyeti.
        // Maliyeti hiç bilinmeyen satışlar kâra katılmaz.
        $totals = DB::table('order_items')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereIn('products.seller_id', $sellers->pluck('id'))
            ->where('order_items.status', '!=', OrderItem::STATUS_RETURNED)
            ->selectRaw('products.seller_id')
            ->selectRaw('sum(order_items.line_total) as revenue')
            ->selectRaw('sum(case when coalesce(order_items.unit_cost, products.cost) is null then 0
                else order_items.line_total - coalesce(order_items.unit_cost, products.cost) * order_items.quantity end) as profit')
            ->groupBy('products.seller_id')
            ->get()
            ->keyBy('seller_id');

        return response()->json([
            'sellers' => $sellers->map(fn (Seller $seller) => [
                'id' => $seller->id,
                'slug' => $seller->slug,
                'name' => $seller->name,
                'logo' => $seller->logo,
                'status' => $seller->status,
                'email' => $seller->users->first()?->email,
                'product_count' => $seller->products_count,
                'revenue' => round((float) ($totals[$seller->id]->revenue ?? 0), 2),
                'profit' => round((float) ($totals[$seller->id]->profit ?? 0), 2),
                // Kategori başına ürün: en çok ürünü olan önce; trail örn. "Ev & Bahçe › Ahşap Ürünler"
                'categories' => ($perCategory[$seller->id] ?? collect())
                    ->map(function ($row) {
                        $chain = Category::ancestry($row->category_id);

                        return [
                            'id' => (int) $row->category_id,
                            'name' => $chain->last()?->name,
                            'trail' => $chain->slice(0, -1)->pluck('name')->implode(' › '),
                            'count' => (int) $row->total,
                        ];
                    })
                    ->sortByDesc('count')
                    ->values(),
                'created_at' => $seller->created_at?->toIso8601String(),
                'reviewed_at' => $seller->reviewed_at?->toIso8601String(),
            ]),
            'counts' => Seller::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    /**
     * Pasife al ya da yeniden aktif et; yalnızca onaylanmış (aktif/pasif) satıcılar için.
     * Pasif satıcının ürünleri sitede görünmez.
     */
    public function setActive(Request $request, Seller $seller): JsonResponse
    {
        $active = $request->validate(['active' => ['required', 'boolean']])['active'];

        if (! in_array($seller->status, [Seller::STATUS_APPROVED, Seller::STATUS_PASSIVE], true)) {
            throw ValidationException::withMessages(['active' => 'Yalnızca onaylanmış satıcılar pasife alınabilir.']);
        }

        $seller->update(['status' => $active ? Seller::STATUS_APPROVED : Seller::STATUS_PASSIVE]);

        return response()->json(['seller' => ['id' => $seller->id, 'status' => $seller->status]]);
    }

    /** Onayla ya da reddet; yalnızca onay bekleyen satıcılar için */
    public function review(Request $request, Seller $seller): JsonResponse
    {
        $decision = $request->validate([
            'status' => ['required', Rule::in([Seller::STATUS_APPROVED, Seller::STATUS_REJECTED])],
        ])['status'];

        if ($seller->status !== Seller::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => 'Bu satıcı zaten değerlendirilmiş.']);
        }

        $seller->update(['status' => $decision, 'reviewed_at' => now()]);

        return response()->json(['seller' => ['id' => $seller->id, 'status' => $seller->status]]);
    }
}
