<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Organization\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $queue = 'email';

    public function __construct(
        public Invitation $invitation,
    ) {}

    public function envelope(): Envelope
    {
        $inviterName = $this->invitation->inviter?->full_name ?? 'Un collega';

        return new Envelope(
            subject: "{$inviterName} ti ha invitato su Kalendarium",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.invitation');
    }
}
