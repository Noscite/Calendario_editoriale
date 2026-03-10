<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Services;

use App\Domain\Brand\Models\Brand;
use App\Domain\Organization\Contracts\OrganizationRepositoryInterface;
use App\Domain\Organization\Models\Organization;
use App\Domain\Subscription\Contracts\BillingServiceInterface;
use App\Domain\Subscription\Contracts\UsageLogRepositoryInterface;
use App\Domain\Subscription\Models\Plan;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class BillingService implements BillingServiceInterface
{
    public function __construct(
        private readonly OrganizationRepositoryInterface $organizationRepository,
        private readonly UsageLogRepositoryInterface $usageLogRepository,
    ) {}

    public function getAvailablePlans(bool $includeInactive = false): Collection
    {
        $query = Plan::orderBy('price_monthly');

        if (! $includeInactive) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    public function getPlan(int $planId): Plan
    {
        return Plan::findOrFail($planId);
    }

    public function updatePlan(int $planId, array $data): Plan
    {
        $plan = Plan::findOrFail($planId);
        $plan->update($data);

        return $plan->refresh();
    }

    /**
     * Calcola limiti effettivi considerando custom_limits per Enterprise.
     *
     * Replica get_effective_limits() di subscriptions.py.
     */
    public function getEffectiveLimits(Organization $organization): array
    {
        $plan = $organization->plan;

        $defaults = [
            'max_brands' => 1,
            'max_users' => 1,
            'monthly_calendars' => 3,
            'monthly_tokens' => 50_000,
            'monthly_images' => 20,
        ];

        if ($organization->custom_limits) {
            $custom = $organization->custom_limits;

            return [
                'max_brands' => $custom['max_brands'] ?? $plan?->max_brands ?? $defaults['max_brands'],
                'max_users' => $custom['max_users'] ?? $plan?->max_users ?? $defaults['max_users'],
                'monthly_calendars' => $custom['monthly_calendar_generations'] ?? $plan?->monthly_calendar_generations ?? $defaults['monthly_calendars'],
                'monthly_tokens' => $custom['monthly_text_tokens'] ?? $plan?->monthly_text_tokens ?? $defaults['monthly_tokens'],
                'monthly_images' => $custom['monthly_images'] ?? $plan?->monthly_images ?? $defaults['monthly_images'],
            ];
        }

        if ($plan) {
            return [
                'max_brands' => $plan->max_brands,
                'max_users' => $plan->max_users,
                'monthly_calendars' => $plan->monthly_calendar_generations,
                'monthly_tokens' => $plan->monthly_text_tokens,
                'monthly_images' => $plan->monthly_images,
            ];
        }

        return $defaults;
    }

    /**
     * Verifica se l'organizzazione può generare un calendario.
     */
    public function canGenerate(int $organizationId): bool
    {
        return $this->checkUsageLimit($organizationId, 'calendars');
    }

    /**
     * Verifica se l'organizzazione può creare un nuovo brand.
     */
    public function canCreateBrand(int $organizationId): bool
    {
        $organization = $this->organizationRepository->findOrFail($organizationId);
        $limits = $this->getEffectiveLimits($organization);
        $maxBrands = $limits['max_brands'];

        // -1 = illimitato
        if ($maxBrands === -1) {
            return true;
        }

        $currentCount = Brand::where('organization_id', $organizationId)->count();

        return $currentCount < $maxBrands;
    }

    /**
     * Verifica se l'organizzazione può aggiungere un nuovo utente.
     */
    public function canAddUser(int $organizationId): bool
    {
        $organization = $this->organizationRepository->findOrFail($organizationId);
        $limits = $this->getEffectiveLimits($organization);
        $maxUsers = $limits['max_users'];

        if ($maxUsers === -1) {
            return true;
        }

        $currentCount = User::withoutOrganization()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->count();

        return $currentCount < $maxUsers;
    }

    /**
     * Verifica un limite specifico di utilizzo.
     *
     * Replica check_usage_limit() di subscriptions.py.
     * -1 = illimitato; se allows_overage = true, consente comunque.
     */
    public function checkUsageLimit(int $organizationId, string $checkType): bool
    {
        $organization = $this->organizationRepository->findOrFail($organizationId);
        $limits = $this->getEffectiveLimits($organization);

        $usage = $this->usageLogRepository->findCurrentPeriod($organizationId);

        if (! $usage) {
            return true; // Nessun usage = nessun consumo
        }

        [$used, $limit] = match ($checkType) {
            'calendars' => [$usage->calendar_generations_used, $limits['monthly_calendars']],
            'tokens' => [$usage->text_tokens_used, $limits['monthly_tokens']],
            'images' => [$usage->images_generated, $limits['monthly_images']],
            default => [0, -1],
        };

        // -1 = illimitato
        if ($limit === -1) {
            return true;
        }

        if ($used >= $limit) {
            // Consenti se il piano permette overage
            $plan = $organization->plan;

            return $plan?->allows_overage ?? false;
        }

        return true;
    }
}
