<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_viewed_products_are_listed_latest_first_with_price_drops(): void
    {
        $this->travelTo(now()->subMinutes(2));
        $this->postJson('/api/viewed-products/p19')->assertNoContent();
        $this->travelBack();
        $this->postJson('/api/viewed-products/p20')->assertNoContent();

        // Bakıldıktan sonra p19 ucuzladı; sıra yine en son bakılan önce
        $p19 = Product::find('p19');
        $oldPrice = $p19->price;
        $p19->update(['price' => $oldPrice - 10]);

        $this->getJson('/api/viewed-products')
            ->assertOk()
            ->assertJsonCount(2, 'products')
            ->assertJsonPath('products.0.id', 'p20')
            ->assertJsonPath('products.0.price_dropped', false)
            ->assertJsonPath('products.1.id', 'p19')
            ->assertJsonPath('products.1.price_dropped', true)
            ->assertJsonPath('products.1.viewed_price', fn ($value) => $value == $oldPrice);
    }

    public function test_viewing_again_updates_the_same_record(): void
    {
        $this->postJson('/api/viewed-products/p19');
        $this->postJson('/api/viewed-products/p19');

        $this->assertDatabaseCount('product_views', 1);
        $this->postJson('/api/viewed-products/yok')->assertNotFound();
    }

    public function test_guests_cannot_track_views(): void
    {
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/viewed-products/p19')->assertUnauthorized();
    }

    public function test_trending_lists_most_viewed_products_first(): void
    {
        // p20'ye iki kullanıcı, p19'a bir kullanıcı baktı
        $this->postJson('/api/viewed-products/p20');
        $this->postJson('/api/viewed-products/p19');
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/viewed-products/p20');

        $this->getJson('/api/products/trending')
            ->assertOk()
            ->assertJsonPath('products.0.id', 'p20')
            ->assertJsonPath('products.1.id', 'p19');
    }
}
