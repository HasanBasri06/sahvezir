<?php

namespace App\Mail;

use App\Models\Requests;
use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OfferSentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Shipment $shipment,
        public Requests $offerRequest,
        public string $recipientType // cargo_owner | vehicle_owner
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'TaşıBul - Yeni Teklif Bildirimi',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.offer-sent',
            with: [
                'shipment' => $this->shipment,
                'offerRequest' => $this->offerRequest,
                'recipientType' => $this->recipientType,
                'siteUrl' => config('app.frontend_url', 'https://tasibul.com'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

