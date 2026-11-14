<?php

/**
 * Paket kataloğu — fiyatların tek kaynağı (web ve mobil buradan okur, ödeme tutarı da buradan hesaplanır).
 *
 * - individual (Bireysel): araç sahibi ve yük sahibi; 1 aracı kapsar.
 * - small / medium / large (İşletme): yalnızca araç sahibi; `vehicle_limit` kadar aracı kapsar,
 *   sınırı aşan her araç `extra_vehicle_price` (KDV hariç) / ay.
 * - `prices`: süre (ay) => toplam fiyat (TL).
 *
 * NOT: İşletme paketlerinin fiyat ve araç sınırları şimdilik yer tutucudur.
 */
return [
    'extra_vehicle_price' => 299,
    'vat_rate' => 20,

    'packages' => [
        'individual' => [
            'name' => 'Bireysel',
            'description' => 'Tek araçla çalışan sürücüler ve bireysel yük sahipleri için.',
            'account_types' => ['vehicle_owner', 'cargo_owner'],
            'vehicle_limit' => 1,
            'prices' => [1 => 399, 3 => 999, 6 => 1749, 12 => 3199],
            'features' => [
                'Sınırsız ilan görüntüleme ve teklif',
                'Yük alarmı ve anlık bildirimler',
            ],
        ],
        'small' => [
            'name' => 'Küçük İşletme',
            'description' => 'Birkaç araçlık filolar için ortak yönetim paneli.',
            'account_types' => ['vehicle_owner'],
            'vehicle_limit' => 5,
            'prices' => [1 => 1499, 3 => 3749, 6 => 6549, 12 => 11999],
            'features' => [
                'Bireysel paketteki her şey',
                'Çok Araç Kontrol paneli',
            ],
        ],
        'medium' => [
            'name' => 'Orta İşletme',
            'description' => 'Büyüyen filolar için daha fazla araç.',
            'account_types' => ['vehicle_owner'],
            'vehicle_limit' => 15,
            'prices' => [1 => 3499, 3 => 8749, 6 => 15299, 12 => 27999],
            'features' => [
                'Küçük İşletme paketindeki her şey',
                'Öncelikli destek',
            ],
        ],
        'large' => [
            'name' => 'Büyük İşletme',
            'description' => 'Geniş filolar için en yüksek kapasite.',
            'account_types' => ['vehicle_owner'],
            'vehicle_limit' => 30,
            'prices' => [1 => 6999, 3 => 17499, 6 => 30599, 12 => 55999],
            'features' => [
                'Orta İşletme paketindeki her şey',
                'Özel müşteri temsilcisi',
            ],
        ],
    ],
];
