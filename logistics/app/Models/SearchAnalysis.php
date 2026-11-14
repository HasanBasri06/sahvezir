<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchAnalysis extends Model
{
    protected $table = 'search_analysis';

    protected $fillable = [
        'calling_user_id',
        'f_where_city',
        'f_where_district',
        't_where_city',
        't_where_district',
        'count',
    ];

    protected $casts = [
        'count' => 'integer',
    ];

    public function callingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calling_user_id', 'id');
    }
}
