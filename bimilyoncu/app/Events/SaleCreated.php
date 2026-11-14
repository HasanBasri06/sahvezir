<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;

/**
 * Satıcının ürünlerinden biri satın alındı; satıcı panelinde anlık bildirim (Pusher).
 * Kuyruk çalıştırmaya gerek kalmasın diye hemen gönderilir.
 */
class SaleCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  Collection<int, \App\Models\OrderItem>  $items  Bu satıcıya ait sipariş satırları
     */
    public function __construct(public int $sellerId, public Order $order, public Collection $items) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("seller.{$this->sellerId}");
    }

    public function broadcastAs(): string
    {
        return 'sale.created';
    }

    public function broadcastWith(): array
    {
        return [
            'new_sales' => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'order_number' => $this->order->number,
                'product_name' => $item->name,
                'variant' => $item->variant,
                'quantity' => $item->quantity,
                'line_total' => $item->line_total,
            ])->values(),
        ];
    }
}
