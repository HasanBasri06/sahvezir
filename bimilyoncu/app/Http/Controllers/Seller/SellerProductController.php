<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Models\ProductColor;
use App\Models\ProductImage;
use App\Models\Review;
use App\Models\ReviewReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Satıcının kendi ürününü yönetmesi: stok, ad, indirim, görseller, silme ve yorum yanıtları.
 * Her işlem ürünün oturumdaki satıcıya ait olduğunu doğrular.
 */
class SellerProductController extends Controller
{
    /** Yüklenen görseller public disk'te bu klasöre, önyüze /storage/... olarak gider */
    private const IMAGE_DIR = 'urunler';

    /**
     * Yeni ürün: ad (slug'ı otomatik), açıklama, fiyat, stok, kategori, özellikler, renkler ve görseller.
     * Görseller tek tek bir renge ya da tüm renklere bağlanabilir; ilk görsel kapak olur.
     */
    /**
     * Yeni ürün ve düzenleme formunun ortak doğrulaması. Düzenlemede renkler ve görseller id ile
     * mevcut kayıtlara işaret edebilir; id'siz olanlar yenidir.
     *
     * @return array<string, mixed>
     */
    private function validateForm(Request $request, ?Product $product = null): array
    {
        $editing = $product !== null;

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:1', 'max:99999999'],
            // Kâr hesabı: satış fiyatı ile maliyet arasındaki fark
            'cost' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'stock' => ['required', 'integer', 'min:0', 'max:100000'],
            // Ürün, satıcının kataloğundaki bir ya da birden fazla alt kategoriye bağlanır; ilki ana kategori
            'category_ids' => ['required', 'array', 'min:1', 'max:20'],
            'category_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('seller_categories', 'category_id')->where('seller_id', $request->user()->seller_id),
            ],
            'specs' => ['nullable', 'array', 'max:30'],
            'specs.*.label' => ['required', 'string', 'max:100'],
            'specs.*.value' => ['required', 'string', 'max:255'],
            'colors' => ['nullable', 'array', 'max:20'],
            'colors.*.id' => $editing
                ? ['nullable', 'integer', Rule::exists('product_colors', 'id')->where('product_id', $product->id)]
                : ['prohibited'],
            'colors.*.name' => ['required', 'string', 'max:50', 'distinct'],
            'colors.*.hex' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'images' => ['required', 'array', 'min:1', 'max:20'],
            // Düzenlemede var olan görsel id ile kalır; yeni görsel dosyayla gelir
            'images.*.id' => $editing
                ? ['nullable', 'integer', Rule::exists('product_images', 'id')->where('product_id', $product->id)]
                : ['prohibited'],
            'images.*.file' => [$editing ? 'required_without:images.*.id' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            // Renk listesindeki sırası; boşsa görsel tüm renkler için
            'images.*.color' => ['nullable', 'integer', 'min:0'],
        ], [
            'name.required' => 'Ürün adı zorunludur.',
            'name.min' => 'Ürün adı en az 3 karakter olmalıdır.',
            'price.required' => 'Fiyat zorunludur.',
            'cost.required' => 'Maliyet zorunludur.',
            'cost.numeric' => 'Maliyet sayı olmalıdır.',
            'cost.min' => 'Maliyet 0 ya da daha büyük olmalıdır.',
            'stock.required' => 'Stok zorunludur.',
            'category_ids.required' => 'Kataloğundan en az bir alt kategori seç.',
            'category_ids.min' => 'Kataloğundan en az bir alt kategori seç.',
            'category_ids.*.exists' => 'Yalnızca kataloğundaki alt kategoriler seçilebilir.',
            'colors.*.name.distinct' => 'Aynı isimde iki renk olamaz.',
            'colors.*.hex.regex' => 'Renk kodu #RRGGBB biçiminde olmalıdır.',
            'images.required' => 'En az bir görsel ekleyin.',
            'images.*.file.required' => 'Görsel dosyası eksik.',
            'images.*.file.required_without' => 'Görsel dosyası eksik.',
            'images.*.file.image' => 'Yalnızca görsel dosyası yükleyebilirsin.',
            'images.*.file.mimes' => 'JPG, PNG ya da WEBP yükleyebilirsin.',
            'images.*.file.max' => 'Her görsel en fazla 5 MB olabilir.',
        ]);

        $colors = $data['colors'] ?? [];
        foreach ($data['images'] as $index => $image) {
            if (isset($image['color']) && ! array_key_exists($image['color'], $colors)) {
                throw ValidationException::withMessages(["images.{$index}.color" => 'Görselin rengi listede yok.']);
            }
        }

        return $data;
    }

    /** Boş satırla ayrılan paragraflar */
    private function paragraphs(?string $description): array
    {
        return collect(preg_split('/\R\s*\R/u', trim($description ?? '')))
            ->map(fn (string $paragraph) => trim(preg_replace('/\s+/u', ' ', $paragraph)))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Ürünün tüm bilgilerini düzenleme formundan günceller: ad, açıklama, fiyat, stok, kategori,
     * özellikler, renkler ve görseller (sıra ve renk eşleşmesi dahil; ilk görsel kapak).
     * Bağlantı (slug) değişmez; çıkarılan renk ve görseller silinir.
     */
    public function replace(Request $request, string $id): JsonResponse
    {
        $product = $this->ownProduct($request, $id);
        $data = $this->validateForm($request, $product);
        $stored = [];
        $removedPaths = [];

        try {
            DB::transaction(function () use ($product, $data, &$stored, &$removedPaths) {
                // İndirim varsa yeni fiyat asıl fiyattır; indirimli fiyat ondan düşük kalmıyorsa indirim kalkar
                $price = round((float) $data['price'], 2);
                $prices = $product->old_price === null
                    ? ['price' => $price]
                    : ($product->price < $price ? ['old_price' => $price] : ['price' => $price, 'old_price' => null]);

                $product->update([
                    ...$prices,
                    'name' => $data['name'],
                    'description' => $this->paragraphs($data['description'] ?? null),
                    'cost' => round((float) $data['cost'], 2),
                    'stock' => $data['stock'],
                    'category_id' => $data['category_ids'][0],
                ]);
                $product->categories()->sync($data['category_ids']);

                $product->specs()->delete();
                foreach ($data['specs'] ?? [] as $index => $spec) {
                    $product->specs()->create(['label' => $spec['label'], 'value' => $spec['value'], 'sort' => $index]);
                }

                // Renkler: id'li olanlar güncellenir, yeniler eklenir, listede olmayanlar silinir
                $colorIds = [];
                foreach ($data['colors'] ?? [] as $index => $color) {
                    $attributes = ['name' => $color['name'], 'hex' => strtolower($color['hex']), 'sort' => $index];
                    $colorIds[$index] = isset($color['id'])
                        ? tap($product->colors()->findOrFail($color['id']))->update($attributes)->id
                        : $product->colors()->create($attributes)->id;
                }

                // Görseller: listede kalanlar yeni sıra ve renkleriyle, yeniler yüklenir, çıkarılanlar silinir
                $keptImageIds = [];
                foreach ($data['images'] as $index => $image) {
                    $colorId = isset($image['color']) ? $colorIds[$image['color']] : null;
                    if (isset($image['id'])) {
                        $product->images()->whereKey($image['id'])->update(['product_color_id' => $colorId, 'sort' => $index]);
                        $keptImageIds[] = (int) $image['id'];
                    } else {
                        $path = $image['file']->store(self::IMAGE_DIR.'/'.$product->id, 'public');
                        $stored[] = $path;
                        $keptImageIds[] = $product->images()->create([
                            'product_color_id' => $colorId,
                            'path' => '/storage/'.$path,
                            'sort' => $index,
                        ])->id;
                    }
                }

                $removed = $product->images()->whereNotIn('id', $keptImageIds);
                $removedPaths = $removed->pluck('path')->all();
                $removed->delete();
                $product->colors()->whereNotIn('id', array_values($colorIds))->delete();

                $product->update(['image' => $product->images()->value('path')]);
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($stored);
            throw $e;
        }

        foreach ($removedPaths as $path) {
            $this->deleteUploadedFile($path);
        }

        return $this->detail($product);
    }

    public function store(Request $request): JsonResponse
    {
        // Onay bekleyen mağaza ürün ekleyebilir (onaylanınca yayına girer); reddedilen mağaza ekleyemez
        abort_if(
            $request->user()->seller->status === Seller::STATUS_REJECTED,
            403,
            'Mağaza başvurun onaylanmadığı için ürün ekleyemezsin.'
        );

        $data = $this->validateForm($request);
        $colors = $data['colors'] ?? [];

        $seller = $request->user()->seller;
        $stored = [];

        try {
            $product = DB::transaction(function () use ($data, $colors, $seller, &$stored) {
                $id = $this->newProductId();

                $product = $seller->products()->create([
                    'id' => $id,
                    'slug' => $this->uniqueSlug($data['name']),
                    'name' => $data['name'],
                    'description' => $this->paragraphs($data['description'] ?? null),
                    'price' => round((float) $data['price'], 2),
                    'cost' => round((float) $data['cost'], 2),
                    'stock' => $data['stock'],
                    'category_id' => $data['category_ids'][0],
                    // Kapak aşağıda ilk görselle doldurulur
                    'image' => '',
                    'sort' => (int) Product::max('sort') + 1,
                ]);
                $product->categories()->sync($data['category_ids']);

                foreach ($data['specs'] ?? [] as $index => $spec) {
                    $product->specs()->create(['label' => $spec['label'], 'value' => $spec['value'], 'sort' => $index]);
                }

                $colorIds = [];
                foreach ($colors as $index => $color) {
                    $colorIds[$index] = $product->colors()->create([
                        'name' => $color['name'],
                        'hex' => strtolower($color['hex']),
                        'sort' => $index,
                    ])->id;
                }

                foreach ($data['images'] as $index => $image) {
                    $path = $image['file']->store(self::IMAGE_DIR.'/'.$id, 'public');
                    $stored[] = $path;
                    $product->images()->create([
                        'product_color_id' => isset($image['color']) ? $colorIds[$image['color']] : null,
                        'path' => '/storage/'.$path,
                        'sort' => $index,
                    ]);
                }

                $product->update(['image' => $product->images()->value('path')]);

                return $product;
            });
        } catch (\Throwable $e) {
            // Kayıt geri alındıysa yüklenen dosyalar da kalmasın
            Storage::disk('public')->delete($stored);
            throw $e;
        }

        return response()->json(['product' => ['id' => $product->id, 'slug' => $product->slug]], 201);
    }

    /** "p23" → "p24"; mevcut kodlarla çakışmaz */
    private function newProductId(): string
    {
        $max = Product::pluck('id')
            ->map(fn (string $id) => (int) preg_replace('/\D/', '', $id))
            ->max() ?? 0;

        return 'p'.($max + 1);
    }

    /** Ad'dan slug; aynısı varsa sonuna -2, -3... eklenir */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'urun';
        $slug = $base;
        for ($i = 2; Product::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return $this->detail($this->ownProduct($request, $id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $product = $this->ownProduct($request, $id);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'min:3', 'max:255'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0', 'max:100000'],
        ], [
            'name.required' => 'Ürün adı boş olamaz.',
            'name.min' => 'Ürün adı en az 3 karakter olmalıdır.',
            'stock.min' => 'Stok negatif olamaz.',
            'stock.integer' => 'Stok tam sayı olmalıdır.',
        ]);

        // Ad değişse de ürünün bağlantısı (slug) aynı kalır; paylaşılmış linkler bozulmasın
        $product->update($data);

        return $this->detail($product);
    }

    public function discount(Request $request, string $id): JsonResponse
    {
        $product = $this->ownProduct($request, $id);
        $original = $product->old_price ?? $product->price;

        $data = $request->validate([
            'price' => ['required', 'numeric', 'min:1', 'lt:'.$original],
        ], [
            'price.required' => 'İndirimli fiyatı girin.',
            'price.lt' => 'İndirimli fiyat, ürünün asıl fiyatından düşük olmalıdır.',
        ]);

        $product->update(['old_price' => $original, 'price' => round((float) $data['price'], 2)]);

        return $this->detail($product);
    }

    public function removeDiscount(Request $request, string $id): JsonResponse
    {
        $product = $this->ownProduct($request, $id);

        if ($product->old_price !== null) {
            $product->update(['price' => $product->old_price, 'old_price' => null]);
        }

        return $this->detail($product);
    }

    public function storeImage(Request $request, string $id): JsonResponse
    {
        $product = $this->ownProduct($request, $id);

        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'color_id' => [
                'nullable', 'integer',
                Rule::exists('product_colors', 'id')->where('product_id', $product->id),
            ],
        ], [
            'image.required' => 'Bir görsel seçin.',
            'image.image' => 'Yalnızca görsel dosyası yükleyebilirsin.',
            'image.mimes' => 'JPG, PNG ya da WEBP yükleyebilirsin.',
            'image.max' => 'Görsel en fazla 5 MB olabilir.',
        ]);

        $stored = $request->file('image')->store(self::IMAGE_DIR.'/'.$product->id, 'public');

        $product->images()->create([
            'product_color_id' => $data['color_id'] ?? null,
            'path' => '/storage/'.$stored,
            'sort' => (int) $product->images()->max('sort') + 1,
        ]);

        return $this->detail($product);
    }

    public function destroyImage(Request $request, string $id, int $imageId): JsonResponse
    {
        $product = $this->ownProduct($request, $id);
        $image = $product->images()->findOrFail($imageId);

        if ($product->images()->count() <= 1) {
            throw ValidationException::withMessages(['image' => 'Üründe en az bir görsel kalmalı.']);
        }

        DB::transaction(function () use ($product, $image) {
            $image->delete();

            // Kapak görseli silindiyse sıradaki görsel kapak olur
            if ($product->image === $image->path) {
                $product->update(['image' => $product->images()->value('path')]);
            }
        });

        $this->deleteUploadedFile($image->path);

        return $this->detail($product);
    }

    public function coverImage(Request $request, string $id, int $imageId): JsonResponse
    {
        $product = $this->ownProduct($request, $id);
        $product->update(['image' => $product->images()->findOrFail($imageId)->path]);

        return $this->detail($product);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $product = $this->ownProduct($request, $id);
        $uploads = $product->images()->pluck('path');

        DB::transaction(function () use ($product) {
            // Sepetlerde kalan satırlar da gider; geçmiş siparişler ürün bilgisiyle saklı kalır
            CartItem::where('product_id', $product->id)->delete();
            $product->delete();
        });

        $uploads->each(fn (string $path) => $this->deleteUploadedFile($path));

        return response()->json(['message' => 'Ürün silindi.']);
    }

    public function reply(Request $request, string $id, int $reviewId): JsonResponse
    {
        $product = $this->ownProduct($request, $id);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:1000'],
        ], [
            'body.required' => 'Yanıt boş olamaz.',
        ]);

        $product->reviews()->findOrFail($reviewId)->replies()->create([
            'seller_user_id' => $request->user()->id,
            'author_type' => ReviewReply::AUTHOR_SELLER,
            'body' => $data['body'],
        ]);

        return $this->detail($product);
    }

    /** Satıcı yalnızca mağazasının yazdığı mesajı silebilir */
    public function destroyReply(Request $request, string $id, int $reviewId, int $replyId): JsonResponse
    {
        $product = $this->ownProduct($request, $id);

        $product->reviews()->findOrFail($reviewId)->replies()
            ->where('author_type', ReviewReply::AUTHOR_SELLER)
            ->findOrFail($replyId)
            ->delete();

        return $this->detail($product);
    }

    /** Başka satıcının ürünü 404 döner; varlığı bile belli olmasın */
    private function ownProduct(Request $request, string $id): Product
    {
        return $request->user()->seller->products()->findOrFail($id);
    }

    /** Yalnızca panelden yüklenen dosyalar silinir; önyüzün kendi görsellerine dokunulmaz */
    private function deleteUploadedFile(string $path): void
    {
        if (str_starts_with($path, '/storage/'.self::IMAGE_DIR.'/')) {
            Storage::disk('public')->delete(substr($path, strlen('/storage/')));
        }
    }

    private function detail(Product $product): JsonResponse
    {
        $product->refresh()->load(['seller', 'colors', 'images', 'specs', 'reviews.images', 'reviews.replies']);

        return response()->json(['product' => [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'price' => $product->price,
            'old_price' => $product->old_price,
            'code' => $product->code,
            'cost' => $product->cost,
            'stock' => $product->stock,
            'image' => $product->image,
            // Düzenleme formunu doldurmak için
            'description' => $product->description ?? [],
            'path' => $product->categoryPath(),
            'category_id' => $product->category_id,
            'category_ids' => $product->categories()->pluck('categories.id'),
            'specs' => $product->specs->map(fn ($spec) => ['label' => $spec->label, 'value' => $spec->value]),
            'colors' => $product->colors->map(fn (ProductColor $color) => [
                'id' => $color->id,
                'name' => $color->name,
                'hex' => $color->hex,
            ]),
            'images' => $product->images->map(fn (ProductImage $image) => [
                'id' => $image->id,
                'path' => $image->path,
                'color_id' => $image->product_color_id,
                'is_cover' => $image->path === $product->image,
            ]),
            'reviews' => $product->reviews->map(fn (Review $review) => $review->toPublicArray($product->seller)),
        ]]);
    }
}
