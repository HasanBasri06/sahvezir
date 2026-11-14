<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Araç sahibinin işi "İşi Başlat" ile başlattığı an (yük sahibine "yola çıktı" bildirimi gider).
     */
    public function up(): void
    {
        Schema::table('shipments_order', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('user_car_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments_order', function (Blueprint $table) {
            $table->dropColumn('started_at');
        });
    }
};
