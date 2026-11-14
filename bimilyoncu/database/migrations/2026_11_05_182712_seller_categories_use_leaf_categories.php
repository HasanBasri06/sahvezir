<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Satıcı kataloğu artık alt kategori düzeyinde: satıcı Ürün Kataloğum'dan alt kategorileri seçer.
     * Eski ana kategori bağları, satıcının ürünü olan alt kategorilerle değiştirilir.
     */
    public function up(): void
    {
        $now = now();
        foreach (DB::table('seller_categories')->get() as $link) {
            if (! Category::find($link->category_id)?->isLeaf()) {
                DB::table('seller_categories')->where('id', $link->id)->delete();
            }
        }

        DB::table('products')->select('seller_id', 'category_id')->whereNotNull('category_id')->distinct()->get()
            ->each(fn ($row) => DB::table('seller_categories')->insertOrIgnore([
                'seller_id' => $row->seller_id,
                'category_id' => $row->category_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
    }

    public function down(): void
    {
        // Veri dönüşümü; geri alınacak yapı değişikliği yok
    }
};
