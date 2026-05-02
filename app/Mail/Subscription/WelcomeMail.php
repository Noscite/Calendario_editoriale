<?php

declare(strict_types=1);

namespace App\Mail\Subscription;

use App\Domain\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user)
    {
        $this->onQueue('email');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Benvenuto in Kalendarium — il tuo trial è iniziato');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.subscription.welcome');
    }
}
