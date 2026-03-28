<?php

namespace App\Mail;

use App\Models\Call;
use App\Models\CallMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VoicemailReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Call $call,
        public CallMessage $callMessage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Receptio - nouveau message vocal',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.voicemail-received',
        );
    }
}
