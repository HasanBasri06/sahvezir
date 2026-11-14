<?php

namespace Tests\Feature;

use App\Models\SellerUser;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\SellerUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CatalogSeeder::class, SellerUserSeeder::class]);
    }

    /** Verilen İstanbul saatinde ödenmiş bir sipariş satırı */
    private function sale(string $productId, string $istanbulTime, int $quantity = 1, string $status = 'paid'): void
    {
        $order = User::factory()->create()->orders()->create([
            'number' => 'BM-'.uniqid(), 'status' => $status, 'subtotal' => 1, 'vat' => 0, 'shipping' => 0,
            'total' => 1, 'shipping_address' => [], 'card_last4' => '1111',
        ]);
        $order->forceFill(['created_at' => Carbon::parse($istanbulTime, 'Europe/Istanbul')->utc()])->save();
        $order->items()->create([
            'product_id' => $productId, 'name' => $productId, 'unit_price' => 100, 'quantity' => $quantity, 'line_total' => 100 * $quantity,
        ]);
    }

    public function test_report_lists_own_sales_within_range_with_times(): void
    {
        $this->sale('p22', '2026-10-10 09:30');
        $this->sale('p23', '2026-10-12 21:15', 2);
        // Aralık dışı, başka satıcının ürünü ve ödenmemiş sipariş rapora girmez
        $this->sale('p22', '2026-10-20 10:00');
        $this->sale('p19', '2026-10-11 12:00');
        $this->sale('p21', '2026-10-11 12:00', 1, 'cancelled');
        // Gün sınırı İstanbul saatine göre: 15 Ekim 00:30 (UTC'de 14 Ekim) aralığa girmez
        $this->sale('p21', '2026-10-15 00:30');

        Sanctum::actingAs(SellerUser::first(), [], 'seller');

        $this->getJson('/api/seller/reports/sales?from=2026-10-10&to=2026-10-14')
            ->assertOk()
            ->assertJsonCount(2, 'sales')
            ->assertJsonPath('sales.0.product_id', 'p22')
            ->assertJsonPath('sales.0.ordered_at', fn ($value) => str_starts_with(Carbon::parse($value)->setTimezone('Europe/Istanbul')->format('Y-m-d H:i'), '2026-10-10 09:30'))
            ->assertJsonPath('sales.1.quantity', 2)
            ->assertJsonPath('totals.items', 3)
            ->assertJsonPath('totals.orders', 2);

        $this->getJson('/api/seller/reports/sales?from=2026-10-10&to=2026-10-14&products[]=p23')
            ->assertOk()
            ->assertJsonCount(1, 'sales')
            ->assertJsonPath('sales.0.product_id', 'p23');

        // Başka satıcının ürünü seçilse de gelmez
        $this->getJson('/api/seller/reports/sales?from=2026-10-10&to=2026-10-14&products[]=p19')
            ->assertOk()
            ->assertJsonCount(0, 'sales');
    }

    public function test_report_range_is_validated(): void
    {
        Sanctum::actingAs(SellerUser::first(), [], 'seller');

        $this->getJson('/api/seller/reports/sales?from=2026-10-14&to=2026-10-10')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
        $this->getJson('/api/seller/reports/sales?from=2024-01-01&to=2026-10-10')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }
}
