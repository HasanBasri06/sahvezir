<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentOrder extends Model
{
    protected $table = 'shipments_order';

    protected $guarded = [];

    /** İşi teslim edecek sürücü (araç sahibi işi başkasına verdiyse) */
    public function driver(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_user_id');
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'progress_updated_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id', 'id');
    }
    
    public function creater_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creater_user', 'id');
    }

    /** İlişki aliası – API yanıtında "creator" olarak dönmek için */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creater_user', 'id');
    }

    public function buyer_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user', 'id');
    }
}
