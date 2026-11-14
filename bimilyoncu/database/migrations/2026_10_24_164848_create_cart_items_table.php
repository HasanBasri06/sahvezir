<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Sepet satırının anahtarı: ürün + varyant (örn. "p19-Boyalı"); her varyant ayrı satırdır
            $table->string('item_key');
            $table->string('product_id')->nullable();
            // Ürünler henüz API'de olmadığı için eklendiği andaki bilgiler saklanır
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->string('image', 2048)->nullable();
            $table->string('variant')->nullable();
            $table->unsignedTinyInteger('quantity');
            $table->timestamps();

            $table->unique(['user_id', 'item_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
