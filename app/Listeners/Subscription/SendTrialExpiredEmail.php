<?php

declare(strict_types=1);

namespace App\Listeners\Subscription;

use App\Domain\Subscription\Events\TrialExpired;
use App\Mail\Subscription\TrialExpiredMail;
use Illuminate\Support\Facades\Mail;

class SendTrialExpiredEmail
{
    public function handle(TrialExpired $event): void
    {
        $subscription = $event->subscription;
        $organization = $subscription->organization;

        if (! $organization) {
            return;
        }

        $users = $organization->users()->whereIn('role', ['owner', 'admin'])->get();

        foreach ($users as $user) {
            Mail::to($user->email)->queue(new TrialExpiredMail($user, $organization));
        }
    }
}
