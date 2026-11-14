@extends('mail.layout')

@section('title', 'Yeni Teklif Bildirimi')

@section('content')
    @if($recipientType === 'cargo_owner')
        <h1 style="margin: 0 0 16px 0; font-size: 22px; font-weight: 700; color: #111827;">
            İlanınıza yeni bir teklif geldi
        </h1>
        <p style="margin: 0 0 14px 0; color: #374151;">
            <strong>{{ $shipment->f_where_city }} / {{ $shipment->f_where_district }}</strong> -
            <strong>{{ $shipment->t_where_city }} / {{ $shipment->t_where_district }}</strong>
            ilanınıza yeni bir teklif gönderildi.
        </p>
        <p style="margin: 0 0 22px 0; color: #374151;">
            Teklifleri görüntülemek ve değerlendirmek için aşağıdaki butonu kullanabilirsiniz.
        </p>
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top: 6px;">
            <tr>
                <td style="background-color: #39838C; border-radius: 8px;">
                    <a href="{{ rtrim($siteUrl, '/') }}/product/{{ $shipment->slug }}" target="_blank" style="display: inline-block; padding: 12px 20px; font-size: 14px; font-weight: 600; color: #ffffff; text-decoration: none;">
                        Teklifleri Görüntüle
                    </a>
                </td>
            </tr>
        </table>
    @else
        <h1 style="margin: 0 0 16px 0; font-size: 22px; font-weight: 700; color: #111827;">
            Teklifiniz başarıyla gönderildi
        </h1>
        <p style="margin: 0 0 14px 0; color: #374151;">
            <strong>{{ $shipment->f_where_city }} / {{ $shipment->f_where_district }}</strong> -
            <strong>{{ $shipment->t_where_city }} / {{ $shipment->t_where_district }}</strong>
            ilanı için teklifiniz sisteme kaydedildi.
        </p>
        <p style="margin: 0 0 22px 0; color: #374151;">
            Süreci takip etmek için aşağıdaki butondan ilan detayına gidebilirsiniz.
        </p>
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top: 6px;">
            <tr>
                <td style="background-color: #39838C; border-radius: 8px;">
                    <a href="{{ rtrim($siteUrl, '/') }}/posts/{{ $shipment->slug }}" target="_blank" style="display: inline-block; padding: 12px 20px; font-size: 14px; font-weight: 600; color: #ffffff; text-decoration: none;">
                        İlan Detayına Git
                    </a>
                </td>
            </tr>
        </table>
    @endif

    <p style="margin: 20px 0 0 0; font-size: 13px; color: #9ca3af;">
        Bu e-posta bilgilendirme amaçlıdır.
    </p>
@endsection

