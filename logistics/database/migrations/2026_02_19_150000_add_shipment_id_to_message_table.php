<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * shipment_id sütunu create_message migration'ında zaten var.
     * Bu migration sadece foreign key ekler.
     */
    public function up(): void
    {
        Schema::table('message', function (Blueprint $table) {
            if (!Schema::hasColumn('message', 'shipment_id')) {
                $table->integer('shipment_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('message', function (Blueprint $table) {
            $table->dropForeign(['shipment_id']);
        });
    }
};
