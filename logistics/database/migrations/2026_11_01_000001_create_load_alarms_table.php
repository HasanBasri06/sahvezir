<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Araç sahibinin yük alarmları: nereden → nereye (nereye isteğe bağlı).
     * İlçe boşsa şehrin tamamı, t_city boşsa her varış noktası için geçerlidir.
     */
    public function up(): void
    {
        Schema::create('load_alarms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('f_city');
            $table->string('f_district')->nullable();
            $table->string('t_city')->nullable();
            $table->string('t_district')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index(['is_active', 'f_city', 't_city']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('load_alarms');
    }
};
