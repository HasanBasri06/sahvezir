<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'E-posta Doğrulama Kodu',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.otp-verification',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
