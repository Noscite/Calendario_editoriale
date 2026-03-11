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

class PasswordResetMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $queue = 'email';

    public function __construct(
        public string $token,
        public User $user,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reimposta la tua password — Kalendarium');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.password-reset');
    }
}
