<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'suitable_postings')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('suitable_postings', ['yes', 'no'])->default('no')->after('type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'suitable_postings')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('suitable_postings');
        });
    }
};
