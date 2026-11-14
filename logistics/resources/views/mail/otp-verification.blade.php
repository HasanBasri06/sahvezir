@extends('mail.layout')

@section('title', 'E-posta Doğrulama Kodu')

@section('content')
        <h1 style="margin: 0 0 20px 0; font-size: 22px; font-weight: 700; color: #111827;">E-posta Doğrulama</h1>
        <p style="margin: 0 0 24px 0; color: #374151;">Kayıt işleminizi tamamlamak için aşağıdaki 6 haneli doğrulama kodunu kullanın:</p>
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td align="center" style="padding: 20px 0;">
                    <span style="display: inline-block; font-size: 28px; font-weight: 700; letter-spacing: 8px; background-color: #f0f9ff; color: #39838C; padding: 16px 32px; border-radius: 10px; border: 2px solid #39838C;">{{ $code }}</span>
                </td>
            </tr>
        </table>
        <p style="margin: 0; font-size: 14px; color: #6b7280;">Bu kodu kimseyle paylaşmayın. Kod sınırlı süre geçerlidir.</p>
@endsection
