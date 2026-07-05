<?php

namespace App\Mail;

use App\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrgInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public OrganizationInvitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "You're invited to join {$this->invitation->organization->name}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.org-invitation', with: [
            'orgName'     => $this->invitation->organization->name,
            'inviterName' => $this->invitation->invitedBy->name,
            'role'        => $this->invitation->role,
            'acceptUrl'   => route('org.invite.accept', $this->invitation->token),
        ]);
    }
}
