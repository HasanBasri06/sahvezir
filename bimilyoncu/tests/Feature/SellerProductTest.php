<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerUser;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\SellerUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerProductTest extends TestCase
{
    use RefreshDatabase;

    /** Akcıl Mobilya'nın ürünü */
    private const OWN = 'p22';

    /** b'milyoncu'nun ürünü */
    private const OTHER = 'p19';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CatalogSeeder::class, SellerUserSeeder::class]);
        Sanctum::actingAs(SellerUser::first(), [], 'seller');
    }

    private function url(string $path = '', string $id = self::OWN): string
    {
        return "/api/seller/products/{$id}{$path}";
    }

    private function png(string $name): UploadedFile
    {
        // GD olmadan da çalışsın diye gerçek 1x1 PNG
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));
    }

    public function test_seller_can_create_product_with_specs_colors_and_color_images(): void
    {
        Storage::fake('public');
        $payload = [
            'name' => 'Işıklı Ahşap Kahve İstasyonu',
            'description' => "İlk paragraf.

İkinci paragraf.",
            'price' => '1500',
            'cost' => '900',
            'stock' => '8',
            'category_ids' => [Category::idForPath(['ev-bahce', 'ahsap-urunler', 'ahsap-mobilya'])],
            'specs' => [['label' => 'Malzeme', 'value' => 'Meşe'], ['label' => 'Ölçü', 'value' => '80 x 60 cm']],
            'colors' => [['name' => 'Ceviz', 'hex' => '#5C3A21'], ['name' => 'Beyaz', 'hex' => '#ffffff']],
            'images' => [
                ['file' => $this->png('genel.png')],
                ['file' => $this->png('ceviz.png'), 'color' => '0'],
            ],
        ];

        $response = $this->post('/api/seller/products', $payload, ['Accept' => 'application/json'])->assertCreated();
        $product = Product::with(['colors', 'images', 'specs'])->find($response->json('product.id'));

        // Aynı adla eklenen ikinci ürünün slug'ı çakışmaz
        $second = $this->post('/api/seller/products', [...$payload, 'images' => [['file' => $this->png('a.png')]]], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertSame('isikli-ahsap-kahve-istasyonu', $product->slug);
        $this->assertSame('isikli-ahsap-kahve-istasyonu-2', $second->json('product.slug'));
        $this->assertSame('p24', $product->id);
        $this->assertSame(['İlk paragraf.', 'İkinci paragraf.'], $product->description);
        $this->assertSame(['Malzeme', 'Ölçü'], $product->specs->pluck('label')->all());
        $this->assertSame('#5c3a21', $product->colors->first()->hex);
        $this->assertNull($product->images[0]->product_color_id);
        $this->assertSame($product->colors->first()->id, $product->images[1]->product_color_id);
        $this->assertSame($product->images[0]->path, $product->image);
        Storage::disk('public')->assertExists(substr($product->image, strlen('/storage/')));

        // Satıcının kendi ürünü olarak görünür, panelde en üstte ve mağazada listelenir
        $this->getJson('/api/seller/products/'.$product->id)->assertOk();
        $this->travel(1)->seconds();
        $newest = $this->post('/api/seller/products', [...$payload, 'images' => [['file' => $this->png('b.png')]]], ['Accept' => 'application/json'])
            ->json('product.id');
        $this->getJson('/api/seller/dashboard')->assertJsonPath('products.0.id', $newest);
        $this->getJson('/api/products/'.$product->slug)->assertOk()->assertJsonPath('product.seller.name', 'Akcıl Mobilya');
    }

    public function test_product_can_be_fully_edited(): void
    {
        Storage::fake('public');
        $product = Product::with(['colors', 'images'])->find(self::OWN);
        [$white, $cream] = [$product->colors[0], $product->colors[1]];
        $keep = $product->images[1];

        $response = $this->post($this->url(), [
            'name' => 'Yenilenmiş Antre Dolabı',
            'description' => "Birinci.

İkinci.",
            'price' => '3000',
            'cost' => '1800',
            'stock' => '4',
            'category_ids' => [Category::idForPath(['ev-bahce', 'ahsap-urunler', 'ahsap-mobilya'])],
            'specs' => [['label' => 'Ölçü', 'value' => '180 x 75 x 35 cm']],
            // Beyaz kalır (adı değişir), krem çıkarılır, yeni renk eklenir
            'colors' => [['id' => $white->id, 'name' => 'Kar Beyazı', 'hex' => '#FFFFFF'], ['name' => 'Siyah', 'hex' => '#111111']],
            // Yeni görsel kapak olur (yalnızca siyah için), eski ikinci görsel kalır, ilki silinir
            'images' => [
                ['file' => $this->png('yeni.png'), 'color' => '1'],
                ['id' => $keep->id],
            ],
        ], ['Accept' => 'application/json'])->assertOk();

        $product->refresh()->load(['colors', 'images', 'specs']);
        $this->assertSame('Yenilenmiş Antre Dolabı', $product->name);
        $this->assertSame('askili-cekmeceli-ahsap-antre-dolabi', $product->slug);
        $this->assertSame(['Birinci.', 'İkinci.'], $product->description);
        $this->assertEquals(3000, $product->price);
        $this->assertEquals(1800, $product->cost);
        $this->assertSame(4, $product->stock);
        $this->assertSame(['Ölçü'], $product->specs->pluck('label')->all());
        $this->assertSame(['Kar Beyazı', 'Siyah'], $product->colors->pluck('name')->all());
        $this->assertSame($white->id, $product->colors[0]->id);
        $this->assertNull($product->colors->firstWhere('id', $cream->id));
        $this->assertCount(2, $product->images);
        $this->assertSame($product->colors[1]->id, $product->images[0]->product_color_id);
        $this->assertSame($keep->id, $product->images[1]->id);
        $this->assertSame($product->images[0]->path, $product->image);
        $response->assertJsonPath('product.path', ['ev-bahce', 'ahsap-urunler', 'ahsap-mobilya']);
    }

    public function test_editing_price_keeps_or_clears_discount(): void
    {
        Storage::fake('public');
        Product::whereKey(self::OWN)->update(['price' => 2000, 'old_price' => 2750]);
        $product = Product::with('images')->find(self::OWN);
        $base = [
            'name' => $product->name, 'cost' => 1200, 'stock' => 5, 'category_ids' => [Category::idForPath(['ev-bahce', 'ahsap-urunler', 'ahsap-mobilya'])], 'images' => [['id' => $product->images[0]->id]],
        ];

        // Asıl fiyat yükselir, indirimli fiyat korunur
        $this->post($this->url(), [...$base, 'price' => '3000'], ['Accept' => 'application/json'])->assertOk();
        $this->assertEquals([2000, 3000], [Product::find(self::OWN)->price, Product::find(self::OWN)->old_price]);

        // Asıl fiyat indirimli fiyatın altına inerse indirim kalkar
        $this->post($this->url(), [...$base, 'price' => '1500'], ['Accept' => 'application/json'])->assertOk();
        $this->assertEquals([1500, null], [Product::find(self::OWN)->price, Product::find(self::OWN)->old_price]);
    }

    public function test_cannot_edit_with_other_products_images(): void
    {
        $foreignImage = Product::with('images')->find('p21')->images[0];

        $this->post($this->url(), [
            'name' => 'Dolap', 'price' => '100', 'cost' => '50', 'stock' => '1', 'category_ids' => [Category::idForPath(['ev-bahce', 'ahsap-urunler', 'ahsap-mobilya'])], 'images' => [['id' => $foreignImage->id]],
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('images.0.id');
    }

    public function test_new_product_is_validated(): void
    {
        $this->postJson('/api/seller/products', [
            'name' => 'Ab',
            'price' => 0,
            'colors' => [['name' => 'Kırmızı', 'hex' => 'kirmizi']],
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'name', 'price', 'stock', 'category_ids', 'images', 'colors.0.hex',
        ]);
    }

    public function test_product_categories_must_come_from_seller_catalog(): void
    {
        // Kataloğunda olmayan (ya da ana/başlık) kategori seçilemez
        $this->postJson('/api/seller/products', ['category_ids' => [Category::idForPath(['ev-bahce', 'ahsap-urunler'])]])
            ->assertJsonValidationErrors(['category_ids.0' => 'Yalnızca kataloğundaki alt kategoriler seçilebilir.']);
        $this->postJson('/api/seller/products', ['category_ids' => [Category::idForPath(['3d-printer', 'yazicilar', 'fdm'])]])
            ->assertJsonValidationErrors(['category_ids.0']);
    }

    public function test_product_can_be_in_several_catalog_categories(): void
    {
        Storage::fake('public');
        $mobilya = Category::idForPath(['ev-bahce', 'ahsap-urunler', 'ahsap-mobilya']);
        $dekor = Category::idForPath(['ev-bahce', 'ahsap-urunler', 'ahsap-dekor']);
        SellerUser::first()->seller->addCategory($dekor);

        $id = $this->post('/api/seller/products', [
            'name' => 'Ahşap Raf', 'price' => '900', 'cost' => '500', 'stock' => '2',
            'category_ids' => [$dekor, $mobilya],
            'images' => [['file' => $this->png('raf.png')]],
        ], ['Accept' => 'application/json'])->assertCreated()->json('product.id');

        // İlk seçilen ana kategori; ürün iki kategorinin sayfasında da listelenir
        $product = Product::find($id);
        $this->assertSame($dekor, $product->category_id);
        $this->assertEqualsCanonicalizing([$dekor, $mobilya], $product->categories()->pluck('categories.id')->all());
        $this->getJson('/api/products')->assertJsonFragment([
            'paths' => [['ev-bahce', 'ahsap-urunler', 'ahsap-dekor'], ['ev-bahce', 'ahsap-urunler', 'ahsap-mobilya']],
        ]);
    }

    public function test_seller_cannot_touch_other_sellers_products(): void
    {
        $this->getJson($this->url('', self::OTHER))->assertNotFound();
        $this->patchJson($this->url('', self::OTHER), ['stock' => 0])->assertNotFound();
        $this->deleteJson($this->url('', self::OTHER))->assertNotFound();
        $this->assertDatabaseHas('products', ['id' => self::OTHER]);
    }

    public function test_stock_and_name_can_be_updated(): void
    {
        $this->patchJson($this->url(), ['stock' => 7, 'name' => 'Yeni Antre Dolabı'])
            ->assertOk()
            ->assertJsonPath('product.stock', 7)
            ->assertJsonPath('product.name', 'Yeni Antre Dolabı')
            ->assertJsonPath('product.slug', 'askili-cekmeceli-ahsap-antre-dolabi');

        $this->patchJson($this->url(), ['stock' => -1])->assertUnprocessable()->assertJsonValidationErrors('stock');
    }

    public function test_discount_can_be_applied_and_removed(): void
    {
        $original = Product::find(self::OWN)->price;

        $this->postJson($this->url('/discount'), ['price' => $original + 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('price');

        $this->postJson($this->url('/discount'), ['price' => 2000])
            ->assertOk()
            ->assertJsonPath('product.price', fn ($v) => $v == 2000)
            ->assertJsonPath('product.old_price', fn ($v) => $v == $original);

        // İkinci indirim yine asıl fiyata göre hesaplanır
        $this->postJson($this->url('/discount'), ['price' => 1800])
            ->assertOk()
            ->assertJsonPath('product.old_price', fn ($v) => $v == $original);

        $this->deleteJson($this->url('/discount'))
            ->assertOk()
            ->assertJsonPath('product.price', fn ($v) => $v == $original)
            ->assertJsonPath('product.old_price', null);
    }

    public function test_images_can_be_uploaded_set_as_cover_and_deleted(): void
    {
        Storage::fake('public');
        $color = Product::find(self::OWN)->colors()->first();

        $images = $this->post($this->url('/images'), [
            // GD olmadan da çalışsın diye gerçek 1x1 PNG
            'image' => UploadedFile::fake()->createWithContent('dolap.png', base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
            )),
            'color_id' => $color->id,
        ], ['Accept' => 'application/json'])->assertOk()->json('product.images');

        $uploaded = collect($images)->last();
        $this->assertSame($color->id, $uploaded['color_id']);
        $this->assertStringStartsWith('/storage/urunler/'.self::OWN.'/', $uploaded['path']);
        Storage::disk('public')->assertExists(substr($uploaded['path'], strlen('/storage/')));

        $this->postJson($this->url("/images/{$uploaded['id']}/cover"))
            ->assertOk()
            ->assertJsonPath('product.image', $uploaded['path']);

        $this->deleteJson($this->url("/images/{$uploaded['id']}"))->assertOk();
        Storage::disk('public')->assertMissing(substr($uploaded['path'], strlen('/storage/')));
        // Kapak silinince sıradaki görsel kapak olur
        $this->assertNotSame($uploaded['path'], Product::find(self::OWN)->image);
    }

    public function test_last_image_cannot_be_deleted(): void
    {
        $product = Product::find(self::OWN);
        $product->images()->skip(1)->take(PHP_INT_MAX)->get()->each->delete();

        $this->deleteJson($this->url('/images/'.$product->images()->value('id')))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }

    public function test_product_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $user->cartItems()->create([
            'item_key' => self::OWN, 'product_id' => self::OWN, 'name' => 'x', 'price' => 1, 'quantity' => 1,
        ]);

        $this->deleteJson($this->url())->assertOk();

        $this->assertDatabaseMissing('products', ['id' => self::OWN]);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_seller_can_reply_to_reviews_repeatedly_and_delete_own_reply(): void
    {
        $review = Product::find(self::OWN)->reviews()->create([
            'author_name' => 'Ebru Demirci', 'rating' => 4, 'comment' => 'Güzel', 'reviewed_at' => '2026-10-20',
        ]);
        $customerReply = $review->replies()->create(['author_type' => 'customer', 'body' => 'Bir sorum var']);

        $this->postJson($this->url("/reviews/{$review->id}/replies"), ['body' => 'Teşekkürler!'])->assertOk();
        $replies = $this->postJson($this->url("/reviews/{$review->id}/replies"), ['body' => 'Başka bir sorunuz olursa yazın.'])
            ->assertOk()
            ->assertJsonPath('product.reviews.0.name', 'E*** D***')
            ->assertJsonCount(3, 'product.reviews.0.replies')
            ->json('product.reviews.0.replies');

        $this->deleteJson($this->url("/reviews/{$review->id}/replies/{$replies[1]['id']}"))
            ->assertOk()
            ->assertJsonCount(2, 'product.reviews.0.replies');

        // Alıcının mesajını satıcı silemez
        $this->deleteJson($this->url("/reviews/{$review->id}/replies/{$customerReply->id}"))->assertNotFound();
    }

    public function test_out_of_stock_products_cannot_be_bought(): void
    {
        Product::whereKey(self::OWN)->update(['stock' => 2]);
        Sanctum::actingAs(User::factory()->create());

        // Sepete stoktan fazlası eklenemez
        $this->postJson('/api/cart/items', ['product_id' => self::OWN, 'color' => 'Beyaz & Ceviz', 'quantity' => 5])
            ->assertOk()
            ->assertJsonPath('items.0.quantity', 2);

        Product::whereKey(self::OWN)->update(['stock' => 0]);
        $this->postJson('/api/cart/items', ['product_id' => self::OWN, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');
    }
}
