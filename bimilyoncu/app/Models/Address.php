<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    /** Bir kullanıcının kaydedebileceği en fazla adres */
    public const MAX_PER_USER = 20;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'full_name',
        'phone',
        'city_id',
        'district_id',
        'neighbourhood',
        'street',
        'building_no',
        'floor',
        'apartment_no',
        'postal_code',
        'directions',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
