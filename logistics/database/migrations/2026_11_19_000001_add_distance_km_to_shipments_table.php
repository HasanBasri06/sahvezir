<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * İlanın yol mesafesi (km): ilan yüklenirken web / mobil rota özetinde hesaplanıp gönderilir (routeInfo.distance).
 * Araç sahibinin "toplam gidilen km" istatistiği tamamlanan işlerin bu değerlerinden toplanır.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shipments', 'distance_km')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->decimal('distance_km', 8, 1)->nullable()->after('hours');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shipments', 'distance_km')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->dropColumn('distance_km');
            });
        }
    }
};
