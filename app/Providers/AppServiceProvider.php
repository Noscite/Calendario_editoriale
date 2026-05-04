<?php

namespace App\Providers;

use App\Domain\Brand\Services\BrandApiKeyService;
use App\Domain\Review\Models\Review;
use App\Domain\Review\Observers\ReviewObserver;
use App\Domain\Subscription\Events\SubscriptionActivated;
use App\Domain\Subscription\Events\SubscriptionExpiring;
use App\Domain\Subscription\Events\TrialExpired;
use App\Listeners\Subscription\SendSubscriptionActivatedEmail;
use App\Listeners\Subscription\SendSubscriptionExpiringEmail;
use App\Listeners\Subscription\SendTrialExpiredEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BrandApiKeyService::class);

        $this->app->bind(
            \App\Domain\Review\Contracts\KnowledgeRetrieverInterface::class,
            \App\Domain\Review\Services\PgVectorKnowledgeRetriever::class,
        );
    }

    public function boot(): void
    {
        Event::listen(TrialExpired::class, SendTrialExpiredEmail::class);
        Event::listen(SubscriptionActivated::class, SendSubscriptionActivatedEmail::class);
        Event::listen(SubscriptionExpiring::class, SendSubscriptionExpiringEmail::class);

        Review::observe(ReviewObserver::class);
    }
}
