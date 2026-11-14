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
        Schema::create('shipments_order', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('creater_user');
            $table->unsignedBigInteger('buyer_user');
            $table->unsignedBigInteger('shipment_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments_order');
    }
};
