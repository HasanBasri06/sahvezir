<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * İlan oluştururken haritadan seçilen detaylı konum (koordinat + adres).
 * f_where_city/district ve t_where_city/district filtre için kalır; bunlar ek bilgi.
 * Yapı: { lat, lng, display_name, address: { province, district, neighbourhood, road, house_number, postcode } }
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            if (! Schema::hasColumn('shipments', 'f_where_location')) {
                $table->json('f_where_location')->nullable()->after('f_where_district');
            }
            if (! Schema::hasColumn('shipments', 't_where_location')) {
                $table->json('t_where_location')->nullable()->after('t_where_district');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            foreach (['f_where_location', 't_where_location'] as $column) {
                if (Schema::hasColumn('shipments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
