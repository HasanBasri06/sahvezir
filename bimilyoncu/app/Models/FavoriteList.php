<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kullanıcının favorilerini grupladığı kategori.
 */
class FavoriteList extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['name'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Bu kategorideki favori kayıtları (favorites tablosu) */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }
}
