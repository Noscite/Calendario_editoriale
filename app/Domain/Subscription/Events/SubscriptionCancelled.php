<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Events;

use App\Domain\Subscription\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SubscriptionCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
    ) {}
}
