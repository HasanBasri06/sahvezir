<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users_detail', function (Blueprint $table) {
            if (! Schema::hasColumn('users_detail', 'exact_location')) {
                $table->json('exact_location')->nullable()->after('user_district');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users_detail', function (Blueprint $table) {
            if (Schema::hasColumn('users_detail', 'exact_location')) {
                $table->dropColumn('exact_location');
            }
        });
    }
};
