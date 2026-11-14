<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kâr hesabı: satıcının girdiği ürün maliyeti ve satış anındaki maliyet (sonradan değişse de satış etkilenmez).
     * Eski ürün ve satışlarda boş kalır.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost', 10, 2)->nullable()->after('old_price');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 10, 2)->nullable()->after('unit_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('cost');
        });
    }
};
