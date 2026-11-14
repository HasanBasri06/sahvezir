<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kullanıcının bir ürünü en son ne zaman ve hangi fiyattan gördüğü.
 */
class ProductView extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'product_id', 'viewed_price', 'viewed_at'];

    protected function casts(): array
    {
        return ['viewed_price' => 'float', 'viewed_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
