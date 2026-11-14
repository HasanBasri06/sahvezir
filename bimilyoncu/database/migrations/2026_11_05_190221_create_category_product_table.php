<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ürün birden fazla alt kategoride olabilir (satıcı kataloğundan çoklu seçim).
     * products.category_id ana kategori olarak kalır (ürün sayfasındaki yol); tüm kategoriler bu tabloda.
     */
    public function up(): void
    {
        Schema::create('category_product', function (Blueprint $table) {
            $table->string('product_id', 20);
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->primary(['product_id', 'category_id']);
        });

        DB::table('category_product')->insertUsing(
            ['product_id', 'category_id'],
            DB::table('products')->select('id', 'category_id')->whereNotNull('category_id')
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('category_product');
    }
};
