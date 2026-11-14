<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_devices')) {
            return;
        }

        DB::statement("ALTER TABLE user_devices MODIFY expo_push_token VARCHAR(255) NULL");
        DB::statement("ALTER TABLE user_devices MODIFY platform ENUM('android', 'ios') NULL");
        DB::statement("ALTER TABLE user_devices MODIFY status ENUM('active', 'inactive', 'deny') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_devices')) {
            return;
        }

        DB::table('user_devices')
            ->where('status', 'deny')
            ->orWhereNull('expo_push_token')
            ->orWhereNull('platform')
            ->delete();

        DB::statement("ALTER TABLE user_devices MODIFY expo_push_token VARCHAR(255) NOT NULL");
        DB::statement("ALTER TABLE user_devices MODIFY platform ENUM('android', 'ios') NOT NULL");
        DB::statement("ALTER TABLE user_devices MODIFY status ENUM('active', 'inactive') NOT NULL DEFAULT 'active'");
    }
};
