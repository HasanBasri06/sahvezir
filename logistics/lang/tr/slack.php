<?php

return [
    'new_user' => <<<TEXT
\n\n Yeni kullanıcı kaydı (:type) türünde yapıldı.

Telefon: :phone
Adı: :first_name :last_name
Kullanıcının kayıt tarihi: :created_at

Kullanıcı hesabı onaylaması bekleniyor.
TEXT,

    'user_verified' => <<<TEXT
\n\n Yeni kullanıcı kaydı (:type) türünde yapıldı.

Telefon: :phone
Adı: :first_name :last_name
Kullanıcının kayıt tarihi: :created_at

Kullanıcı hesabı onayladı.
TEXT,

    'payment_paid' => <<<TEXT
\n\n Ödeme başarılı

Kullanıcı: :full_name
Telefon: :phone
E-posta: :email
Paket: :package_name
Tutar: :amount
Süre: :months ay
Ödeme zamanı: :paid_at

Durum: Ödeme alındı
TEXT,

    'payment_failed' => <<<TEXT
\n\n Ödeme başarısız

Kullanıcı: :full_name
Telefon: :phone
E-posta: :email
Paket: :package_name
Tutar: :amount
Süre: :months ay
Deneme zamanı: :paid_at

Durum: Ödeme alınamadı
Sebep: :reason
TEXT,

    'account_activation_request' => <<<'TEXT'
Hesap etkinleştirme talebi

Adı: :first_name :last_name
Telefon: :phone

Kişi hesap onaylamaya gönderdi.
TEXT,

    'shipment_load' => <<<TEXT
    \n-------------------\n\n 🟢 İlan Yüklendi \n\n
    Yük Sahibi: :full_name :phone \n
    Nereden: :f_where \n
    Nereye: :t_where \n
    Fiyat: :price \n
    Araç: :car\n\n
    Toplam Aktif İlan: :pickup_truck: :total_count
    TEXT,

    'user_types' => [
        'cargo_owner' => 'Yük sahibi',
        'vehicle_owner' => 'Araç sahibi',
    ],
];
