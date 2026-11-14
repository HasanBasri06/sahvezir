<?php

use App\Models\SellerUser;
use Illuminate\Support\Facades\Broadcast;

// Satıcı yalnızca kendi mağazasının sipariş kanalını dinleyebilir
Broadcast::channel('seller.{sellerId}', function ($user, int $sellerId) {
    return $user instanceof SellerUser && (int) $user->seller_id === $sellerId;
}, ['guards' => ['seller']]);
