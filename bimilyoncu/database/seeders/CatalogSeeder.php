<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Başlangıç kataloğu: satıcılar, ürünler, renkler, görseller ve özellikler.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        // Tekrar çalıştırılabilir: katalog zaten yüklüyse dokunma
        if (Product::query()->exists()) {
            return;
        }

        $catalog = json_decode(file_get_contents(database_path('data/catalog.json')), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($catalog) {
            $sellerIds = [];
            foreach ($catalog['sellers'] as $seller) {
                $sellerIds[$seller['slug']] = Seller::create($seller)->id;
            }

            foreach ($catalog['products'] as $position => $data) {
                $product = Product::create([
                    ...collect($data)->only([
                        'id', 'slug', 'name', 'price', 'old_price', 'image',
                        'description', 'featured', 'ai_images',
                    ])->all(),
                    'seller_id' => $sellerIds[$data['seller']],
                    'category_id' => Category::idForPath([$data['category_slug'], $data['group_slug'], $data['item_slug']]),
                    'sort' => $position,
                    'stock' => $data['stock'] ?? 20,
                ]);
                // Ürünün alt kategorisi hem ürün–kategori hem satıcı kataloğu tablosuna eklenir
                $product->categories()->attach($product->category_id);
                $product->seller->addCategory($product->category_id);

                $sort = 0;
                foreach ($data['images'] as $path) {
                    $product->images()->create(['path' => $path, 'sort' => $sort++]);
                }

                foreach ($data['colors'] as $index => $color) {
                    $productColor = $product->colors()->create([
                        'name' => $color['name'],
                        'hex' => $color['hex'],
                        'sort' => $index,
                    ]);
                    foreach ($color['images'] as $path) {
                        $product->images()->create([
                            'product_color_id' => $productColor->id,
                            'path' => $path,
                            'sort' => $sort++,
                        ]);
                    }
                }

                foreach ($data['specs'] as $index => $spec) {
                    $product->specs()->create(['label' => $spec['label'], 'value' => $spec['value'], 'sort' => $index]);
                }
            }
        });
    }
}
