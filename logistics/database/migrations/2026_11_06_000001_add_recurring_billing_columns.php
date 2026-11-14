<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Otomatik ödeme (PayTR kayıtlı kart + tekrarlayan ödeme):
     * - users.paytr_utoken: PayTR'nin kullanıcıya verdiği kart grubu kimliği (kartın kendisi PayTR'de saklanır)
     * - user_subscriptions: tüm satın alımlar tek tabloda — paket (package) ve araç başı aylık ücret (vehicle)
     * - user_orders: ödemenin neye ait olduğu (paket / araç / otomatik yenileme)
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('paytr_utoken', 191)->nullable()->after('end_payment_date');
        });

        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->enum('type', ['package', 'vehicle'])->default('package')->after('user_id');
            $table->unsignedBigInteger('user_car_id')->nullable()->after('type');
            $table->decimal('vat_amount', 10, 2)->nullable()->after('price');
            $table->decimal('total_amount', 10, 2)->nullable()->after('vat_amount');
            // Otomatik talimat: bitişte kayıtlı karttan yenilenir
            $table->boolean('auto_renew')->default(true)->after('status');
            $table->unsignedTinyInteger('renew_attempts')->default(0)->after('auto_renew');
            $table->string('last_renew_error')->nullable()->after('renew_attempts');
            $table->timestamp('last_renew_attempt_at')->nullable()->after('last_renew_error');

            $table->index(['type', 'status', 'ends_at']);
            $table->index(['user_car_id', 'status']);
        });

        Schema::table('user_orders', function (Blueprint $table) {
            $table->enum('order_type', ['package', 'vehicle', 'renewal'])->default('package')->after('user_id');
            $table->unsignedBigInteger('user_car_id')->nullable()->after('order_type');
            // Yenileme ödemesi: hangi aboneliği uzatıyor
            $table->unsignedBigInteger('renews_subscription_id')->nullable()->after('user_car_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_orders', function (Blueprint $table) {
            $table->dropColumn(['order_type', 'user_car_id', 'renews_subscription_id']);
        });

        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['type', 'status', 'ends_at']);
            $table->dropIndex(['user_car_id', 'status']);
            $table->dropColumn(['type', 'user_car_id', 'vat_amount', 'total_amount', 'auto_renew', 'renew_attempts', 'last_renew_error', 'last_renew_attempt_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('paytr_utoken');
        });
    }
};
