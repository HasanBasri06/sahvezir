<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'with_admin')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->tinyInteger('with_admin')->default(0)->after('verified');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'with_admin')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('with_admin');
        });
    }
};
