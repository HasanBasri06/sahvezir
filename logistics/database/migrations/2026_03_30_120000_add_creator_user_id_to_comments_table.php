<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Değerlendirmeyi yazan kullanıcı (`users.id`) — `Comment::creator()` ile uyumlu.
     */
    public function up(): void
    {
        if (Schema::hasTable('comments') && ! Schema::hasColumn('comments', 'creator_user_id')) {
            Schema::table('comments', function (Blueprint $table) {
                $table->unsignedBigInteger('creator_user_id')->nullable()->after('user_id');
            });
            Schema::table('comments', function (Blueprint $table) {
                $table->foreign('creator_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('comments') && Schema::hasColumn('comments', 'creator_user_id')) {
            Schema::table('comments', function (Blueprint $table) {
                $table->dropForeign(['creator_user_id']);
            });
            Schema::table('comments', function (Blueprint $table) {
                $table->dropColumn('creator_user_id');
            });
        }
    }
};
