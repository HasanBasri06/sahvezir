<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * İşi başkasına verme: araç sahibinin oluşturduğu sürücü hesabı (users.type = driver) ve işin sürücüsü.
     * Sürücü yalnızca kendisine verilen aktif işi görür ve teslim eder.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY type ENUM('vehicle_owner','cargo_owner','driver') NULL");

        Schema::table('users', function (Blueprint $table) {
            // Sürücüyü oluşturan araç sahibi
            $table->unsignedBigInteger('parent_user_id')->nullable()->after('type')->index();
        });

        Schema::table('shipments_order', function (Blueprint $table) {
            // İşi teslim edecek sürücü (araç sahibi işi başkasına verdiyse)
            $table->unsignedBigInteger('driver_user_id')->nullable()->after('buyer_user')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments_order', function (Blueprint $table) {
            $table->dropColumn('driver_user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('parent_user_id');
        });

        DB::statement("UPDATE users SET type = NULL WHERE type = 'driver'");
        DB::statement("ALTER TABLE users MODIFY type ENUM('vehicle_owner','cargo_owner') NULL");
    }
};
