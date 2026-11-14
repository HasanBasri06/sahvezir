<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    public const STATUS_PREPARING = 'preparing';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_RETURN_REQUESTED = 'return_requested';

    public const STATUS_RETURNED = 'returned';

    /** Teslimattan sonra iade talep edilebilecek gün sayısı */
    public const RETURN_DAYS = 14;

    /**
     * Satıcının yapabileceği geçişler: mevcut durum => sonraki durum
     *
     * @var array<string, string>
     */
    public const SELLER_TRANSITIONS = [
        self::STATUS_PREPARING => self::STATUS_SHIPPED,
        self::STATUS_SHIPPED => self::STATUS_DELIVERED,
        self::STATUS_RETURN_REQUESTED => self::STATUS_RETURNED,
    ];

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'product_id', 'name', 'variant', 'image', 'unit_price', 'unit_cost', 'quantity', 'line_total',
        'status', 'shipped_at', 'delivered_at', 'return_reason', 'return_requested_at', 'returned_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'float',
            'unit_cost' => 'float',
            'quantity' => 'integer',
            'line_total' => 'float',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'return_requested_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Teslim edilmiş ve iade süresi dolmamış */
    public function canBeReturned(): bool
    {
        return $this->status === self::STATUS_DELIVERED
            && $this->delivered_at?->addDays(self::RETURN_DAYS)->isFuture();
    }

    /**
     * Durumu ilerletir ve ilgili zamanı işler; iade onayında stok geri eklenir.
     */
    public function advanceTo(string $status): void
    {
        $now = now();

        match ($status) {
            self::STATUS_SHIPPED => $this->shipped_at = $now,
            self::STATUS_DELIVERED => $this->delivered_at = $now,
            self::STATUS_RETURNED => $this->returned_at = $now,
            default => null,
        };
        $this->status = $status;
        $this->save();

        if ($status === self::STATUS_RETURNED) {
            $this->product?->increment('stock', $this->quantity);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            // Ürün sayfası ve yorum için; ürün kaldırıldıysa null
            'product_slug' => $this->product?->slug,
            'name' => $this->name,
            'variant' => $this->variant,
            'image' => $this->image,
            'unit_price' => $this->unit_price,
            'quantity' => $this->quantity,
            'line_total' => $this->line_total,
            'status' => $this->status,
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'return_reason' => $this->return_reason,
            'return_requested_at' => $this->return_requested_at?->toIso8601String(),
            'returned_at' => $this->returned_at?->toIso8601String(),
            'can_return' => $this->canBeReturned(),
            'return_deadline' => $this->delivered_at?->addDays(self::RETURN_DAYS)->toIso8601String(),
        ];
    }
}
