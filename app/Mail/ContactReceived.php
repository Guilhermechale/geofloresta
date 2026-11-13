<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Aviso interno de nova mensagem (em português). */
class ContactReceived extends Mailable
{
    public function __construct(public ContactMessage $msg) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[geofloresta.com] '.$this->msg->subject.' — '.$this->msg->name,
            replyTo: [new Address($this->msg->email, $this->msg->name)],
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.contact-received');
    }
}
