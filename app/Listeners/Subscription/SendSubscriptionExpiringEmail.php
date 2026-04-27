<?php

declare(strict_types=1);

namespace App\Listeners\Subscription;

use App\Domain\Subscription\Events\SubscriptionExpiring;
use App\Mail\Subscription\SubscriptionExpiringMail;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionExpiringEmail
{
    public function handle(SubscriptionExpiring $event): void
    {
        $subscription = $event->subscription;
        $organization = $subscription->organization;

        if (! $organization) {
            return;
        }

        $users = $organization->users()->whereIn('role', ['owner', 'admin'])->get();

        foreach ($users as $user) {
            Mail::to($user->email)->queue(new SubscriptionExpiringMail($user, $organization, $subscription));
        }
    }
}
