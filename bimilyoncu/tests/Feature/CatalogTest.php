<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);
    }

    public function test_products_are_listed_from_database(): void
    {
        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(Product::count(), 'products')
            ->assertJsonPath('products.0.id', 'p19')
            ->assertJsonPath('products.0.review_count', 0);
    }

    public function test_product_detail_includes_color_images_specs_and_seller(): void
    {
        $this->getJson('/api/products/dogal-ahsap-kutuk-masa-lambasi')
            ->assertOk()
            ->assertJsonPath('product.colors.0.name', 'Krem Abajur')
            ->assertJsonPath('product.colors.0.images', ['/urunler/ahsap-aydinlatma-1.jpg'])
            ->assertJsonPath('product.colors.1.images', [])
            ->assertJsonPath('product.seller.name', "b'milyoncu");

        $this->getJson('/api/products/askili-cekmeceli-ahsap-antre-dolabi')
            ->assertOk()
            ->assertJsonPath('product.specs.0.value', '180 x 75 x 35 cm')
            ->assertJsonPath('product.seller.name', 'Akcıl Mobilya');
    }

    public function test_rating_is_computed_from_reviews_and_author_is_masked(): void
    {
        $product = Product::find('p21');
        $product->reviews()->createMany([
            ['author_name' => 'Hasan Bulut', 'rating' => 5, 'comment' => 'Harika', 'reviewed_at' => '2026-10-01'],
            ['author_name' => 'Elif Kaya', 'rating' => 4, 'comment' => 'Güzel', 'reviewed_at' => '2026-09-01'],
        ]);
        $product->reviews()->first()->replies()->create(['author_type' => 'seller', 'body' => 'Teşekkürler']);

        $response = $this->getJson('/api/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('product.rating', 4.5)
            ->assertJsonPath('product.review_count', 2)
            ->assertJsonPath('product.reviews.0.name', 'H*** B***')
            ->assertJsonPath('product.reviews.0.replies.0.author', 'seller')
            ->assertJsonPath('product.reviews.0.replies.0.name', 'Akcıl Mobilya');

        $this->assertStringNotContainsString('Hasan Bulut', $response->getContent());
        $this->assertSame(2, Review::count());
    }

    public function test_every_product_gets_unique_mixed_code(): void
    {
        $codes = Product::pluck('code');

        $this->assertCount(Product::count(), $codes->unique());
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^(?=.*[A-Z])(?=.*\d)[A-Z0-9]{10}$/', $code);
        }
        $this->getJson('/api/products')->assertJsonPath('products.0.code', fn ($code) => strlen($code) === 10);
    }

    public function test_categories_form_a_tree_and_products_keep_their_path(): void
    {
        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(3, 'categories')
            ->assertJsonPath('categories.1.slug', 'ev-bahce')
            ->assertJsonPath('categories.1.children.3.slug', 'ahsap-urunler')
            ->assertJsonPath('categories.1.children.3.children.0.slug', 'ahsap-mobilya');

        // Ürün en alttaki kategoriye bağlı; API'deki yol eskisi gibi slug'larla
        $product = Product::find('p22');
        $this->assertSame(Category::idForPath(['ev-bahce', 'ahsap-urunler', 'ahsap-mobilya']), $product->category_id);
        $this->getJson('/api/products')->assertJsonFragment(['path' => ['ev-bahce', 'ahsap-urunler', 'ahsap-mobilya']]);
    }

    public function test_storefront_lists_only_active_sellers(): void
    {
        $this->getJson('/api/sellers')->assertOk()->assertJsonCount(2, 'sellers');
        $this->getJson('/api/sellers/akcil-mobilya')->assertOk()->assertJsonPath('seller.name', 'Akcıl Mobilya');

        // Pasif mağaza şeritte görünmez, sayfası açılmaz
        \App\Models\Seller::where('slug', 'akcil-mobilya')->update(['status' => 'passive']);
        $this->getJson('/api/sellers')->assertJsonCount(1, 'sellers');
        $this->getJson('/api/sellers/akcil-mobilya')->assertNotFound();
    }

    public function test_unknown_product_returns_404(): void
    {
        $this->getJson('/api/products/olmayan-urun')->assertNotFound();
    }
}
