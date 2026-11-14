<?php

namespace App\Models;

use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
    ];

    protected function casts(): array
    {
        return [
            'name' => RoleName::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
