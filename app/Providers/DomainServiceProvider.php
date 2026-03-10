<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// ── Repository Contracts ───────────────────────────────────────
use App\Domain\Brand\Contracts\BrandRepositoryInterface;
use App\Domain\Project\Contracts\ProjectRepositoryInterface;
use App\Domain\Post\Contracts\PostRepositoryInterface;
use App\Domain\Organization\Contracts\OrganizationRepositoryInterface;
use App\Domain\User\Contracts\UserRepositoryInterface;
use App\Domain\Social\Contracts\SocialConnectionRepositoryInterface;
use App\Domain\Document\Contracts\DocumentRepositoryInterface;
use App\Domain\Subscription\Contracts\UsageLogRepositoryInterface;

// ── Repository Implementations ─────────────────────────────────
use App\Domain\Brand\Repositories\BrandRepository;
use App\Domain\Project\Repositories\ProjectRepository;
use App\Domain\Post\Repositories\PostRepository;
use App\Domain\Organization\Repositories\OrganizationRepository;
use App\Domain\User\Repositories\UserRepository;
use App\Domain\Social\Repositories\SocialConnectionRepository;
use App\Domain\Document\Repositories\DocumentRepository;
use App\Domain\Subscription\Repositories\UsageLogRepository;

// ── Service Contracts ──────────────────────────────────────────
use App\Domain\Brand\Contracts\BrandServiceInterface;
use App\Domain\Project\Contracts\ProjectServiceInterface;
use App\Domain\Post\Contracts\PostServiceInterface;
use App\Domain\Organization\Contracts\OrganizationServiceInterface;
use App\Domain\User\Contracts\UserServiceInterface;
use App\Domain\Subscription\Contracts\BillingServiceInterface;
use App\Domain\Subscription\Contracts\UsageTrackerInterface;
use App\Domain\Generation\Contracts\ContentGeneratorInterface;
use App\Domain\Generation\Contracts\ImageGeneratorInterface;
use App\Domain\Generation\Contracts\TrendResearcherInterface;

// ── Service Implementations ────────────────────────────────────
use App\Domain\Brand\Services\BrandService;
use App\Domain\Project\Services\ProjectService;
use App\Domain\Post\Services\PostService;
use App\Domain\Organization\Services\OrganizationService;
use App\Domain\User\Services\UserService;
use App\Domain\Subscription\Services\BillingService;
use App\Domain\Subscription\Services\UsageTracker;

// ── AI Service Implementations ──────────────────────────────────
use App\Domain\Generation\Services\ClaudeContentGenerator;
use App\Domain\Generation\Services\DalleImageGenerator;
use App\Domain\Generation\Services\PerplexityTrendResearcher;

/**
 * Registra tutti i binding Domain: Repository e Service.
 *
 * Pattern identico a MCP Hub — ogni interfaccia è legata
 * alla sua implementazione concreta tramite bind().
 */
final class DomainServiceProvider extends ServiceProvider
{
    /**
     * Binding Repository: interfaccia → implementazione Eloquent.
     *
     * @var array<class-string, class-string>
     */
    private array $repositories = [
        BrandRepositoryInterface::class          => BrandRepository::class,
        ProjectRepositoryInterface::class        => ProjectRepository::class,
        PostRepositoryInterface::class           => PostRepository::class,
        OrganizationRepositoryInterface::class   => OrganizationRepository::class,
        UserRepositoryInterface::class           => UserRepository::class,
        SocialConnectionRepositoryInterface::class => SocialConnectionRepository::class,
        DocumentRepositoryInterface::class       => DocumentRepository::class,
        UsageLogRepositoryInterface::class       => UsageLogRepository::class,
    ];

    /**
     * Binding Service: interfaccia → implementazione concreta.
     *
     * I 3 servizi AI (ContentGenerator, ImageGenerator, TrendResearcher)
     * verranno registrati quando le implementazioni saranno create.
     *
     * @var array<class-string, class-string>
     */
    private array $services = [
        BrandServiceInterface::class        => BrandService::class,
        ProjectServiceInterface::class      => ProjectService::class,
        PostServiceInterface::class         => PostService::class,
        OrganizationServiceInterface::class => OrganizationService::class,
        UserServiceInterface::class         => UserService::class,
        BillingServiceInterface::class      => BillingService::class,
        UsageTrackerInterface::class        => UsageTracker::class,
    ];

    /**
     * Binding servizi AI — stub implementations.
     * Sostituire con ClaudeContentGenerator, DalleImageGenerator, PerplexityTrendResearcher
     * quando i servizi AI saranno configurati.
     *
     * @var array<class-string, class-string>
     */
    private array $aiServices = [
        ContentGeneratorInterface::class  => ClaudeContentGenerator::class,
        ImageGeneratorInterface::class    => DalleImageGenerator::class,
        TrendResearcherInterface::class   => PerplexityTrendResearcher::class,
    ];

    // ────────────────────────────────────────────────────────────

    public function register(): void
    {
        foreach ($this->repositories as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }

        foreach ($this->services as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }

        foreach ($this->aiServices as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }

    public function boot(): void
    {
        //
    }
}
