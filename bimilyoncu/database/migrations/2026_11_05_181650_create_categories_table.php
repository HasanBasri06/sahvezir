<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kategoriler veritabanına taşınır: parent_id ile ağaç (ana kategori → başlık → alt kategori).
     * Ürünler ve satıcı–kategori bağı slug yerine category_id ile tutulur; mevcut kayıtlar aktarılır.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('name');
            $table->string('slug', 100);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['parent_id', 'slug']);
        });
        Category::seedFromFile();

        // Ürünler: en alttaki kategoriye bağlanır
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('seller_id')->constrained()->restrictOnDelete();
        });
        DB::table('products')->get(['id', 'category_slug', 'group_slug', 'item_slug'])->each(
            fn ($product) => DB::table('products')->where('id', $product->id)->update([
                'category_id' => Category::idForPath([$product->category_slug, $product->group_slug, $product->item_slug]),
            ])
        );
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['category_slug', 'group_slug', 'item_slug']);
            $table->dropColumn(['category_slug', 'group_slug', 'item_slug']);
        });

        // Satıcı–kategori bağı: ana kategoriye bağlanır
        Schema::table('seller_categories', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('seller_id')->constrained()->cascadeOnDelete();
        });
        DB::table('seller_categories')->get(['id', 'category_slug'])->each(
            fn ($link) => DB::table('seller_categories')->where('id', $link->id)->update([
                'category_id' => Category::idForPath([$link->category_slug]),
            ])
        );
        DB::table('seller_categories')->whereNull('category_id')->delete();
        // MySQL eski benzersiz indeksi seller_id yabancı anahtarı için kullanır; önce yenisi eklenir
        Schema::table('seller_categories', function (Blueprint $table) {
            $table->unique(['seller_id', 'category_id']);
        });
        Schema::table('seller_categories', function (Blueprint $table) {
            $table->dropUnique(['seller_id', 'category_slug']);
            $table->dropIndex(['category_slug']);
            $table->dropColumn('category_slug');
        });
    }

    /**
     * Geri dönüş desteklenmez: slug kolonları kaldırıldı, veri category_id'de.
     */
    public function down(): void
    {
        throw new RuntimeException('Bu migration geri alınamaz.');
    }
};
