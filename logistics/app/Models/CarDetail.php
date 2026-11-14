<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarDetail extends Model
{
    protected $table = 'car_detail';

    protected $guarded = [];

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class, 'car_id', 'id');
    }
}
