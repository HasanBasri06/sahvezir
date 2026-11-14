<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ürün kartlarında satıcı adının yazıldığı marka rengi.
     */
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->string('brand_color', 7)->nullable()->after('logo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn('brand_color');
        });
    }
};
