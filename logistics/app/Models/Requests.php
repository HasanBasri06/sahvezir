<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Requests extends Model
{
    protected $table = 'requests';

    protected $guarded = [];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class, 'car_type', 'id');
    }

    public function createdAt(): Attribute
    {
        return Attribute::get(function ($value) {
            return Carbon::parse($value)->format('d.m.Y H:i');
        });
    }
    public function price(): Attribute
    {
        return Attribute::get(function ($value) {
            return number_format($value, 2, ',', '.') . ' ₺';
        });
    }
}
