<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geçmişte veritabanına elle eklenmiş, ama hiçbir migration'da olmayan tablo/kolonlar.
 * Uygulama bunları kullandığı için temiz kurulumda (ör. Docker) da oluşmaları gerekiyor.
 * Mevcut veritabanlarında hepsi zaten var; her adım varlık kontrolü yaptığı için orada hiçbir şey değişmez.
 * 2026_11_04_000002_create_user_subscriptions_table (users.end_payment_date okur) bundan önce çalışmamalı.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->enum('name', ['vehicle_owner', 'cargo_owner']);
                $table->timestamps();
            });
        }

        $this->addMissing('users', [
            'end_payment_date' => fn (Blueprint $t) => $t->string('end_payment_date', 155)->nullable(),
            'email_verified_at' => fn (Blueprint $t) => $t->timestamp('email_verified_at')->nullable(),
        ]);

        $this->addMissing('message', [
            'sender_message' => fn (Blueprint $t) => $t->string('sender_message', 155)->nullable(),
            'receiver_message' => fn (Blueprint $t) => $t->string('receiver_message', 155)->nullable(),
        ]);

        // 2026_06_19_120000_add_paytr_fields_to_user_orders_table'ın up() metodu boş bırakılmış
        $this->addMissing('user_orders', [
            'merchant_oid' => fn (Blueprint $t) => $t->string('merchant_oid', 70)->nullable(),
            'plan_months' => fn (Blueprint $t) => $t->integer('plan_months')->nullable(),
        ]);

        $this->addMissing('shipments', [
            'code' => fn (Blueprint $t) => $t->string('code', 6)->nullable(),
            'message' => fn (Blueprint $t) => $t->string('message', 500)->nullable(),
        ]);
    }

    public function down(): void
    {
        // Bilinçli olarak boş: bu kolonlar uygulamanın parçası, geri alınca veri kaybı olur.
    }

    /** @param array<string, callable(Blueprint): mixed> $columns */
    private function addMissing(string $table, array $columns): void
    {
        $missing = array_filter(
            $columns,
            fn (string $column) => ! Schema::hasColumn($table, $column),
            ARRAY_FILTER_USE_KEY
        );

        if ($missing === []) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($missing) {
            foreach ($missing as $define) {
                $define($t);
            }
        });
    }
};
