<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Siparişler. Adres ve ürün bilgileri sipariş anındaki halleriyle saklanır;
     * sonradan adres ya da ürün değişse de sipariş değişmez.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Kullanıcıya gösterilen sipariş numarası, örn. "BM-20261025-4F7K2Q"
            $table->string('number', 30)->unique();
            $table->string('status', 20);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('vat', 10, 2);
            $table->decimal('shipping', 10, 2);
            $table->decimal('total', 10, 2);
            $table->json('shipping_address');
            // Kart bilgisi saklanmaz; yalnızca kartı tanımak için marka ve son 4 hane
            $table->string('card_brand', 20)->nullable();
            $table->char('card_last4', 4)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('product_id', 20)->nullable();
            $table->string('name');
            $table->string('variant')->nullable();
            $table->string('image', 2048)->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->unsignedTinyInteger('quantity');
            $table->decimal('line_total', 10, 2);

            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
