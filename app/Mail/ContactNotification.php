<?php

namespace App\Mail;

use App\Models\ContactSubmission;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactNotification extends Mailable
{
    use SerializesModels;

    public function __construct(public ContactSubmission $submission) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New contact message from {$this->submission->name}",
            replyTo: [$this->submission->email],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.contact-notification');
    }
}
