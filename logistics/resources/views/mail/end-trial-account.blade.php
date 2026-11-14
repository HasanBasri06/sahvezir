@extends('mail.layout')

@section('title', 'Deneme Süreniz Sona Ermiştir')

@section('content')
        <h1 style="margin: 0 0 20px 0; font-size: 22px; font-weight: 700; color: #111827;">Deneme Süreniz Sona Ermiştir</h1>
        <p style="margin: 0 0 16px 0; color: #4b5563;">Sayın <strong>{{ $user->first_name }} {{ $user->last_name }}</strong>,</p>
        <p style="margin: 0 0 20px 0; color: #374151;">Deneme süreniz sona ermiştir. Hesabınızı aktif hale getirmek için lütfen ödeme işleminizi tamamlayınız.</p>
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top: 24px;">
            <tr>
                <td style="background-color: #39838C; border-radius: 8px;">
                    <a href="{{ config('app.frontend_url', url('/')) }}" target="_blank" style="display: inline-block; padding: 14px 28px; font-size: 15px; font-weight: 600; color: #ffffff; text-decoration: none;">Ödeme Yap</a>
                </td>
            </tr>
        </table>
        <p style="margin: 24px 0 0 0; font-size: 14px; color: #9ca3af;">Sorularınız için destek ekibimizle iletişime geçebilirsiniz.</p>
@endsection
