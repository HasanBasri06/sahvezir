<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Araç sahibi alt üyelik türü: 'yes' = Çoklu Araç Yönetim modu (birden fazla araç), 'no' = tek araç.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('is_multi_cargo_owner', ['yes', 'no'])->default('no')->after('type');
        });

        // Halihazırda birden fazla aktif aracı olan araç sahipleri çoklu üyelikle devam eder
        DB::table('users')
            ->whereIn('id', DB::table('user_cars')->where('status', 'active')->groupBy('user_id')->havingRaw('COUNT(*) > 1')->pluck('user_id'))
            ->update(['is_multi_cargo_owner' => 'yes']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_multi_cargo_owner');
        });
    }
};
