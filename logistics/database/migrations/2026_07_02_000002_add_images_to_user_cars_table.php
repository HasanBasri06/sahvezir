<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_cars', function (Blueprint $table) {
            if (! Schema::hasColumn('user_cars', 'image_one')) {
                $table->string('image_one')->nullable()->after('plate');
            }
            if (! Schema::hasColumn('user_cars', 'image_two')) {
                $table->string('image_two')->nullable()->after('image_one');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_cars', function (Blueprint $table) {
            $table->dropColumn(['image_one', 'image_two']);
        });
    }
};
