<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('card_holder_name', 255);
            $table->string('brand', 255)->nullable();
            $table->string('last_four', 255);
            $table->string('exp_month', 255);
            $table->string('exp_year', 255);
        
            $table->string('provider', 255);
            $table->string('provider_card_id', 255);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
