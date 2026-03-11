<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrialReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $queue = 'email';

    public function __construct(
        public User $user,
        public int $daysRemaining,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->daysRemaining === 1
            ? 'Il tuo trial scade domani — Kalendarium'
            : "Il tuo trial scade tra {$this->daysRemaining} giorni — Kalendarium";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.trial-reminder');
    }
}
