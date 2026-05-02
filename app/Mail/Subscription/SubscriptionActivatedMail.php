<?php

declare(strict_types=1);

namespace App\Mail\Subscription;

use App\Domain\Organization\Models\Organization;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionActivatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User         $user,
        public readonly Organization $organization,
        public readonly Subscription $subscription,
    ) {
        $this->onQueue('email');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Abbonamento Kalendarium attivato — benvenuto a bordo!');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.subscription.subscription-activated');
    }
}
