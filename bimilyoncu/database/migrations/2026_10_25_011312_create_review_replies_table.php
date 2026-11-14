<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Yorumlar tek satıcı yanıtı yerine yazışmaya döner: satıcı ve yorumu yazan alıcı sırayla yanıt verebilir.
     */
    public function up(): void
    {
        Schema::create('review_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            // Yanıtı yazan: satıcı paneli kullanıcısı ya da yorumu yazan alıcı (biri dolu olur)
            $table->foreignId('seller_user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_type', 10);
            $table->text('body');
            $table->timestamps();

            $table->index(['review_id', 'created_at']);
        });

        // Eski tek satıcı yanıtları yazışmanın ilk mesajı olur
        $now = now();
        DB::table('reviews')->whereNotNull('seller_reply')->orderBy('id')->each(function ($review) use ($now) {
            DB::table('review_replies')->insert([
                'review_id' => $review->id,
                'author_type' => 'seller',
                'body' => $review->seller_reply,
                'created_at' => $review->replied_at ?? $now,
                'updated_at' => $review->replied_at ?? $now,
            ]);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['seller_reply', 'replied_at']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            // Bir alıcı bir ürüne bir kez yorum yapar
            $table->unique(['product_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'user_id']);
            $table->text('seller_reply')->nullable();
            $table->date('replied_at')->nullable();
        });

        Schema::dropIfExists('review_replies');
    }
};
