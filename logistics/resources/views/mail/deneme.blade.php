@extends('mail.layout')

@section('title', 'İlan Oluşturuldu')

@section('content')
    <h1 style="margin: 0 0 20px 0; font-size: 22px; font-weight: 700; color: #111827;">İlanınız Başarıyla Oluşturuldu!</h1>
    <p style="margin: 0 0 12px 0; color: #374151;">
        Tebrikler, ilanınız başarıyla oluşturuldu ve şimdi platformumuzda yayında! 
        Artık ilanınızı potansiyel müşteriler görebilecek ve size ulaşabilecekler.
    </p>

    <div style="margin: 16px 0 20px 0; background-color: #f9fafb; padding: 18px; border-radius: 8px; border: 1px solid #e5e7eb;">
        <ul style="margin: 0; padding-left: 20px; font-size: 14px; color: #374151;">
            <li>İlan detaylarınızı yönetmek ve düzenlemek için <a href="{{ url('/panel') }}" style="color: #39838C; text-decoration: underline;">panelinize</a> gidebilirsiniz.</li>
            <li>İlgili kullanıcılar ilanınıza teklif veya mesaj gönderebilir.</li>
            <li>Herhangi bir sorunuz olursa destek ekibimizle iletişime geçebilirsiniz.</li>
        </ul>
    </div>

    <p style="margin: 0 0 8px 0; color: #374151;">Başarılar dileriz!</p>
    <p style="margin: 0; color: #6b7280; font-size: 13px;">TaşıBul Ekibi</p>
@endsection
