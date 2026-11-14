<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Yorum altındaki yazışma mesajı; satıcı ya da yorumu yazan alıcı tarafından.
 */
class ReviewReply extends Model
{
    public const AUTHOR_SELLER = 'seller';

    public const AUTHOR_CUSTOMER = 'customer';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['seller_user_id', 'user_id', 'author_type', 'body'];

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function isFromSeller(): bool
    {
        return $this->author_type === self::AUTHOR_SELLER;
    }
}
