<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Türkiye il, ilçe ve ilçelerin posta kodları. Veriyi LocationSeeder yükler.
     */
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            // Plaka kodu (1-81)
            $table->unsignedTinyInteger('id')->primary();
            $table->string('name', 50);
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('city_id');
            $table->string('name', 50);

            $table->foreign('city_id')->references('id')->on('cities')->cascadeOnDelete();
            $table->unique(['city_id', 'name']);
        });

        Schema::create('district_postal_codes', function (Blueprint $table) {
            $table->foreignId('district_id')->constrained()->cascadeOnDelete();
            $table->char('code', 5);

            $table->primary(['district_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('district_postal_codes');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('cities');
    }
};
