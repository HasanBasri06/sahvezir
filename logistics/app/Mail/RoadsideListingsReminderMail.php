<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RoadsideListingsReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Yol uzerindeki ilanlar seni bekliyor!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.roadside-listings-reminder',
            with: [
                'user' => $this->user,
                'slogan' => "Bos km yapma, hemen TasiBul'a gir ve yol uzerindeki ilanlar ile kazancini katla!",
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
