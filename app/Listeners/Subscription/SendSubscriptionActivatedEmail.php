<?php

declare(strict_types=1);

namespace App\Listeners\Subscription;

use App\Domain\Subscription\Events\SubscriptionActivated;
use App\Mail\Subscription\SubscriptionActivatedMail;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionActivatedEmail
{
    public function handle(SubscriptionActivated $event): void
    {
        $subscription = $event->subscription;
        $organization = $subscription->organization;

        if (! $organization) {
            return;
        }

        $users = $organization->users()->whereIn('role', ['owner', 'admin'])->get();

        foreach ($users as $user) {
            Mail::to($user->email)->queue(new SubscriptionActivatedMail($user, $organization, $subscription));
        }
    }
}
