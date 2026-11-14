<?php

// Sipariş tutarı hesabı; önyüzdeki lib/checkout.ts ile aynı değerler kullanılmalı
return [

    'vat_rate' => (float) env('SHOP_VAT_RATE', 0.2),

    'shipping_fee' => (float) env('SHOP_SHIPPING_FEE', 49.9),

    'free_shipping_limit' => (float) env('SHOP_FREE_SHIPPING_LIMIT', 500),

];
