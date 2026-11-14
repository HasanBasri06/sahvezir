<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kullanıcının aldığı paket ve bitiş tarihi. Aynı anda tek `active` kayıt olur; yeni paket alınınca
 * öncekiler `inactive` yapılır.
 */
class UserSubscription extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'user_subscriptions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'price' => 'float',
            'plan_months' => 'integer',
            'auto_renew' => 'boolean',
            'renew_attempts' => 'integer',
            'last_renew_attempt_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(UserOrder::class, 'user_order_id');
    }
}
