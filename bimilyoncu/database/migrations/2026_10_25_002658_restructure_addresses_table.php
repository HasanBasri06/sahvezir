<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * İl/ilçe serbest metin yerine veritabanındaki kayıtlara bağlanır; adres parçaları ayrı alanlara bölünür.
     */
    public function up(): void
    {
        // Eski serbest metin adresler yeni zorunlu alanlara dönüştürülemez (yalnızca geliştirme verisi)
        DB::table('addresses')->delete();

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['city', 'district', 'address_line']);
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->unsignedTinyInteger('city_id')->after('phone');
            $table->foreignId('district_id')->after('city_id')->constrained()->restrictOnDelete();
            $table->string('neighbourhood', 100)->after('district_id');
            $table->string('street', 150)->after('neighbourhood');
            $table->string('building_no', 10)->after('street');
            $table->string('floor', 10)->after('building_no');
            $table->string('apartment_no', 10)->after('floor');
            $table->char('postal_code', 5)->after('apartment_no');
            // İsteğe bağlı tarif, örn. "Eczanenin yanındaki bina"
            $table->string('directions', 255)->nullable()->after('postal_code');

            $table->foreign('city_id')->references('id')->on('cities')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('addresses')->delete();

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropForeign(['district_id']);
            $table->dropColumn([
                'city_id', 'district_id', 'neighbourhood', 'street', 'building_no',
                'floor', 'apartment_no', 'postal_code', 'directions',
            ]);
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->string('city', 50);
            $table->string('district', 50);
            $table->string('address_line', 500);
        });
    }
};
