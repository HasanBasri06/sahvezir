<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Kullanıcı–ürün favori kaydı; isteğe bağlı bir kategoriye bağlı.
 */
class Favorite extends Pivot
{
    protected $table = 'favorites';

    public $incrementing = true;
}
