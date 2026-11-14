<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Her sipariş satırının kendi durumu: satıcı kargolar/teslim eder, alıcı iade talep eder.
     * (Bir siparişte farklı satıcıların ürünleri olabilir; her satıcı kendi satırını yönetir.)
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('status', 20)->default('preparing')->after('line_total');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('return_reason', 500)->nullable();
            $table->timestamp('return_requested_at')->nullable();
            $table->timestamp('returned_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['status', 'shipped_at', 'delivered_at', 'return_reason', 'return_requested_at', 'returned_at']);
        });
    }
};
