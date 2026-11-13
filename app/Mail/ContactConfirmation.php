<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Confirmação para quem escreveu, no idioma em que escreveu. */
class ContactConfirmation extends Mailable
{
    public function __construct(public ContactMessage $msg) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.confirm_subject', [], $this->msg->locale));
    }

    public function content(): Content
    {
        return new Content(text: 'mail.contact-confirmation');
    }
}
