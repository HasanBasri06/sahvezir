<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    /** Ürün kodunda kullanılan karakterler: büyük harf ve rakam */
    private const CODE_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    private const CODE_LENGTH = 10;

    /** Her yeni ürüne kayıt sırasında otomatik, benzersiz ürün kodu verilir */
    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            $product->code ??= self::newCode();
        });
    }

    /**
     * Harf ve rakam karışık 10 karakterlik benzersiz kod (en az bir harf ve bir rakam içerir).
     */
    public static function newCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_CHARS[random_int(0, strlen(self::CODE_CHARS) - 1)];
            }
        } while (
            ! preg_match('/[A-Z]/', $code)
            || ! preg_match('/\d/', $code)
            || static::query()->where('code', $code)->exists()
        );

        return $code;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id', 'slug', 'seller_id', 'name', 'price', 'old_price', 'cost', 'stock', 'image',
        'category_id', 'description', 'featured', 'ai_images', 'sort',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'float',
            'cost' => 'float',
            'old_price' => 'float',
            'stock' => 'integer',
            'description' => 'array',
            'featured' => 'boolean',
            'ai_images' => 'boolean',
        ];
    }

    /**
     * Yorumlardan puan ve değerlendirme sayısı, siparişlerden satış adedi.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeWithStats(Builder $query): void
    {
        $query->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->withSum('orderItems as sold_count', 'quantity');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Kullanıcıların bu ürüne bakışları (Önceden Gezdiklerin); öne çıkanlar bundan hesaplanır */
    public function views(): HasMany
    {
        return $this->hasMany(ProductView::class);
    }

    /** En alttaki (yaprak) kategori */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Kategori yolu slug'larla: [ana kategori, başlık, alt kategori] (ana kategorisi) */
    public function categoryPath(): array
    {
        return Category::pathFor($this->category_id);
    }

    /** Ürünün tüm alt kategorileri (çoklu seçim); category_id bunlardan ilki, ana kategori */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /** Tüm kategorilerin yolları; ana kategori ilk sırada */
    public function categoryPaths(): array
    {
        $ids = $this->categories->pluck('id')->sortBy(fn (int $id) => $id === $this->category_id ? 0 : 1)->values();

        return $ids->isEmpty()
            ? [$this->categoryPath()]
            : $ids->map(fn (int $id) => Category::pathFor($id))->all();
    }

    /** Sitede yayında: mağazası site yöneticisi tarafından onaylanmış ürünler */
    public function scopePublished(Builder $query): void
    {
        $query->whereHas('seller', fn (Builder $seller) => $seller->where('status', Seller::STATUS_APPROVED));
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function colors(): HasMany
    {
        return $this->hasMany(ProductColor::class)->orderBy('sort');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort');
    }

    public function specs(): HasMany
    {
        return $this->hasMany(ProductSpec::class)->orderBy('sort');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->latest('reviewed_at')->latest('id');
    }
}
