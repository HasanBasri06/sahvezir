<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Ürün kategorileri; parent_id ile kendi içinde ağaç: ana kategori → başlık → alt kategori.
 * Ürünler en alttaki (yaprak) kategoriye bağlanır.
 */
class Category extends Model
{
    protected $fillable = ['parent_id', 'name', 'slug', 'sort'];

    /**
     * İstek boyunca tüm kategoriler bir kez okunur (yol hesabı her ürün için sorgu atmasın).
     * Önbellek uygulama örneğinde tutulur; her istek (ve her test) temiz başlar.
     */
    private const CACHE = 'categories.all';

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }

    protected static function booted(): void
    {
        // Ağaç değişince önbellek tazelensin
        static::saved(fn () => app()->forgetInstance(self::CACHE));
        static::deleted(fn () => app()->forgetInstance(self::CACHE));
    }

    /** @return Collection<int, Category> id'ye göre */
    private static function all_(): Collection
    {
        if (! app()->resolved(self::CACHE) && ! app()->bound(self::CACHE)) {
            app()->instance(self::CACHE, self::query()->get()->keyBy('id'));
        }

        return app(self::CACHE);
    }

    /** Kategorinin kökten kendisine kadar slug'ları, örn. ["ev-bahce", "ahsap-urunler", "ahsap-mobilya"] */
    public static function pathFor(?int $id): array
    {
        $path = [];
        for ($category = self::all_()->get($id); $category; $category = self::all_()->get($category->parent_id)) {
            array_unshift($path, $category->slug);
        }

        return $path;
    }

    /** Kökten kendisine kadar kategoriler; ilk eleman ana kategori */
    public static function ancestry(int $id): Collection
    {
        $chain = collect();
        for ($category = self::all_()->get($id); $category; $category = self::all_()->get($category->parent_id)) {
            $chain->prepend($category);
        }

        return $chain;
    }

    /** Slug yolundan kategori id'si; yol geçersizse null */
    public static function idForPath(array $slugs): ?int
    {
        $parentId = null;
        foreach ($slugs as $slug) {
            $match = self::all_()->first(fn (Category $c) => $c->parent_id === $parentId && $c->slug === $slug);
            if (! $match) {
                return null;
            }
            $parentId = $match->id;
        }

        return $parentId;
    }

    /** Kategorinin altındaki tüm alt (yaprak) kategorilerin id'leri; kendisi yapraksa kendisi */
    public static function leafIdsUnder(int $id): array
    {
        $children = self::all_()->where('parent_id', $id);

        return $children->isEmpty()
            ? [$id]
            : $children->flatMap(fn (Category $c) => self::leafIdsUnder($c->id))->values()->all();
    }

    public function isLeaf(): bool
    {
        return ! self::all_()->contains(fn (Category $c) => $c->parent_id === $this->id);
    }

    /** Tüm ağaç; her düğüm alt kategorileriyle */
    public static function tree(): array
    {
        $byParent = self::all_()->sortBy('sort')->groupBy(fn (Category $c) => $c->parent_id ?? 0);
        $build = function (int $parentId) use (&$build, $byParent): array {
            return ($byParent->get($parentId) ?? collect())->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'children' => $build($c->id),
            ])->values()->all();
        };

        return $build(0);
    }

    /** database/data/categories.json'daki ağacı yükler (zaten varsa dokunmaz) */
    public static function seedFromFile(): void
    {
        if (self::query()->exists()) {
            return;
        }

        $insert = function (array $nodes, ?int $parentId) use (&$insert) {
            foreach ($nodes as $sort => $node) {
                $category = self::create([
                    'parent_id' => $parentId,
                    'name' => $node['name'],
                    'slug' => $node['slug'],
                    'sort' => $sort,
                ]);
                $insert($node['children'] ?? [], $category->id);
            }
        };
        $insert(json_decode(file_get_contents(database_path('data/categories.json')), true, flags: JSON_THROW_ON_ERROR), null);
    }
}
