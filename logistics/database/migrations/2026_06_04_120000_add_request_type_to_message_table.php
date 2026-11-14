<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('message', 'type')) {
            return;
        }

        DB::statement("ALTER TABLE `message` MODIFY `type` ENUM('normal', 'system', 'request') NOT NULL DEFAULT 'normal'");
    }

    public function down(): void
    {
        if (! Schema::hasColumn('message', 'type')) {
            return;
        }

        DB::statement("ALTER TABLE `message` MODIFY `type` ENUM('normal', 'system') NOT NULL DEFAULT 'normal'");
    }
};
