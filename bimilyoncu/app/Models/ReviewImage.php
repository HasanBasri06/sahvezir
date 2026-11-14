<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewImage extends Model
{
    /** Bir yoruma eklenebilecek en fazla fotoğraf */
    public const MAX_PER_REVIEW = 5;

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['path', 'sort'];

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
