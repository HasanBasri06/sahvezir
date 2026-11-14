<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentCar extends Model
{
    protected $table = 'shipment_cars';

    protected $guarded = [];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id', 'id');
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class, 'car_id', 'id');
    }

    public function carDetail(): BelongsTo
    {
        return $this->belongsTo(CarDetail::class, 'car_detail_id', 'id');
    }
}
