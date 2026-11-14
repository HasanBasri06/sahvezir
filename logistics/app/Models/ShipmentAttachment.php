<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentAttachment extends Model
{
    protected $table = 'shipment_attachments';

    protected $guarded = [];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id', 'id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id', 'id');
    }
}
