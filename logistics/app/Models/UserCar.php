<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCar extends Model
{
    protected $table = 'user_cars';

    protected $guarded = [];


    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class, 'car_id', 'id');
    }

    public function carDetail(): BelongsTo
    {
        return $this->belongsTo(CarDetail::class, 'car_detail_id', 'id');
    }
}
