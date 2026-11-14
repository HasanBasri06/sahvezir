@extends('mail.layout')

@section('title', 'TaşıBul Destek Talebi')

@section('content')
        <h1 style="margin: 0 0 20px 0; font-size: 22px; font-weight: 700; color: #111827;">Yeni Destek Talebi</h1>
        @if($userName || $userEmail)
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 20px; background-color: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
            <tr>
                <td style="padding: 14px 18px; font-size: 14px; color: #6b7280;">
                    @if($userName) <strong style="color: #374151;">Gönderen:</strong> {{ $userName }}<br> @endif
                    @if($userEmail) <strong style="color: #374151;">E-posta:</strong> {{ $userEmail }} @endif
                </td>
            </tr>
        </table>
        @endif
        <p style="margin: 0 0 8px 0; font-size: 14px; font-weight: 600; color: #374151;">Konu:</p>
        <p style="margin: 0 0 16px 0; font-size: 16px; color: #111827;">{{ $konu }}</p>
        <p style="margin: 0 0 8px 0; font-size: 14px; font-weight: 600; color: #374151;">Mesaj:</p>
        <div style="margin: 0; padding: 18px; background-color: #f9fafb; border-radius: 8px; border-left: 4px solid #39838C; font-size: 15px; color: #374151; white-space: pre-wrap;">{{ $icerik }}</div>
@endsection
