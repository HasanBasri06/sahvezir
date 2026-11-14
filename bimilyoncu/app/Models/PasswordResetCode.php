<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Şifremi unuttum kodu; e-posta başına tek kayıt, yeni kod istenince eskisinin yerine geçer.
 */
class PasswordResetCode extends Model
{
    public const UPDATED_AT = null;

    protected $primaryKey = 'email';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['email', 'code_hash', 'attempts', 'expires_at'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'expires_at' => 'datetime'];
    }
}
