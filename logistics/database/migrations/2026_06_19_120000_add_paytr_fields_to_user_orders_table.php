<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    }

    public function down(): void
    {
        Schema::table('user_orders', function (Blueprint $table) {
            $table->dropColumn(['merchant_oid', 'plan_months']);
        });
    }
};
