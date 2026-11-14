<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aktif işin canlı ilerlemesi: araç sahibinin konumu rota illerine izdüşürülür (0–100).
     */
    public function up(): void
    {
        Schema::table('shipments_order', function (Blueprint $table) {
            $table->decimal('progress', 5, 1)->nullable()->after('started_at');
            $table->string('current_city')->nullable()->after('progress');
            $table->timestamp('progress_updated_at')->nullable()->after('current_city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments_order', function (Blueprint $table) {
            $table->dropColumn(['progress', 'current_city', 'progress_updated_at']);
        });
    }
};
