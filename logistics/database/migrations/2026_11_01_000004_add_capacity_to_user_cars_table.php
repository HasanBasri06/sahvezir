<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kullanıcının aracının taşıyabileceği yük: ağırlık (kg) ve hacim (m³).
     * Aracın yüke uygunluğu ve parça yük eşleştirmesi için. Eski araçlarda boş kalabilir.
     */
    public function up(): void
    {
        Schema::table('user_cars', function (Blueprint $table) {
            $table->unsignedInteger('capacity_kg')->nullable()->after('plate');
            $table->decimal('volume_m3', 6, 2)->nullable()->after('capacity_kg');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_cars', function (Blueprint $table) {
            $table->dropColumn(['capacity_kg', 'volume_m3']);
        });
    }
};
