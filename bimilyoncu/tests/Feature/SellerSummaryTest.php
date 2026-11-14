<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerUser;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SellerUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CatalogSeeder::class, SellerUserSeeder::class]);
        Sanctum::actingAs(SellerUser::first(), [], 'seller');
    }

    /** Akcıl Mobilya ürünü için, verilen İstanbul zamanında sipariş satırı */
    private function sale(string $istanbulTime, float $unitPrice, ?float $unitCost, int $quantity = 1, string $status = 'preparing'): void
    {
        $order = Order::forceCreate([
            'user_id' => User::factory()->create()->id,
            'number' => 'BM-'.uniqid(),
            'status' => Order::STATUS_PAID,
            'subtotal' => $unitPrice * $quantity,
            'vat' => 0,
            'shipping' => 0,
            'total' => $unitPrice * $quantity,
            'shipping_address' => [],
            'paid_at' => now(),
            'created_at' => Carbon::parse($istanbulTime, 'Europe/Istanbul')->utc(),
        ]);
        $order->items()->create([
            'product_id' => 'p22',
            'name' => 'Ürün',
            'unit_price' => $unitPrice,
            'unit_cost' => $unitCost,
            'quantity' => $quantity,
            'line_total' => $unitPrice * $quantity,
            'status' => $status,
        ]);
    }

    public function test_summary_shows_revenue_and_profit_in_range(): void
    {
        $this->sale('2026-10-10 12:00', 1000, 600, 2);   // kâr 800
        $this->sale('2026-10-12 23:30', 500, 450);       // kâr 50
        $this->sale('2026-10-12 10:00', 300, 100, 1, OrderItem::STATUS_RETURNED); // iade: sayılmaz
        $this->sale('2026-10-20 09:00', 2000, 1000);     // aralık dışı

        $this->getJson('/api/seller/dashboard/summary?from=2026-10-10&to=2026-10-12')
            ->assertOk()
            ->assertJsonPath('stats.order_count', 2)
            ->assertJsonPath('stats.items_sold', 3)
            ->assertJsonPath('stats.revenue', fn ($v) => $v == 2500)
            ->assertJsonPath('stats.profit', fn ($v) => $v == 850)
            // Son eklenen önce; ilk satış (2 adet × 1000, birim maliyet 600) en sonda
            ->assertJsonPath('sales.2.cost', fn ($v) => $v == 1200)
            ->assertJsonPath('sales.2.profit', fn ($v) => $v == 800)
            ->assertJsonCount(3, 'sales');

        // Aralık yoksa tüm zamanlar
        $this->getJson('/api/seller/dashboard/summary')
            ->assertJsonPath('stats.revenue', fn ($v) => $v == 4500)
            ->assertJsonPath('stats.profit', fn ($v) => $v == 1850);
    }

    public function test_old_sales_use_current_product_cost_and_unknown_costs_are_counted(): void
    {
        $this->sale('2026-10-10 12:00', 1000, null);
        $this->getJson('/api/seller/dashboard/summary')
            ->assertJsonPath('stats.profit', fn ($v) => $v == 0)
            ->assertJsonPath('stats.missing_cost_count', 1)
            ->assertJsonPath('sales.0.profit', null);

        Product::find('p22')->update(['cost' => 700]);
        $this->getJson('/api/seller/dashboard/summary')
            ->assertJsonPath('stats.profit', fn ($v) => $v == 300)
            ->assertJsonPath('stats.missing_cost_count', 0);
    }

    public function test_new_order_stores_cost_at_sale_time(): void
    {
        $this->seed(LocationSeeder::class);
        Product::find('p22')->update(['cost' => 400]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $address = $user->addresses()->create([
            'title' => 'Ev', 'full_name' => 'A B', 'phone' => '0532 000 00 00', 'city_id' => 34,
            'district_id' => District::where('city_id', 34)->value('id'), 'neighbourhood' => 'M', 'street' => 'S',
            'building_no' => '1', 'floor' => '1', 'apartment_no' => '1', 'postal_code' => '34000',
        ]);
        $this->postJson('/api/cart/items', ['product_id' => 'p22', 'quantity' => 1]);
        $this->postJson('/api/orders', ['address_id' => $address->id, 'card_brand' => 'visa', 'card_last4' => '1111'])
            ->assertCreated();

        // Maliyet sonradan değişse de satıştaki maliyet aynı kalır
        Product::find('p22')->update(['cost' => 900]);
        $this->assertEquals(400, OrderItem::latest('id')->value('unit_cost'));
    }

    public function test_range_is_validated(): void
    {
        $this->getJson('/api/seller/dashboard/summary?from=2026-10-12&to=2026-10-10')
            ->assertJsonValidationErrors(['to']);
        $this->getJson('/api/seller/dashboard/summary?from=2026-10-12')
            ->assertJsonValidationErrors(['to']);
    }
}
