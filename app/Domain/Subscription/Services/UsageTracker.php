<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Services;

use App\Domain\Organization\Contracts\OrganizationRepositoryInterface;
use App\Domain\Subscription\Contracts\BillingServiceInterface;
use App\Domain\Subscription\Contracts\UsageLogRepositoryInterface;
use App\Domain\Subscription\Contracts\UsageTrackerInterface;

final class UsageTracker implements UsageTrackerInterface
{
    public function __construct(
        private readonly UsageLogRepositoryInterface $usageLogRepository,
        private readonly OrganizationRepositoryInterface $organizationRepository,
        private readonly BillingServiceInterface $billing,
    ) {}

    /**
     * Registra l'utilizzo di una generazione di calendario.
     *
     * Replica increment_usage(db, org_id, calendars=1) di subscriptions.py.
     */
    public function trackCalendarGeneration(int $organizationId, int $amount = 1): void
    {
        $this->usageLogRepository->incrementCalendarGenerations($organizationId, $amount);
    }

    /**
     * Registra l'utilizzo di token di testo.
     *
     * Replica increment_usage(db, org_id, tokens=N) di subscriptions.py.
     */
    public function trackTextTokens(int $organizationId, int $tokens): void
    {
        $this->usageLogRepository->incrementTextTokens($organizationId, $tokens);
    }

    /**
     * Registra la generazione di un'immagine.
     *
     * Replica increment_usage(db, org_id, images=1) di subscriptions.py.
     */
    public function trackImageGeneration(int $organizationId, int $amount = 1): void
    {
        $this->usageLogRepository->incrementImagesGenerated($organizationId, $amount);
    }

    /**
     * Ottieni il riepilogo di utilizzo per l'organizzazione corrente.
     *
     * Replica get_my_usage() di subscriptions.py.
     *
     * @return array{
     *     calendars_used: int,
     *     calendars_limit: int,
     *     tokens_used: int,
     *     tokens_limit: int,
     *     images_used: int,
     *     images_limit: int,
     *     period_start: string,
     *     period_end: string,
     * }
     */
    public function getUsageSummary(int $organizationId): array
    {
        $organization = $this->organizationRepository->findOrFail($organizationId);
        $limits = $this->billing->getEffectiveLimits($organization);
        $usage = $this->usageLogRepository->findOrCreateCurrentPeriod($organizationId);

        return [
            'calendars_used' => $usage->calendar_generations_used,
            'calendars_limit' => $limits['monthly_calendars'],
            'tokens_used' => $usage->text_tokens_used,
            'tokens_limit' => $limits['monthly_tokens'],
            'images_used' => $usage->images_generated,
            'images_limit' => $limits['monthly_images'],
            'period_start' => $usage->period_start->toDateString(),
            'period_end' => $usage->period_end->toDateString(),
        ];
    }

    /**
     * Ottieni l'utilizzo per un periodo specifico (admin).
     *
     * Replica get_organization_usage() di subscriptions.py.
     */
    public function getUsageForPeriod(int $organizationId, ?string $period = null): array
    {
        $organization = $this->organizationRepository->findOrFail($organizationId);
        $limits = $this->billing->getEffectiveLimits($organization);
        $usage = $this->usageLogRepository->getStatsByOrganization($organizationId, $period);

        if (! $usage) {
            return [
                'calendars_used' => 0,
                'calendars_limit' => $limits['monthly_calendars'],
                'tokens_used' => 0,
                'tokens_limit' => $limits['monthly_tokens'],
                'images_used' => 0,
                'images_limit' => $limits['monthly_images'],
                'overage_tokens' => 0,
                'overage_images' => 0,
                'overage_cost' => '0.00',
            ];
        }

        return [
            'calendars_used' => $usage->calendar_generations_used,
            'calendars_limit' => $limits['monthly_calendars'],
            'tokens_used' => $usage->text_tokens_used,
            'tokens_limit' => $limits['monthly_tokens'],
            'images_used' => $usage->images_generated,
            'images_limit' => $limits['monthly_images'],
            'overage_tokens' => $usage->overage_tokens,
            'overage_images' => $usage->overage_images,
            'overage_cost' => (string) ($usage->overage_cost ?? '0.00'),
        ];
    }

    /**
     * Verifica se l'organizzazione ha quota disponibile per un tipo specifico.
     *
     * Replica check_usage_limit() di subscriptions.py.
     */
    public function checkQuota(int $organizationId, string $type): bool
    {
        return $this->billing->checkUsageLimit($organizationId, $type);
    }
}
