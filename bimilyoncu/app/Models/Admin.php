<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Site yöneticisi; "admin" guard'ı ile doğrulanır.
 */
class Admin extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}
