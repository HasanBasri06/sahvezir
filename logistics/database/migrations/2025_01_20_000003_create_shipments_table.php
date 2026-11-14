<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique()->default(Str::random(25));
            $table->boolean('call_verify')->default(false);
            $table->string('f_where_city');
            $table->string('t_where_city');
            $table->string('f_where_district');
            $table->string('t_where_district');
            $table->string('hours');
            $table->time('departure_time')->nullable();
            $table->time('time_arrival')->nullable();
            $table->date('departure_date')->nullable();
            $table->string('weight')->nullable();
            $table->unsignedBigInteger('creater_id');
            $table->unsignedBigInteger('car_id');
            $table->unsignedBigInteger('car_detail_id')->nullable();
            $table->unsignedBigInteger('post_type_id');
            $table->tinyInteger('call_access')->default(0);
            $table->float('price');
            $table->enum('status', ['active', 'inactive', 'pending', 'done', 'canceled', 'accepted'])->default('active');
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
