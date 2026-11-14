<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satıcının hangi ana kategoriler için ürün geliştirdiği (bir satıcı birden fazla kategoride olabilir).
     * Kategoriler önyüzde tanımlı olduğu için kategori slug'ı ile tutulur, örn. "ev-bahce".
     */
    public function up(): void
    {
        Schema::create('seller_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->string('category_slug', 100);
            $table->timestamps();

            $table->unique(['seller_id', 'category_slug']);
            $table->index('category_slug');
        });

        // Mevcut satıcılar ürünlerinin bulunduğu kategorilere bağlanır
        $now = now();
        DB::table('seller_categories')->insertUsing(
            ['seller_id', 'category_slug', 'created_at', 'updated_at'],
            DB::table('products')->select('seller_id', 'category_slug')
                ->selectRaw('? as created_at, ? as updated_at', [$now, $now])
                ->distinct()
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_categories');
    }
};
