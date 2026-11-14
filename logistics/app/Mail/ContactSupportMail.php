<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactSupportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $konu,
        public string $icerik,
        public ?string $userName = null,
        public ?string $userEmail = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[TaşıBul Destek] ' . $this->konu,
            replyTo: $this->userEmail ? [$this->userEmail] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.contact-support',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
