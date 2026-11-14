<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * İşin aracının son konumu (araç sahibi "İşlerin Durumu" haritası) ve teslim anı.
     */
    public function up(): void
    {
        Schema::table('shipments_order', function (Blueprint $table) {
            $table->decimal('last_lat', 10, 7)->nullable()->after('progress_updated_at');
            $table->decimal('last_lng', 10, 7)->nullable()->after('last_lat');
            $table->timestamp('delivered_at')->nullable()->after('last_lng');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments_order', function (Blueprint $table) {
            $table->dropColumn(['last_lat', 'last_lng', 'delivered_at']);
        });
    }
};
