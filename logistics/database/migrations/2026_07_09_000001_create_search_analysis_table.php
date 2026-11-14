<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('search_analysis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('calling_user_id');
            $table->string('f_where_city')->nullable();
            $table->string('f_where_district')->nullable();
            $table->string('t_where_city')->nullable();
            $table->string('t_where_district')->nullable();
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->foreign('calling_user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_analysis');
    }
};
