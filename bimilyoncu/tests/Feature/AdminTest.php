<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerUser;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\SellerUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function pendingSeller(): Seller
    {
        $seller = Seller::create(['slug' => 'yeni-magaza', 'name' => 'Yeni Mağaza', 'status' => Seller::STATUS_PENDING]);
        $seller->users()->create(['name' => 'Yeni Mağaza', 'email' => 'yeni@magaza.com', 'password' => 'gizli-sifre-1']);

        return $seller;
    }

    public function test_admin_logs_in_with_own_guard(): void
    {
        Admin::create(['name' => 'Yönetici', 'email' => 'admin@example.com', 'password' => 'gizli-sifre-1']);

        $token = $this->postJson('/api/admin/auth/login', ['email' => 'admin@example.com', 'password' => 'gizli-sifre-1'])
            ->assertOk()
            ->json('token');

        $this->getJson('/api/admin/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('user.email', 'admin@example.com');
    }

    public function test_customer_and_seller_tokens_cannot_use_admin_api(): void
    {
        $this->seed([CatalogSeeder::class, SellerUserSeeder::class]);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/admin/sellers?status=pending')->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(SellerUser::first(), [], 'seller');
        $this->getJson('/api/admin/sellers?status=pending')->assertUnauthorized();
    }

    public function test_admin_approves_pending_seller(): void
    {
        $seller = $this->pendingSeller();
        Sanctum::actingAs(Admin::create(['name' => 'Y', 'email' => 'a@example.com', 'password' => 'x']), [], 'admin');

        $this->getJson('/api/admin/sellers?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'sellers')
            ->assertJsonPath('sellers.0.email', 'yeni@magaza.com')
            ->assertJsonPath('counts.pending', 1);

        $this->postJson("/api/admin/sellers/{$seller->id}/review", ['status' => 'approved'])->assertOk();
        $this->assertSame(Seller::STATUS_APPROVED, $seller->fresh()->status);
        $this->assertNotNull($seller->fresh()->reviewed_at);

        // Bir kez değerlendirilen satıcı tekrar değerlendirilemez
        $this->postJson("/api/admin/sellers/{$seller->id}/review", ['status' => 'rejected'])
            ->assertJsonValidationErrors(['status']);
    }

    public function test_pending_sellers_products_are_published_after_approval(): void
    {
        $this->seed(CatalogSeeder::class);
        $seller = $this->pendingSeller();
        Sanctum::actingAs($seller->users()->first(), [], 'seller');

        // Onay bekleyen mağaza ürün ekleyebilir (form doğrulamasına kadar gelir)
        $this->postJson('/api/seller/products', ['name' => 'Ürün'])->assertUnprocessable();
        $this->getJson('/api/seller/auth/me')->assertJsonPath('user.seller.status', 'pending');

        // Ürünü sitede görünmez, sepete eklenemez; mağaza onaylanınca yayına girer
        $product = Product::first()->replicate()->fill(['id' => 'p999', 'slug' => 'bekleyen-urun']);
        $product->seller_id = $seller->id;
        // Kayıtta yeni ürün kodu üretilsin
        $product->code = null;
        $product->save();

        $this->getJson('/api/products/bekleyen-urun')->assertNotFound();
        $this->assertNotContains('p999', collect($this->getJson('/api/products')->json('products'))->pluck('id'));

        $seller->update(['status' => Seller::STATUS_APPROVED]);
        $this->getJson('/api/products/bekleyen-urun')->assertOk();
    }

    public function test_rejected_seller_cannot_add_products(): void
    {
        $seller = $this->pendingSeller();
        $seller->update(['status' => Seller::STATUS_REJECTED]);
        Sanctum::actingAs($seller->users()->first(), [], 'seller');

        $this->postJson('/api/seller/products', ['name' => 'Ürün'])->assertForbidden();
    }

    public function test_admin_deactivates_and_reactivates_seller(): void
    {
        $this->seed(CatalogSeeder::class);
        $seller = Seller::where('slug', 'akcil-mobilya')->firstOrFail();
        $productSlug = $seller->products()->value('slug');
        Sanctum::actingAs(Admin::create(['name' => 'Y', 'email' => 'a@example.com', 'password' => 'x']), [], 'admin');

        $this->postJson("/api/admin/sellers/{$seller->id}/active", ['active' => false])->assertOk();
        $this->assertSame(Seller::STATUS_PASSIVE, $seller->fresh()->status);

        // Pasif satıcının ürünleri sitede görünmez; Satıcılarımız'da pasif olarak listelenir
        $this->getJson("/api/products/{$productSlug}")->assertNotFound();
        $this->getJson('/api/admin/sellers?status=approved,passive')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'akcil-mobilya', 'status' => 'passive']);
        // Satıcının kategori başına ürün sayıları
        $listed = collect($this->getJson('/api/admin/sellers?status=approved,passive')->json('sellers'))->firstWhere('slug', 'akcil-mobilya');
        $this->assertSame($seller->products()->count(), collect($listed['categories'])->sum('count'));
        $this->assertSame('Ahşap Mobilya', $listed['categories'][0]['name']);
        $this->assertArrayHasKey('revenue', $listed);
        $this->assertArrayHasKey('profit', $listed);

        $this->postJson("/api/admin/sellers/{$seller->id}/active", ['active' => true])->assertOk();
        $this->getJson("/api/products/{$productSlug}")->assertOk();

        // Onay bekleyen satıcı bu yolla aktif edilemez
        $pending = $this->pendingSeller();
        $this->postJson("/api/admin/sellers/{$pending->id}/active", ['active' => true])
            ->assertJsonValidationErrors(['active']);
    }

    public function test_admin_adds_and_renames_categories(): void
    {
        Sanctum::actingAs(Admin::create(['name' => 'Y', 'email' => 'a@example.com', 'password' => 'x']), [], 'admin');

        $root = $this->postJson('/api/admin/categories', ['name' => 'Oyun Ürünleri'])
            ->assertCreated()
            ->assertJsonPath('category.slug', 'oyun-urunleri')
            ->json('category.id');
        $group = $this->postJson('/api/admin/categories', ['name' => 'Kutu Oyunları', 'parent_id' => $root])->json('category.id');
        $leaf = $this->postJson('/api/admin/categories', ['name' => 'Strateji', 'parent_id' => $group])->json('category.id');

        // 3 seviyeden derine inilemez, aynı seviyede aynı ad olamaz
        $this->postJson('/api/admin/categories', ['name' => 'Derin', 'parent_id' => $leaf])->assertJsonValidationErrors(['parent_id']);
        $this->postJson('/api/admin/categories', ['name' => 'kutu oyunları', 'parent_id' => $root])->assertJsonValidationErrors(['name']);

        // Ad değişir, adres (slug) değişmez
        $this->patchJson("/api/admin/categories/{$group}", ['name' => 'Masa Oyunları'])
            ->assertOk()
            ->assertJsonPath('category.slug', 'kutu-oyunlari');

        $this->getJson('/api/admin/categories')->assertOk()->assertJsonFragment(['name' => 'Masa Oyunları']);
        $this->getJson('/api/categories')->assertJsonFragment(['slug' => 'strateji']);
    }

    public function test_category_list_counts_products_on_every_level(): void
    {
        $this->seed(CatalogSeeder::class);
        Sanctum::actingAs(Admin::create(['name' => 'Y', 'email' => 'a@example.com', 'password' => 'x']), [], 'admin');

        $categories = collect($this->getJson('/api/admin/categories')->assertOk()->json('categories'));
        $evBahce = $categories->firstWhere('slug', 'ev-bahce');
        $ahsap = collect($evBahce['children'])->firstWhere('slug', 'ahsap-urunler');
        $mobilya = collect($ahsap['children'])->firstWhere('slug', 'ahsap-mobilya');

        // Üst seviye altındaki tüm ürünleri sayar
        $this->assertSame(DB::table('category_product')->distinct()->count('product_id'), $evBahce['product_count']);
        $this->assertGreaterThanOrEqual($mobilya['product_count'], $ahsap['product_count']);
        $this->assertGreaterThan(0, $mobilya['product_count']);
        $this->assertArrayNotHasKey('product_ids', $evBahce);
    }

    public function test_admin_lists_products_paginated_and_filtered_by_category(): void
    {
        $this->seed(CatalogSeeder::class);
        Sanctum::actingAs(Admin::create(['name' => 'Y', 'email' => 'a@example.com', 'password' => 'x']), [], 'admin');
        $total = Product::count();

        $this->getJson('/api/admin/products')
            ->assertOk()
            ->assertJsonPath('meta.total', $total)
            ->assertJsonPath('meta.per_page', 30);

        // Alt kategori ve üst seviye filtresi
        $mobilya = Category::idForPath(['ev-bahce', 'ahsap-urunler', 'ahsap-mobilya']);
        $inMobilya = DB::table('category_product')->where('category_id', $mobilya)->count();
        $this->getJson("/api/admin/products?category_id={$mobilya}")->assertJsonPath('meta.total', $inMobilya);
        $this->getJson('/api/admin/products?category_id='.Category::idForPath(['ev-bahce']))->assertJsonPath('meta.total', $total);
        $this->getJson('/api/admin/products?category_id='.Category::idForPath(['3d-printer']))->assertJsonPath('meta.total', 0);
    }
}
