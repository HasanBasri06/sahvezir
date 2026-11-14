<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kullanıcının aldığı paketler ve bitiş tarihleri (abonelik kaydı). Her yeni paket `active`,
     * öncekiler `inactive`; bitiş tarihi artık buradan okunur (users.end_payment_date yalnızca eşlenen kopya).
     */
    public function up(): void
    {
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Ödeme ile alındıysa sipariş (aynı ödeme iki kez işlenmesin)
            $table->unsignedBigInteger('user_order_id')->nullable()->unique();
            // payment: satın alma, trial: kayıt denemesi, admin: yönetici tanımı, legacy: eski kayıttan aktarım
            $table->enum('source', ['payment', 'trial', 'admin', 'legacy'])->default('payment');
            $table->string('package_name')->nullable();
            $table->unsignedSmallInteger('plan_months')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'ends_at']);
        });

        // Mevcut kullanıcıların bitiş tarihlerini aktar (son ödenen paketle eşleştirerek)
        $now = now('Europe/Istanbul');
        DB::table('users')->whereNotNull('end_payment_date')->orderBy('id')->chunkById(200, function ($users) use ($now) {
            foreach ($users as $u) {
                $order = DB::table('user_orders')->where('user_id', $u->id)->where('status', 'paid')->orderByDesc('id')->first();
                $ends = \Illuminate\Support\Carbon::parse($u->end_payment_date, 'Europe/Istanbul');
                $months = $order ? max(1, (int) $order->plan_months) : null;
                DB::table('user_subscriptions')->insert([
                    'user_id' => $u->id,
                    'user_order_id' => $order?->id,
                    'source' => $order ? 'payment' : 'legacy',
                    'package_name' => $order?->package_name,
                    'plan_months' => $months,
                    'price' => $order ? (float) $order->package_price : null,
                    'starts_at' => $order ? \Illuminate\Support\Carbon::parse($order->created_at) : $ends->copy()->subMonthNoOverflow(),
                    'ends_at' => $ends,
                    'status' => ((int) $u->payment_confirm === 1 && $ends->greaterThan($now)) ? 'active' : 'inactive',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
