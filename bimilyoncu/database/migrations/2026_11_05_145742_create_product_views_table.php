<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kullanıcının önceden gezdiği ürünler (anasayfadaki "Önceden Gezdiklerin").
     * viewed_price: son bakıldığındaki fiyat; ürün şimdi daha ucuzsa "fiyatı düştü" sayılır.
     */
    public function up(): void
    {
        Schema::create('product_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('product_id', 20);
            $table->decimal('viewed_price', 10, 2);
            $table->timestamp('viewed_at');

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique(['user_id', 'product_id']);
            $table->index(['user_id', 'viewed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_views');
    }
};
