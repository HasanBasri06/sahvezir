<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Araç sahibinin bu işi hangi aracıyla taşıdığı (aktif iş ekranında "Seç").
     */
    public function up(): void
    {
        Schema::table('shipments_order', function (Blueprint $table) {
            $table->unsignedBigInteger('user_car_id')->nullable()->after('buyer_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments_order', function (Blueprint $table) {
            $table->dropColumn('user_car_id');
        });
    }
};
