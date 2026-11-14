<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['user_id', 'author_name', 'rating', 'comment', 'reviewed_at'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'reviewed_at' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Alıcının yorumla birlikte gönderdiği fotoğraflar */
    public function images(): HasMany
    {
        return $this->hasMany(ReviewImage::class)->orderBy('sort');
    }

    /** Satıcı ile alıcı arasındaki yazışma, eskiden yeniye */
    public function replies(): HasMany
    {
        return $this->hasMany(ReviewReply::class)->oldest()->oldest('id');
    }

    /**
     * Gerçek isim API'den dışarı çıkmaz: "Hasan Bulut" → "H*** B***"
     */
    public function maskedAuthor(): string
    {
        return collect(preg_split('/\s+/u', trim($this->author_name)))
            ->filter()
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)).'***')
            ->implode(' ');
    }

    /**
     * Herkese açık yorum verisi; yazışmada satıcı adı ve alıcının maskelenmiş adı gösterilir.
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(Seller $seller): array
    {
        $author = $this->maskedAuthor();

        return [
            'id' => $this->id,
            'name' => $author,
            'rating' => $this->rating,
            'date' => $this->reviewed_at->toDateString(),
            'comment' => $this->comment,
            'images' => $this->images->pluck('path')->values(),
            'replies' => $this->replies->map(fn (ReviewReply $reply) => [
                'id' => $reply->id,
                'author' => $reply->author_type,
                'name' => $reply->isFromSeller() ? $seller->name : $author,
                'logo' => $reply->isFromSeller() ? $seller->logo : null,
                'body' => $reply->body,
                'date' => $reply->created_at->toIso8601String(),
            ])->values(),
        ];
    }
}
