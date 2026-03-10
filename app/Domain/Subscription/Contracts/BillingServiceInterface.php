<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Contracts;

use App\Domain\Organization\Models\Organization;
use App\Domain\Subscription\Models\Plan;
use Illuminate\Database\Eloquent\Collection;

interface BillingServiceInterface
{
    /**
     * Ottieni tutti i piani attivi disponibili.
     */
    public function getAvailablePlans(bool $includeInactive = false): Collection;

    /**
     * Ottieni un piano per ID.
     */
    public function getPlan(int $planId): Plan;

    /**
     * Aggiorna un piano (superuser).
     */
    public function updatePlan(int $planId, array $data): Plan;

    /**
     * Ottieni i limiti effettivi per un'organizzazione (merge custom_limits con piano).
     */
    public function getEffectiveLimits(Organization $organization): array;

    /**
     * Verifica se l'organizzazione può generare un calendario.
     */
    public function canGenerate(int $organizationId): bool;

    /**
     * Verifica se l'organizzazione può creare un nuovo brand.
     */
    public function canCreateBrand(int $organizationId): bool;

    /**
     * Verifica se l'organizzazione può aggiungere un nuovo utente.
     */
    public function canAddUser(int $organizationId): bool;

    /**
     * Verifica un limite specifico di utilizzo.
     *
     * @param  string  $checkType  Tipo di check: 'calendars', 'tokens', 'images'
     */
    public function checkUsageLimit(int $organizationId, string $checkType): bool;
}
