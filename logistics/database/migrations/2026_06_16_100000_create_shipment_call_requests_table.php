<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_call_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipment_id');
            $table->unsignedBigInteger('vehicle_owner_id');
            $table->unsignedBigInteger('cargo_owner_id');
            $table->string('caller_phone', 32)->nullable();
            $table->timestamp('called_at');
            $table->boolean('listing_taken');
            $table->timestamps();

            $table->foreign('shipment_id')->references('id')->on('shipments')->cascadeOnDelete();
            $table->foreign('vehicle_owner_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('cargo_owner_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['shipment_id', 'called_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_call_requests');
    }
};
