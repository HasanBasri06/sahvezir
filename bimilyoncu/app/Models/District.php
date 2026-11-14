<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class District extends Model
{
    public $timestamps = false;

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Posta kodu bu ilçeye ait mi.
     */
    public function hasPostalCode(string $code): bool
    {
        return DB::table('district_postal_codes')
            ->where('district_id', $this->id)
            ->where('code', $code)
            ->exists();
    }
}
