<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    protected $table = 'comments';

    protected $guarded = [];

    protected $appends = [
        'commenter_name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /** Değerlendirmeyi yazan kullanıcı (`comments.sender_user_id` → `users.id`). */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id', 'id');
    }

    /** Eski kayıtlar: yalnızca `creator_user_id` doluysa. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id', 'id');
    }

    /**
     * Değerlendirmeyi yapan kullanıcının görünen adı (API / mobil liste).
     */
    public function getCommenterNameAttribute(): ?string
    {
        $c = $this->sender ?? $this->creator;
        if (! $c) {
            return null;
        }

        $fn = trim((string) ($c->first_name ?? ''));
        $ln = trim((string) ($c->last_name ?? ''));
        $joined = trim($fn.' '.$ln);

        return $joined !== '' ? $joined : ($c->full_name ?? null);
    }
}
