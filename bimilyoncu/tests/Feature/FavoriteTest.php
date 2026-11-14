<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);
    }

    public function test_favorites_require_authentication(): void
    {
        $this->postJson('/api/favorites/p19')->assertUnauthorized();
        $this->getJson('/api/favorites')->assertUnauthorized();
    }

    public function test_user_can_add_list_and_remove_favorites(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/favorites/p19')->assertOk()->assertJsonPath('ids', ['p19']);
        // Aynı ürün ikinci kez eklenmez
        $this->postJson('/api/favorites/p19')->assertOk()->assertJsonCount(1, 'ids');
        $this->travel(1)->seconds();
        $this->postJson('/api/favorites/p22')->assertOk()->assertJsonCount(2, 'ids');

        $this->getJson('/api/favorites')
            ->assertOk()
            ->assertJsonCount(2, 'products')
            ->assertJsonPath('products.0.id', 'p22')
            ->assertJsonPath('products.0.seller.name', 'Akcıl Mobilya');

        $this->deleteJson('/api/favorites/p19')->assertOk()->assertJsonPath('ids', ['p22']);
        $this->postJson('/api/favorites/olmayan')->assertNotFound();
    }

    public function test_favorites_can_be_grouped_under_lists(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Kategorisiz
        $this->postJson('/api/favorites/p19')->assertOk();
        // Yeni kategori açılarak
        $this->postJson('/api/favorites/p22', ['new_list' => 'Salon'])->assertOk();
        $salon = $user->favoriteLists()->where('name', 'Salon')->value('id');
        // Var olan kategoriye; aynı adla "yeni" kategori de mevcut olana eklenir
        $this->postJson('/api/favorites/p23', ['list_id' => $salon])->assertOk();
        $this->postJson('/api/favorites/p21', ['new_list' => 'Salon'])->assertOk();
        $this->assertSame(1, $user->favoriteLists()->count());

        $response = $this->getJson('/api/favorites')
            ->assertOk()
            ->assertJsonPath('lists.0.name', 'Salon')
            ->assertJsonPath('lists.0.count', 3);
        $byId = collect($response->json('products'))->keyBy('id');
        $this->assertNull($byId['p19']['favorite_list_id']);
        $this->assertSame($salon, $byId['p22']['favorite_list_id']);

        // Kategorisi değiştirilebilir (kategorisize taşıma)
        $this->postJson('/api/favorites/p22', ['list_id' => null])->assertOk();
        $this->getJson('/api/favorites/lists')->assertJsonPath('lists.0.count', 2);
    }

    public function test_cannot_add_to_someone_elses_list(): void
    {
        $other = User::factory()->create();
        $list = $other->favoriteLists()->create(['name' => 'Gizli']);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/favorites/p19', ['list_id' => $list->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('list_id');
    }

    public function test_users_only_see_their_own_favorites(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/favorites/p19');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/favorites/ids')->assertOk()->assertJsonCount(0, 'ids');
    }
}
