<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDetail extends Model
{
    protected $table = 'users_detail';

    protected $fillable = [
        'user_id',
        'user_city',
        'user_district',
        'exact_location',
    ];

    protected $casts = [
        'exact_location' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
