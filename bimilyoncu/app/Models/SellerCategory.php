<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satıcı–kategori bağı (ara tablo): satıcının kataloğundaki alt kategori.
 */
class SellerCategory extends Model
{
    protected $fillable = ['seller_id', 'category_id'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }
}
