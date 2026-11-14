<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class Shipment extends Model
{
    protected $table = 'shipments';

    /**
     * Açık ilan listesi değişince (yeni açık ilan / açık ilan kapandı) `listings` kanalına yayın:
     * aktif iş ekranındaki "Yol Üzerindeki İlanlar" anlık güncellenir. Yayın hatası kaydı bozmasın.
     */
    protected static function booted(): void
    {
        $notify = static function (self $shipment, string $action): void {
            try {
                broadcast(\App\Events\ShipmentListingChanged::fromShipment($shipment, $action));
            } catch (\Throwable $e) {
                Log::warning('listing.changed yayınlanamadı', ['shipment_id' => $shipment->id, 'error' => $e->getMessage()]);
            }
        };

        static::created(function (self $shipment) use ($notify) {
            if ($shipment->status === 'active') {
                $notify($shipment, 'opened');
            }
        });

        static::updated(function (self $shipment) use ($notify) {
            if (! $shipment->wasChanged('status')) {
                return;
            }
            $was = $shipment->getOriginal('status');
            if ($shipment->status === 'active') {
                $notify($shipment, 'opened');
            } elseif ($was === 'active') {
                $notify($shipment, 'closed');
            }
        });

        static::deleted(function (self $shipment) use ($notify) {
            $notify($shipment, 'closed');
        });
    }

    protected $guarded = [];

    protected $casts = [
        'is_kdv' => 'boolean',
        'part_load' => 'boolean',
        'is_fast' => 'boolean',
        'departure_date' => 'datetime',
        'f_where_location' => 'array',
        't_where_location' => 'array',
    ];

    /**
     * Haritadan seçilen tam adres/koordinat herkese açık ilan yanıtlarında görünmesin.
     * Gerektiğinde bilinçli olarak `makeVisible([...])` ile açılmalı.
     */
    protected $hidden = [
        'f_where_location',
        't_where_location',
    ];

    protected $appends = [
        'departure_date_text'
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creater_id', 'id');
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class, 'car_id', 'id');
    }

    public function getCarDetail(): BelongsTo
    {
        return $this->belongsTo(CarDetail::class, 'car_detail_id', 'id');
    }

    /** İlanın taşınabileceği tüm araç türleri (ilk eleman `car_id` ile aynıdır) */
    public function shipmentCars(): HasMany
    {
        return $this->hasMany(ShipmentCar::class, 'shipment_id', 'id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(Requests::class, 'shipment_id', 'id');
    }

    public function postType(): BelongsTo
    {
        return $this->belongsTo(PostType::class, 'post_type_id', 'id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ShipmentAttachment::class, 'shipment_id', 'id');
    }
    public function price(): Attribute
    {
        return Attribute::get(function ($value) {
            return $value == 0 ? 'Fiyat görüşülecektir' : number_format($value, 2, ',', '.') . ' ₺';
        });
    }
    public function departureTime(): Attribute
    {
        return Attribute::get(function ($value) {
            return $value ? Carbon::parse($value)->format('H:i') : null;
        });
    }
    public function timeArrival(): Attribute
    {
        return Attribute::get(function ($value) {
            return $value ? Carbon::parse($value)->format('H:i') : null;
        });
    }
    public function departureDateText(): Attribute
    {
        return Attribute::get(function ($value) {
            if (is_null($this->attributes['departure_date'])) {
                return 'Yükleme tarihi görüşülecek';
            }

            $date = Carbon::parse($this->attributes['departure_date']);

            if ($date->isToday()) {
                return 'Bugün';
            }

            if ($date->isTomorrow()) {
                return 'Yarın';
            }

            if ($date->isSameDay(Carbon::now()->addDays(2))) {
                return '2 gün sonra';
            }
            
            return $this->attributes['departure_date'] ? Carbon::parse($this->attributes['departure_date'])->format('d.m.Y') : null;
        });
    }
}
