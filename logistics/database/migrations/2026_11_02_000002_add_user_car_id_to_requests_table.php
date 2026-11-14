<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Teklif verilirken seçilen kullanıcı aracı (`user_cars.id`). `car_type` yalnızca araç türünü tutar;
     * teklif kabul edilince iş bu araca bağlanır (aktif işte "aracım" doğrudan bu araç olur).
     */
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->unsignedBigInteger('user_car_id')->nullable()->after('car_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn('user_car_id');
        });
    }
};
