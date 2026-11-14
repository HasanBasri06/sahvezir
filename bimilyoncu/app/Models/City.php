<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * İl; id plaka kodudur.
 */
class City extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }
}
