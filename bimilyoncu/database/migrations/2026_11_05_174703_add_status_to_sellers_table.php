<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satıcı onayı: Satıcı Ol ile açılan mağazalar site yöneticisi onaylayana kadar "pending".
     * Mevcut satıcılar onaylı sayılır.
     */
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->string('status', 20)->default('approved')->after('shipping_days')->index();
            $table->timestamp('reviewed_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn(['status', 'reviewed_at']);
        });
    }
};
