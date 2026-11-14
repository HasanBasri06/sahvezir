<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'askili-cekmeceli-ahsap-antre-dolabi';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);
    }

    /** Ürünü satın almış bir alıcı */
    private function buyer(): User
    {
        $user = User::factory()->create(['name' => 'Selin Aydın']);
        $order = $user->orders()->create([
            'number' => 'BM-TEST-'.$user->id, 'status' => 'paid', 'subtotal' => 1, 'vat' => 0, 'shipping' => 0,
            'total' => 1, 'shipping_address' => [], 'card_last4' => '1111',
        ]);
        $order->items()->create([
            'product_id' => 'p22', 'name' => 'Dolap', 'unit_price' => 1, 'quantity' => 1, 'line_total' => 1,
        ]);

        return $user;
    }

    public function test_only_buyers_can_review_once(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/products/'.self::SLUG.'/review-status')->assertOk()->assertJsonPath('can_review', false);
        $this->postJson('/api/products/'.self::SLUG.'/reviews', ['rating' => 5, 'comment' => 'Çok güzel bir dolap.'])
            ->assertUnprocessable();

        Sanctum::actingAs($this->buyer());
        $this->getJson('/api/products/'.self::SLUG.'/review-status')->assertOk()->assertJsonPath('can_review', true);

        $this->postJson('/api/products/'.self::SLUG.'/reviews', ['rating' => 4, 'comment' => 'Çok güzel bir dolap.'])
            ->assertCreated()
            ->assertJsonPath('review.name', 'S*** A***');

        $this->postJson('/api/products/'.self::SLUG.'/reviews', ['rating' => 5, 'comment' => 'İkinci yorumum burada.'])
            ->assertUnprocessable();
        $this->getJson('/api/products/'.self::SLUG.'/review-status')
            ->assertJsonPath('can_review', false)
            ->assertJsonPath('review_id', Product::find('p22')->reviews()->value('id'));

        $this->getJson('/api/products/'.self::SLUG)
            ->assertJsonPath('product.rating', 4)
            ->assertJsonPath('product.review_count', 1);
    }

    public function test_review_owner_and_seller_can_reply_back_and_forth(): void
    {
        $buyer = $this->buyer();
        Sanctum::actingAs($buyer);
        $reviewId = $this->postJson('/api/products/'.self::SLUG.'/reviews', ['rating' => 5, 'comment' => 'Harika bir ürün oldu.'])
            ->json('review.id');

        $review = Product::find('p22')->reviews()->find($reviewId);
        $review->replies()->create(['author_type' => 'seller', 'body' => 'Teşekkür ederiz!']);

        $this->postJson("/api/reviews/{$reviewId}/replies", ['body' => 'Montaj vidası eksikti.'])->assertOk();
        $review->replies()->create(['author_type' => 'seller', 'body' => 'Hemen gönderiyoruz.']);

        $this->postJson("/api/reviews/{$reviewId}/replies", ['body' => 'Ulaştı, teşekkürler.'])
            ->assertOk()
            ->assertJsonCount(4, 'review.replies')
            ->assertJsonPath('review.replies.0.author', 'seller')
            ->assertJsonPath('review.replies.0.name', 'Akcıl Mobilya')
            ->assertJsonPath('review.replies.1.author', 'customer')
            ->assertJsonPath('review.replies.1.name', 'S*** A***')
            ->assertJsonPath('review.replies.3.body', 'Ulaştı, teşekkürler.');
    }

    public function test_unpaid_orders_do_not_grant_review_rights(): void
    {
        $buyer = $this->buyer();
        $buyer->orders()->update(['status' => 'cancelled']);
        Sanctum::actingAs($buyer);

        $this->getJson('/api/products/'.self::SLUG.'/review-status')->assertJsonPath('can_review', false);
        $this->postJson('/api/products/'.self::SLUG.'/reviews', ['rating' => 5, 'comment' => 'Çok güzel bir dolap.'])
            ->assertUnprocessable();
    }

    public function test_others_cannot_reply_to_someones_review(): void
    {
        Sanctum::actingAs($this->buyer());
        $reviewId = $this->postJson('/api/products/'.self::SLUG.'/reviews', ['rating' => 5, 'comment' => 'Harika bir ürün oldu.'])
            ->json('review.id');

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/reviews/{$reviewId}/replies", ['body' => 'Araya giriyorum'])->assertForbidden();
    }
}
