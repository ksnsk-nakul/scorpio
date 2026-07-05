<?php

namespace App\Mail;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberAddedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Organization $organization,
        public string $role
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "You've been added to {$this->organization->name}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.member-added', with: [
            'orgName' => $this->organization->name,
            'role'    => $this->role,
            'orgUrl'  => route('admin.organizations.show', $this->organization),
        ]);
    }
}
