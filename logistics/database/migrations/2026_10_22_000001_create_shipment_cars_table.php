<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bir ilanın taşınabileceği araç türleri (çoklu araç seçimi).
     * `shipments.car_id` ilk seçilen aracı tutmaya devam eder.
     */
    public function up(): void
    {
        Schema::create('shipment_cars', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipment_id');
            $table->unsignedBigInteger('car_id');
            $table->unsignedBigInteger('car_detail_id')->nullable();
            $table->timestamps();

            $table->unique(['shipment_id', 'car_id']);
            $table->index('car_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_cars');
    }
};
