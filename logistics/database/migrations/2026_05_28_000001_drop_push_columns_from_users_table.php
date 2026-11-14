<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'platform')) {
                $table->dropColumn('platform');
            }
            if (Schema::hasColumn('users', 'expo_push_token')) {
                $table->dropColumn('expo_push_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'platform')) {
                $table->enum('platform', ['android', 'ios'])->nullable();
            }
            if (! Schema::hasColumn('users', 'expo_push_token')) {
                $table->string('expo_push_token')->nullable();
            }
        });
    }
};
