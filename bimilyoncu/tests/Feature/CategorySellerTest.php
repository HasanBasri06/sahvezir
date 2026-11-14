<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\SellerUser;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\SellerUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategorySellerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CatalogSeeder::class, SellerUserSeeder::class]);
    }

    public function test_category_lists_only_its_sellers(): void
    {
        // Katalogda iki satıcı da ev-bahce'de ürün satıyor; 3d-printer'da kimse yok
        $this->getJson('/api/categories/ev-bahce/sellers')
            ->assertOk()
            ->assertJsonCount(2, 'sellers')
            ->assertJsonStructure(['sellers' => [['slug', 'name', 'brand_color']]]);

        $this->getJson('/api/categories/3d-printer/sellers')->assertOk()->assertJsonCount(0, 'sellers');
    }

    public function test_new_product_goes_into_selected_catalog_category(): void
    {
        Storage::fake('public');
        $sellerUser = SellerUser::first();
        Sanctum::actingAs($sellerUser, [], 'seller');
        // Ürün yalnızca kataloğundaki alt kategoriye eklenebilir
        $sellerUser->seller->addCategory(Category::idForPath(['3d-printer', 'yazicilar', 'fdm']));

        $this->post('/api/seller/products', [
            'name' => 'Masaüstü 3D Yazıcı',
            'price' => '9000',
            'cost' => '6000',
            'stock' => '3',
            'category_ids' => [Category::idForPath(['3d-printer', 'yazicilar', 'fdm'])],
            'images' => [['file' => UploadedFile::fake()->createWithContent('a.png', base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
            ))]],
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertDatabaseHas('category_product', [
            'category_id' => Category::idForPath(['3d-printer', 'yazicilar', 'fdm']),
        ]);
        $this->getJson('/api/categories/3d-printer/sellers')
            ->assertJsonCount(1, 'sellers')
            ->assertJsonPath('sellers.0.slug', $sellerUser->seller->slug);
    }

    public function test_seller_saves_catalog_of_leaf_categories(): void
    {
        $sellerUser = SellerUser::first();
        Sanctum::actingAs($sellerUser, [], 'seller');
        $fdm = Category::idForPath(['3d-printer', 'yazicilar', 'fdm']);
        $pla = Category::idForPath(['3d-printer', 'filamentler', 'pla']);

        // Ürünlerinin bulunduğu alt kategoriler katalogda kalmak zorunda
        $withProducts = $sellerUser->seller->products()->pluck('category_id')->unique()->values()->all();

        $this->putJson('/api/seller/catalog', ['category_ids' => [$fdm, $pla, ...$withProducts]])
            ->assertOk()
            ->assertJsonCount(2 + count($withProducts), 'category_ids');
        $this->getJson('/api/seller/auth/me')->assertJsonCount(2 + count($withProducts), 'user.seller.category_ids');

        // Kategori sayfasının satıcı filtresi kataloğa göre
        $this->getJson('/api/categories/3d-printer/sellers')
            ->assertJsonCount(1, 'sellers')
            ->assertJsonPath('sellers.0.slug', $sellerUser->seller->slug);

        // Ana kategori ya da başlık seçilemez, boş katalog kaydedilemez
        $this->putJson('/api/seller/catalog', ['category_ids' => [Category::idForPath(['3d-printer'])]])
            ->assertJsonValidationErrors(['category_ids.0']);
        $this->putJson('/api/seller/catalog', ['category_ids' => []])->assertJsonValidationErrors(['category_ids']);
    }

    public function test_products_are_moved_before_their_category_leaves_catalog(): void
    {
        $sellerUser = SellerUser::first();
        Sanctum::actingAs($sellerUser, [], 'seller');
        $seller = $sellerUser->seller;
        $from = $seller->products()->value('category_id');
        $to = Category::idForPath(['ev-bahce', 'ahsap-urunler', 'ahsap-dekor']);
        $productIds = $seller->products()->where('category_id', $from)->pluck('id')->all();
        $otherIds = $seller->products()->where('category_id', '!=', $from)->pluck('category_id')->unique()->values()->all();

        // Ürünü olan alt kategori katalogdan çıkarılamaz
        $this->putJson('/api/seller/catalog', ['category_ids' => [$to, ...$otherIds]])
            ->assertJsonValidationErrors(['category_ids']);

        $this->postJson('/api/seller/catalog/move-products', [
            'product_ids' => $productIds,
            'from_category_ids' => [$from],
            'category_id' => $to,
        ])
            ->assertOk()
            ->assertJsonPath('moved', count($productIds));
        $this->assertSame([$to], $seller->products()->whereIn('id', $productIds)->pluck('category_id')->unique()->values()->all());

        $this->putJson('/api/seller/catalog', ['category_ids' => [$to, ...$otherIds]])->assertOk();

        // Başka satıcının ürünü aktarılamaz
        $foreign = \App\Models\Product::where('seller_id', '!=', $seller->id)->value('id');
        $this->postJson('/api/seller/catalog/move-products', [
            'product_ids' => [$foreign],
            'from_category_ids' => [$from],
            'category_id' => $to,
        ])
            ->assertJsonPath('moved', 0);
    }
}
