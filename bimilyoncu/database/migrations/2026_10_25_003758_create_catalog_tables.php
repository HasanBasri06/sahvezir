<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satıcılar, ürünler ve ürünlere bağlı renk, görsel, özellik ve yorumlar.
     * Puan ve değerlendirme sayısı saklanmaz, yorumlardan hesaplanır.
     */
    public function up(): void
    {
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('logo')->nullable();
            // Siparişin kaç iş günü içinde kargoya verildiği
            $table->unsignedTinyInteger('shipping_days')->default(2);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            // Sepet satırları ürünü bu kodla tutar (örn. "p19")
            $table->string('id', 20)->primary();
            $table->string('slug')->unique();
            $table->foreignId('seller_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->decimal('old_price', 10, 2)->nullable();
            // Liste ve kartlarda gösterilen ana görsel
            $table->string('image');
            // Kategori yolu: kategori / başlık / alt kategori slug'ları
            $table->string('category_slug', 100);
            $table->string('group_slug', 100);
            $table->string('item_slug', 100);
            $table->json('description')->nullable();
            // Anasayfadaki Popüler Ürünler'de en başta gösterilir
            $table->boolean('featured')->default(false);
            // Görseller yapay zeka ile üretildiyse bilgi notu gösterilir
            $table->boolean('ai_images')->default(false);
            // Kategori sayfasındaki "Önerilen" sıralaması
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['category_slug', 'group_slug', 'item_slug']);
        });

        Schema::create('product_colors', function (Blueprint $table) {
            $table->id();
            $table->string('product_id', 20);
            $table->string('name', 50);
            $table->string('hex', 7);
            $table->unsignedSmallInteger('sort')->default(0);

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique(['product_id', 'name']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->string('product_id', 20);
            // Doluysa görsel yalnızca o renk seçiliyken gösterilir
            $table->foreignId('product_color_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('sort')->default(0);

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::create('product_specs', function (Blueprint $table) {
            $table->id();
            $table->string('product_id', 20);
            $table->string('label', 100);
            $table->string('value');
            $table->unsignedSmallInteger('sort')->default(0);

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->string('product_id', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name');
            $table->unsignedTinyInteger('rating');
            $table->text('comment');
            $table->date('reviewed_at');
            // Ürünü satan firmanın yanıtı
            $table->text('seller_reply')->nullable();
            $table->date('replied_at')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->index(['product_id', 'reviewed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('product_specs');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_colors');
        Schema::dropIfExists('products');
        Schema::dropIfExists('sellers');
    }
};
