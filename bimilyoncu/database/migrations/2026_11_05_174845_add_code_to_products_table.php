<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ürün kodu: sistemin otomatik verdiği, harf ve rakam karışık, 10 karakterlik benzersiz kod (örn. K7M2Q9X4BD).
     * Mevcut ürünlere de kod verilir.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('code', 10)->nullable()->after('id');
        });

        DB::table('products')->whereNull('code')->pluck('id')->each(
            fn (string $id) => DB::table('products')->where('id', $id)->update(['code' => Product::newCode()])
        );

        Schema::table('products', function (Blueprint $table) {
            $table->unique('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
