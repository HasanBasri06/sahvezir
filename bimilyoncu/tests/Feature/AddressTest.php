<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\District;
use App\Models\User;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LocationSeeder::class);
    }

    private function district(int $cityId, string $name): District
    {
        return District::where('city_id', $cityId)->where('name', $name)->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Ev',
            'full_name' => 'Ayşe Yılmaz',
            'phone' => '0532 123 45 67',
            'city_id' => 34,
            'district_id' => $this->district(34, 'Kadıköy')->id,
            'neighbourhood' => 'Caferağa Mah.',
            'street' => 'Moda Cad.',
            'building_no' => '12',
            'floor' => '2',
            'apartment_no' => '3',
            'postal_code' => '34710',
        ], $overrides);
    }

    public function test_cities_and_districts_are_listed(): void
    {
        $this->getJson('/api/cities')->assertOk()->assertJsonCount(81, 'cities');

        $this->getJson('/api/cities/34/districts')
            ->assertOk()
            ->assertJsonCount(39, 'districts')
            ->assertJsonPath('districts.0.name', 'Adalar');
    }

    public function test_addresses_require_authentication(): void
    {
        $this->getJson('/api/addresses')->assertUnauthorized();
    }

    public function test_user_can_add_and_list_addresses(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/addresses', $this->payload())
            ->assertCreated()
            ->assertJsonPath('address.city.name', 'İstanbul')
            ->assertJsonPath('address.district.name', 'Kadıköy')
            ->assertJsonPath('address.postal_code', '34710');

        $this->postJson('/api/addresses', $this->payload(['title' => 'İş']))->assertCreated();

        $this->getJson('/api/addresses')
            ->assertOk()
            ->assertJsonCount(2, 'addresses')
            ->assertJsonPath('addresses.0.title', 'İş');
    }

    public function test_required_address_parts_are_validated(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/addresses', $this->payload([
            'phone' => '123',
            'building_no' => '',
            'floor' => '',
            'apartment_no' => '',
            'postal_code' => '347',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone', 'building_no', 'floor', 'apartment_no', 'postal_code']);
    }

    public function test_district_must_belong_to_city(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/addresses', $this->payload(['city_id' => 6]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('district_id');
    }

    public function test_postal_code_is_not_matched_against_district(): void
    {
        Sanctum::actingAs(User::factory()->create());

        // Yalnızca 5 hane kontrol edilir; ilçeyle eşleşmesi aranmaz
        $this->postJson('/api/addresses', $this->payload(['postal_code' => '06420']))->assertCreated();
    }

    public function test_address_count_is_limited(): void
    {
        $user = User::factory()->create();
        $user->addresses()->createMany(array_fill(0, Address::MAX_PER_USER, $this->payload()));
        Sanctum::actingAs($user);

        $this->postJson('/api/addresses', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_users_only_see_their_own_addresses(): void
    {
        User::factory()->create()->addresses()->create($this->payload());
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/addresses')->assertOk()->assertJsonCount(0, 'addresses');
    }
}
