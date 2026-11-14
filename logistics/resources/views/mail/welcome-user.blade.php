@extends('mail.layout')

@section('title', 'TaşıBul\'a Hoş Geldiniz')

@section('content')
    @php
        $baseUrl = trim((string) ($siteUrl ?? ''));
        if ($baseUrl === '') {
            $baseUrl = config('app.url', 'https://tasibul.com');
        }
        if (!preg_match('/^https?:\/\//i', $baseUrl)) {
            $baseUrl = 'https://' . ltrim($baseUrl, '/');
        }
        $baseUrl = rtrim($baseUrl, '/');
    @endphp

    <h1 style="margin: 0 0 16px 0; font-size: 22px; font-weight: 700; color: #111827;">
        Hoş geldiniz, {{ $user->first_name }}!
    </h1>

    @if($user->type === 'cargo_owner')
        <p style="margin: 0 0 14px 0; color: #374151;">
            TaşıBul'a katıldığınız için teşekkür ederiz. Yük sahibi olarak artık ilan oluşturabilir, gelen teklifleri karşılaştırabilir
            ve sizin için en uygun taşıyıcıyı hızlıca seçebilirsiniz.
        </p>
        <p style="margin: 0 0 22px 0; color: #374151;">
            İlk adım olarak ilanınızı oluşturup taşıma sürecinizi başlatabilirsiniz.
        </p>
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top: 6px;">
            <tr>
                <td style="background-color: #39838C; border-radius: 8px;">
                    <a href="{{ $baseUrl }}/cargo-owner/posts/create" target="_blank" style="display: inline-block; padding: 12px 20px; font-size: 14px; font-weight: 600; color: #ffffff; text-decoration: none;">
                        İlan Oluşturmaya Başla
                    </a>
                </td>
            </tr>
        </table>
        <p style="margin: 12px 0 0 0; font-size: 13px; color: #6b7280;">
            Buton çalışmazsa şu linki kullanın:
            <a href="{{ $baseUrl }}/cargo-owner/posts/create" target="_blank" style="color: #39838C; text-decoration: underline;">
                {{ $baseUrl }}/cargo-owner/posts/create
            </a>
        </p>
    @elseif($user->type === 'vehicle_owner')
        <p style="margin: 0 0 14px 0; color: #374151;">
            TaşıBul'a katıldığınız için teşekkür ederiz. Araç sahibi olarak ilanlara teklif verebilir, yük sahipleri ile
            mesajlaşabilir ve işlerinizi dijital olarak yönetebilirsiniz.
        </p>
        <p style="margin: 0 0 22px 0; color: #374151;">
            İlk adım olarak araçlarınızı ekleyip size uygun ilanları incelemeye başlayabilirsiniz.
        </p>
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top: 6px;">
            <tr>
                <td style="background-color: #39838C; border-radius: 8px;">
                    <a href="{{ $baseUrl }}/vehicle-owner/vehicles" target="_blank" style="display: inline-block; padding: 12px 20px; font-size: 14px; font-weight: 600; color: #ffffff; text-decoration: none;">
                        Araçlarımı Görüntüle
                    </a>
                </td>
            </tr>
        </table>
        <p style="margin: 12px 0 0 0; font-size: 13px; color: #6b7280;">
            Buton çalışmazsa şu linki kullanın:
            <a href="{{ $baseUrl }}/vehicle-owner/vehicles" target="_blank" style="color: #39838C; text-decoration: underline;">
                {{ $baseUrl }}/vehicle-owner/vehicles
            </a>
        </p>
    @else
        <p style="margin: 0 0 22px 0; color: #374151;">
            Hesabınız başarıyla oluşturuldu. Platformu kullanmaya başlamak için aşağıdaki bağlantıyı kullanabilirsiniz.
        </p>
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top: 6px;">
            <tr>
                <td style="background-color: #39838C; border-radius: 8px;">
                    <a href="{{ $baseUrl }}" target="_blank" style="display: inline-block; padding: 12px 20px; font-size: 14px; font-weight: 600; color: #ffffff; text-decoration: none;">
                        Siteye Git
                    </a>
                </td>
            </tr>
        </table>
        <p style="margin: 12px 0 0 0; font-size: 13px; color: #6b7280;">
            Buton çalışmazsa şu linki kullanın:
            <a href="{{ $baseUrl }}" target="_blank" style="color: #39838C; text-decoration: underline;">
                {{ $baseUrl }}
            </a>
        </p>
    @endif

    <p style="margin: 20px 0 0 0; font-size: 13px; color: #9ca3af;">
        Herhangi bir sorunuz olursa destek ekibimize dilediğiniz zaman ulaşabilirsiniz.
    </p>
@endsection

