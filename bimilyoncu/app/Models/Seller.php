<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Seller extends Model
{
    /** Satıcı Ol ile açılan mağaza, site yöneticisi onaylayana kadar ürün ekleyemez */
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** Site yöneticisi tarafından pasife alındı: ürünleri sitede görünmez, yeniden aktif edilebilir */
    public const STATUS_PASSIVE = 'passive';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['slug', 'name', 'logo', 'brand_color', 'shipping_days', 'status', 'reviewed_at'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shipping_days' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** Satıcının ürün geliştirdiği ana kategoriler (seller_categories ara tablosu) */
    public function categories(): HasMany
    {
        return $this->hasMany(SellerCategory::class);
    }

    /** Alt kategoriyi satıcının kataloğuna ekler (zaten varsa bir şey yapmaz) */
    public function addCategory(int $categoryId): void
    {
        $this->categories()->firstOrCreate(['category_id' => $categoryId]);
    }

    /** Satıcı paneline giriş yapabilen hesaplar */
    public function users(): HasMany
    {
        return $this->hasMany(SellerUser::class);
    }

    /** Satıcının tüm ürünlerine yapılan yorumlar */
    public function reviews(): HasManyThrough
    {
        return $this->hasManyThrough(Review::class, Product::class);
    }
}
