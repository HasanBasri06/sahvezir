<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductColor extends Model
{
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'hex', 'sort'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Bu renk seçiliyken gösterilen görseller */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort');
    }
}
