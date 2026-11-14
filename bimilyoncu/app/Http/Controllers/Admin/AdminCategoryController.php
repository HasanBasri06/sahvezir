<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Kategoriler: ağaç listesi, ekleme ve ad güncelleme.
 * Ağaç 3 seviye: ana kategori → başlık → alt kategori; ürünler en alttakine bağlanır.
 */
class AdminCategoryController extends Controller
{
    /** Ana kategori, başlık, alt kategori */
    private const MAX_DEPTH = 3;

    /**
     * Ağaç; her kategoride kaç ürün olduğuyla. Üst seviyeler altındaki ürünleri sayar;
     * birden fazla alt kategorideki ürün bir kez sayılır.
     */
    public function index(): JsonResponse
    {
        $productsByLeaf = DB::table('category_product')->get(['category_id', 'product_id'])
            ->groupBy('category_id')
            ->map(fn ($rows) => $rows->pluck('product_id')->all());

        // Her düğüm: altındaki ürün id'leri (tekrarsız) → sayı
        $withCounts = function (array $nodes) use (&$withCounts, $productsByLeaf): array {
            return array_map(function (array $node) use ($withCounts, $productsByLeaf) {
                $children = $withCounts($node['children']);
                $ids = array_unique(array_merge(
                    $productsByLeaf[$node['id']] ?? [],
                    ...array_map(fn (array $child) => $child['product_ids'], $children),
                ));

                return [...$node, 'product_ids' => $ids, 'product_count' => count($ids), 'children' => $children];
            }, $nodes);
        };
        // Yanıtta id listeleri gönderilmez, yalnızca sayılar
        $strip = function (array $nodes) use (&$strip): array {
            return array_map(function (array $node) use ($strip) {
                unset($node['product_ids']);
                $node['children'] = $strip($node['children']);

                return $node;
            }, $nodes);
        };

        return response()->json(['categories' => $strip($withCounts(Category::tree()))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
        ], [
            'name.required' => 'Kategori adını yazın.',
            'name.min' => 'Kategori adı en az 2 karakter olmalıdır.',
        ]);
        $parentId = $data['parent_id'] ?? null;

        // Alt kategorinin altına yeni seviye açılamaz (ürünler en alttakine bağlı)
        if ($parentId && Category::ancestry($parentId)->count() >= self::MAX_DEPTH) {
            throw ValidationException::withMessages(['parent_id' => 'Alt kategorinin altına kategori eklenemez.']);
        }
        $this->ensureUniqueName($data['name'], $parentId);

        $category = Category::create([
            'parent_id' => $parentId,
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name'], $parentId),
            'sort' => (int) Category::where('parent_id', $parentId)->max('sort') + 1,
        ]);

        return response()->json(['category' => $category->only(['id', 'parent_id', 'name', 'slug'])], 201);
    }

    /** Ad güncellenir; adres (slug) değişmez ki mevcut kategori linkleri bozulmasın */
    public function update(Request $request, Category $category): JsonResponse
    {
        $name = $request->validate(
            ['name' => ['required', 'string', 'min:2', 'max:100']],
            ['name.required' => 'Kategori adını yazın.', 'name.min' => 'Kategori adı en az 2 karakter olmalıdır.'],
        )['name'];
        $this->ensureUniqueName($name, $category->parent_id, $category->id);

        $category->update(['name' => $name]);

        return response()->json(['category' => $category->only(['id', 'parent_id', 'name', 'slug'])]);
    }

    /** Aynı seviyede aynı adla iki kategori olmasın */
    private function ensureUniqueName(string $name, ?int $parentId, ?int $exceptId = null): void
    {
        $exists = Category::where('parent_id', $parentId)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->get(['name'])
            ->contains(fn (Category $c) => mb_strtolower($c->name, 'UTF-8') === mb_strtolower($name, 'UTF-8'));
        if ($exists) {
            throw ValidationException::withMessages(['name' => 'Bu seviyede aynı adla bir kategori zaten var.']);
        }
    }

    private function uniqueSlug(string $name, ?int $parentId): string
    {
        $base = Str::slug($name) ?: 'kategori';
        $slug = $base;
        for ($i = 2; Category::where('parent_id', $parentId)->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
