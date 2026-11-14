<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kullanıcının favorilerini gruplamak için kendi açtığı kategoriler (örn. "Salon", "Hediye fikirleri").
     */
    public function up(): void
    {
        Schema::create('favorite_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::table('favorites', function (Blueprint $table) {
            // Boşsa favori kategorisizdir; kategori silinirse favori kalır, kategorisiz olur
            $table->foreignId('favorite_list_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('favorite_list_id');
        });

        Schema::dropIfExists('favorite_lists');
    }
};
