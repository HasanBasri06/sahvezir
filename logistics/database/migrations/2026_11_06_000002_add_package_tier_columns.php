<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paket türü: individual (Bireysel), small / medium / large (İşletme) — kapsanan araç sayısı paketle gelir.
     */
    public function up(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->string('package_tier', 32)->nullable()->after('package_name');
            $table->unsignedSmallInteger('vehicle_limit')->nullable()->after('package_tier');
        });

        Schema::table('user_orders', function (Blueprint $table) {
            $table->string('package_tier', 32)->nullable()->after('package_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_orders', function (Blueprint $table) {
            $table->dropColumn('package_tier');
        });

        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['package_tier', 'vehicle_limit']);
        });
    }
};
