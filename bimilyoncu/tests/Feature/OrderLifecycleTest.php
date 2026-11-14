<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerUser;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\SellerUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private Order $order;

    private OrderItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CatalogSeeder::class, SellerUserSeeder::class]);

        $this->buyer = User::factory()->create();
        $this->order = $this->buyer->orders()->create([
            'number' => 'BM-TEST-1', 'status' => 'paid', 'subtotal' => 2750, 'vat' => 550, 'shipping' => 0,
            'total' => 3300, 'shipping_address' => [], 'card_last4' => '1111',
        ]);
        // p22: Akcıl Mobilya'nın ürünü
        $this->item = $this->order->items()->create([
            'product_id' => 'p22', 'name' => 'Dolap', 'unit_price' => 2750, 'quantity' => 2, 'line_total' => 5500,
        ]);
    }

    private function asSeller(): void
    {
        Sanctum::actingAs(SellerUser::first(), [], 'seller');
    }

    private function advance(string $status)
    {
        return $this->postJson("/api/seller/order-items/{$this->item->id}/status", ['status' => $status]);
    }

    private function requestReturn(string $reason = 'Ölçüsü uymadı')
    {
        Sanctum::actingAs($this->buyer);

        return $this->postJson("/api/orders/{$this->order->number}/items/{$this->item->id}/return", ['reason' => $reason]);
    }

    public function test_new_order_items_are_preparing(): void
    {
        Sanctum::actingAs($this->buyer);

        $this->getJson('/api/orders')->assertOk()->assertJsonPath('orders.0.items.0.status', 'preparing');
    }

    public function test_full_lifecycle_with_return_restores_stock(): void
    {
        $stock = Product::find('p22')->stock;

        // Teslim edilmeden iade talep edilemez
        $this->requestReturn()->assertUnprocessable()->assertJsonValidationErrors('reason');

        $this->asSeller();
        // Adım atlanamaz
        $this->advance('delivered')->assertUnprocessable();
        $this->advance('shipped')->assertOk()->assertJsonPath('item.status', 'shipped');
        $this->advance('delivered')->assertOk()->assertJsonPath('item.status', 'delivered')->assertJsonPath('item.can_return', true);

        $this->requestReturn()
            ->assertOk()
            ->assertJsonPath('order.items.0.status', 'return_requested')
            ->assertJsonPath('order.items.0.return_reason', 'Ölçüsü uymadı');

        $this->asSeller();
        $this->advance('returned')->assertOk()->assertJsonPath('item.status', 'returned');

        $this->assertSame($stock + 2, Product::find('p22')->stock);
    }

    public function test_return_window_is_fourteen_days(): void
    {
        $this->item->update(['status' => 'delivered', 'delivered_at' => now()->subDays(15)]);

        $this->requestReturn()->assertUnprocessable()->assertJsonValidationErrors('reason');
    }

    public function test_seller_is_notified_only_about_own_new_sales(): void
    {
        $this->asSeller();
        $baseline = $this->getJson('/api/seller/notifications')
            ->assertOk()
            ->assertJsonPath('latest_id', $this->item->id)
            ->assertJsonCount(0, 'new_sales')
            ->json('latest_id');

        // Başka satıcının ürünü bildirim üretmez; kendi ürünü üretir
        $this->order->items()->create(['product_id' => 'p19', 'name' => 'Figür', 'unit_price' => 1, 'quantity' => 1, 'line_total' => 1]);
        $own = $this->order->items()->create(['product_id' => 'p23', 'name' => 'Kahvelik', 'unit_price' => 1, 'quantity' => 3, 'line_total' => 3]);

        $this->getJson('/api/seller/notifications?after='.$baseline)
            ->assertOk()
            ->assertJsonPath('latest_id', $own->id)
            ->assertJsonCount(1, 'new_sales')
            ->assertJsonPath('new_sales.0.product_name', 'Kahvelik')
            ->assertJsonPath('new_sales.0.order_number', 'BM-TEST-1');
    }

    public function test_seller_cannot_update_other_sellers_items(): void
    {
        $this->item->update(['product_id' => 'p19']);
        $this->asSeller();

        $this->advance('shipped')->assertNotFound();
    }

    public function test_others_cannot_request_return_for_someones_order(): void
    {
        $this->item->update(['status' => 'delivered', 'delivered_at' => now()]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/orders/{$this->order->number}/items/{$this->item->id}/return", ['reason' => 'Benim değil'])
            ->assertNotFound();
    }
}
