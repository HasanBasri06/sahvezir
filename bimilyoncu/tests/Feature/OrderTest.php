<?php

namespace Tests\Feature;

use App\Events\SaleCreated;
use App\Models\Address;
use App\Models\District;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Address $address;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([LocationSeeder::class, CatalogSeeder::class]);

        $this->user = User::factory()->create();
        $this->address = $this->user->addresses()->create([
            'title' => 'Ev',
            'full_name' => 'Ayşe Yılmaz',
            'phone' => '0532 123 45 67',
            'city_id' => 34,
            'district_id' => District::where('city_id', 34)->where('name', 'Kadıköy')->value('id'),
            'neighbourhood' => 'Caferağa Mah.',
            'street' => 'Moda Cad.',
            'building_no' => '12',
            'floor' => '2',
            'apartment_no' => '3',
            'postal_code' => '34710',
        ]);
        Sanctum::actingAs($this->user);
    }

    private function order(array $overrides = [])
    {
        return $this->postJson('/api/orders', array_merge([
            'address_id' => $this->address->id,
            'card_brand' => 'visa',
            'card_last4' => '1111',
        ], $overrides));
    }

    public function test_each_seller_is_notified_of_their_own_sales(): void
    {
        Event::fake([SaleCreated::class]);
        $this->postJson('/api/cart/items', ['product_id' => 'p19', 'color' => 'Boyalı', 'quantity' => 1]);
        $this->postJson('/api/cart/items', ['product_id' => 'p21', 'quantity' => 2]);

        $this->order()->assertCreated();

        $sellerOf = fn (string $id) => Product::find($id)->seller_id;
        Event::assertDispatchedTimes(SaleCreated::class, 2);
        Event::assertDispatched(SaleCreated::class, fn (SaleCreated $event) => $event->sellerId === $sellerOf('p19')
            && $event->items->pluck('product_id')->all() === ['p19']
            && $event->broadcastOn()->name === "private-seller.{$sellerOf('p19')}");
        Event::assertDispatched(SaleCreated::class, fn (SaleCreated $event) => $event->sellerId === $sellerOf('p21')
            && $event->broadcastWith()['new_sales'][0]['quantity'] === 2);
    }

    public function test_order_is_created_from_cart_with_database_prices(): void
    {
        $this->postJson('/api/cart/items', ['product_id' => 'p19', 'color' => 'Boyalı', 'quantity' => 2]);
        // Sepetteki kayıtlı fiyat oynansa bile sipariş ürünün güncel fiyatından hesaplanır
        $this->user->cartItems()->update(['price' => 1]);
        $price = Product::find('p19')->price;

        $response = $this->order()
            ->assertCreated()
            ->assertJsonPath('order.status', 'paid')
            ->assertJsonPath('order.items.0.unit_price', fn ($value) => $value == $price)
            ->assertJsonPath('order.items.0.quantity', 2)
            ->assertJsonPath('order.subtotal', fn ($value) => $value == $price * 2)
            ->assertJsonPath('order.vat', fn ($value) => $value == round($price * 2 * 0.2, 2))
            ->assertJsonPath('order.shipping', fn ($value) => $value == 0)
            ->assertJsonPath('order.shipping_address.district', 'Kadıköy')
            ->assertJsonPath('order.card_last4', '1111');

        $this->assertDatabaseCount('cart_items', 0);
        $this->assertMatchesRegularExpression('/^BM-\d{8}-[A-Z0-9]{6}$/', $response->json('order.number'));

        $this->getJson('/api/orders')->assertOk()->assertJsonCount(1, 'orders');
        $this->getJson('/api/orders/'.$response->json('order.number'))->assertOk();
    }

    public function test_sold_count_is_reflected_in_catalog(): void
    {
        $this->postJson('/api/cart/items', ['product_id' => 'p23', 'quantity' => 3]);
        $this->order()->assertCreated();

        $products = collect($this->getJson('/api/products')->json('products'))->keyBy('id');
        $this->assertSame(3, $products['p23']['sold_count']);
    }

    public function test_order_requires_items_and_own_address(): void
    {
        $this->order()->assertUnprocessable()->assertJsonValidationErrors('cart');

        $this->postJson('/api/cart/items', ['product_id' => 'p23', 'quantity' => 1]);
        $other = User::factory()->create()->addresses()->create($this->address->only([
            'title', 'full_name', 'phone', 'city_id', 'district_id', 'neighbourhood', 'street',
            'building_no', 'floor', 'apartment_no', 'postal_code',
        ]));

        $this->order(['address_id' => $other->id])->assertUnprocessable()->assertJsonValidationErrors('address_id');
        $this->order(['card_last4' => '12'])->assertUnprocessable()->assertJsonValidationErrors('card_last4');
    }

    public function test_users_only_see_their_own_orders(): void
    {
        $this->postJson('/api/cart/items', ['product_id' => 'p23', 'quantity' => 1]);
        $number = $this->order()->json('order.number');

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/orders')->assertOk()->assertJsonCount(0, 'orders');
        $this->getJson('/api/orders/'.$number)->assertNotFound();
    }
}
