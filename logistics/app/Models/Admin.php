<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'admin';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    public function terminateAllSessions(): void
    {
        $this->tokens()->delete();
    }
}
