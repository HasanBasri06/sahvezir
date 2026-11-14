<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satıcının girdiği stok; siparişte düşer, sepete stoktan fazlası eklenemez.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('stock')->default(0)->after('old_price');
        });

        // Mevcut ürünler stoksuz kalıp satıştan düşmesin
        DB::table('products')->update(['stock' => 20]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('stock');
        });
    }
};
