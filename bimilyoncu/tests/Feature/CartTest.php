<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);

        Sanctum::actingAs(User::factory()->create());
    }

    private function item(array $overrides = []): array
    {
        return array_merge(['product_id' => 'p19', 'color' => 'Boyalı', 'quantity' => 1], $overrides);
    }

    public function test_cart_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', '')->getJson('/api/cart')->assertUnauthorized();
    }

    public function test_product_details_come_from_database_not_client(): void
    {
        $this->postJson('/api/cart/items', $this->item(['name' => 'Sahte', 'price' => 1]))
            ->assertOk()
            ->assertJsonPath('items.0.id', 'p19-Boyalı')
            ->assertJsonPath('items.0.name', Product::find('p19')->name)
            ->assertJsonPath('items.0.price', fn ($value) => $value == Product::find('p19')->price)
            ->assertJsonPath('items.0.variant', 'Renk: Boyalı');
    }

    public function test_quantities_accumulate_and_are_capped_at_ten(): void
    {
        $this->postJson('/api/cart/items', $this->item(['quantity' => 8]));

        $this->postJson('/api/cart/items', $this->item(['quantity' => 5]))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.quantity', 10);

        $this->postJson('/api/cart/items', $this->item(['quantity' => 11]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
    }

    public function test_unknown_product_or_color_is_rejected(): void
    {
        $this->postJson('/api/cart/items', $this->item(['product_id' => 'yok']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');

        $this->postJson('/api/cart/items', $this->item(['color' => 'Mor']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('color');
    }

    public function test_user_can_update_and_remove_item(): void
    {
        $this->postJson('/api/cart/items', $this->item());
        $key = rawurlencode('p19-Boyalı');

        $this->patchJson("/api/cart/items/{$key}", ['quantity' => 7])
            ->assertOk()
            ->assertJsonPath('items.0.quantity', 7);

        $this->patchJson("/api/cart/items/{$key}", ['quantity' => 11])->assertUnprocessable();

        $this->deleteJson("/api/cart/items/{$key}")
            ->assertOk()
            ->assertJsonCount(0, 'items');
    }

    public function test_guest_cart_is_merged_and_unknown_products_are_skipped(): void
    {
        $this->postJson('/api/cart/items', $this->item(['quantity' => 6]));

        $this->postJson('/api/cart/merge', ['items' => [
            $this->item(['quantity' => 6]),
            ['product_id' => 'p23', 'color' => null, 'quantity' => 2],
            ['product_id' => 'kaldirilmis-urun', 'quantity' => 1],
        ]])
            ->assertOk()
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.quantity', 10)
            ->assertJsonPath('items.1.id', 'p23');
    }

    public function test_users_cannot_see_each_others_cart(): void
    {
        $this->postJson('/api/cart/items', $this->item());

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/cart')->assertOk()->assertJsonCount(0, 'items');
        $this->deleteJson('/api/cart/items/'.rawurlencode('p19-Boyalı'))->assertOk();
        $this->assertDatabaseCount('cart_items', 1);
    }
}
