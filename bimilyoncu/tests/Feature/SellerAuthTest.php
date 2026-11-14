<?php

namespace Tests\Feature;

use App\Models\Seller;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\SellerUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CatalogSeeder::class, SellerUserSeeder::class]);
    }

    private function sellerToken(): string
    {
        return $this->postJson('/api/seller/auth/login', [
            'email' => 'satici@akcilmobilya.com',
            'password' => 'Satici1234!',
        ])->assertOk()->assertJsonPath('user.seller.name', 'Akcıl Mobilya')->json('token');
    }

    public function test_seller_sees_only_own_products_on_dashboard(): void
    {
        $products = $this->withToken($this->sellerToken())->getJson('/api/seller/dashboard')
            ->assertOk()
            ->json('products');

        $akcil = Seller::where('slug', 'akcil-mobilya')->first();
        $this->assertEqualsCanonicalizing($akcil->products()->pluck('id')->all(), array_column($products, 'id'));
    }

    public function test_seller_token_is_rejected_by_customer_routes(): void
    {
        $token = $this->sellerToken();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/cart')->assertUnauthorized();
        $this->withToken($token)->getJson('/api/auth/user')->assertUnauthorized();
    }

    public function test_customer_token_is_rejected_by_seller_routes(): void
    {
        $token = User::factory()->create()->createToken('auth')->plainTextToken;

        $this->withToken($token)->getJson('/api/seller/dashboard')->assertUnauthorized();
    }

    public function test_customer_credentials_do_not_work_on_seller_login(): void
    {
        User::factory()->create(['email' => 'musteri@example.com', 'password' => 'gizli-sifre']);

        $this->postJson('/api/seller/auth/login', ['email' => 'musteri@example.com', 'password' => 'gizli-sifre'])
            ->assertUnprocessable();
    }
}
