<?php

namespace App\Providers;

use App\Domain\Brand\Models\Brand;
use App\Domain\Brand\Observers\BrandObserver;
use App\Domain\Brand\Services\BrandApiKeyService;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Observers\OrganizationObserver;
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

        $this->app->bind(
            \App\Domain\Review\Contracts\ReviewScoringServiceInterface::class,
            \App\Domain\Review\Services\ReviewScoringService::class,
        );
        $this->app->bind(
            \App\Domain\Review\Contracts\ReviewReplyGeneratorInterface::class,
            \App\Domain\Review\Services\ReviewReplyGenerator::class,
        );
        $this->app->bind(
            \App\Domain\Review\Contracts\OntologyBootstrapServiceInterface::class,
            \App\Domain\Review\Services\OntologyBootstrapService::class,
        );
        $this->app->bind(
            \App\Domain\Document\Contracts\OpenAiEmbeddingClientInterface::class,
            \App\Domain\Document\Services\OpenAiEmbeddingClient::class,
        );
    }

    public function boot(): void
    {
        Event::listen(TrialExpired::class, SendTrialExpiredEmail::class);
        Event::listen(SubscriptionActivated::class, SendSubscriptionActivatedEmail::class);
        Event::listen(SubscriptionExpiring::class, SendSubscriptionExpiringEmail::class);

        Review::observe(ReviewObserver::class);
        Brand::observe(BrandObserver::class);
        Organization::observe(OrganizationObserver::class);
    }
}
