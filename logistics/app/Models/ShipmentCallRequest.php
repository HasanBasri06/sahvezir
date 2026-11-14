<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentCallRequest extends Model
{
    protected $table = 'shipment_call_requests';

    protected $guarded = [];

    protected $casts = [
        'called_at' => 'datetime',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id', 'id');
    }

    public function vehicleOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vehicle_owner_id', 'id');
    }

    public function cargoOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cargo_owner_id', 'id');
    }
}
