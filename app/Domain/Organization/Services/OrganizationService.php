<?php

declare(strict_types=1);

namespace App\Domain\Organization\Services;

use App\Domain\Organization\Contracts\OrganizationRepositoryInterface;
use App\Domain\Organization\Contracts\OrganizationServiceInterface;
use App\Domain\Organization\Enums\OrganizationStatus;
use App\Domain\Organization\Models\Organization;
use App\Domain\Subscription\Contracts\UsageLogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class OrganizationService implements OrganizationServiceInterface
{
    public function __construct(
        private readonly OrganizationRepositoryInterface $organizationRepository,
        private readonly UsageLogRepositoryInterface $usageLogRepository,
    ) {}

    public function listPaginated(
        ?string $search = null,
        ?int $planId = null,
        ?OrganizationStatus $status = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return $this->organizationRepository->listPaginated($search, $planId, $status, $perPage);
    }

    public function getById(int $id): Organization
    {
        return $this->organizationRepository->findOrFail($id);
    }

    /**
     * Crea un'organizzazione con slug unico, piano e record di usage iniziale.
     *
     * Replica create_organization_saas() di subscriptions.py.
     */
    public function create(array $data): Organization
    {
        return DB::transaction(function () use ($data) {
            // Genera slug unico
            $data['slug'] = $this->organizationRepository->generateUniqueSlug($data['name']);

            // Defaults dal Python
            $data['subscription_status'] ??= OrganizationStatus::Trial->value;
            $data['trial_ends_at'] ??= now()->addDays(14);
            $data['is_active'] ??= true;

            $organization = $this->organizationRepository->create($data);

            // Inizializza record di usage per il mese corrente
            $this->usageLogRepository->initializeForOrganization($organization->id);

            return $organization;
        });
    }

    public function update(int $organizationId, array $data): Organization
    {
        $organization = $this->organizationRepository->findOrFail($organizationId);

        return $this->organizationRepository->update($organization, $data);
    }

    public function suspend(int $organizationId): Organization
    {
        $organization = $this->organizationRepository->findOrFail($organizationId);

        return $this->organizationRepository->update($organization, [
            'subscription_status' => OrganizationStatus::Suspended,
        ]);
    }

    public function activate(int $organizationId): Organization
    {
        $organization = $this->organizationRepository->findOrFail($organizationId);

        return $this->organizationRepository->update($organization, [
            'subscription_status' => OrganizationStatus::Active,
        ]);
    }

    public function delete(int $organizationId): void
    {
        $organization = $this->organizationRepository->findOrFail($organizationId);

        $this->organizationRepository->delete($organization);
    }
}
