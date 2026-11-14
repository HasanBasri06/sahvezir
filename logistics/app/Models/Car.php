<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Car extends Model
{
    protected $table = 'cars';

    protected $guarded = [];

    public function details()
    {
        return $this->hasMany(CarDetail::class, 'car_id', 'id');
    }
}
