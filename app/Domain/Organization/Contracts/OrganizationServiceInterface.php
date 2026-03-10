<?php

declare(strict_types=1);

namespace App\Domain\Organization\Contracts;

use App\Domain\Organization\Enums\OrganizationStatus;
use App\Domain\Organization\Models\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrganizationServiceInterface
{
    /**
     * Elenca le organizzazioni con filtri e paginazione (admin).
     */
    public function listPaginated(
        ?string $search = null,
        ?int $planId = null,
        ?OrganizationStatus $status = null,
        int $perPage = 20,
    ): LengthAwarePaginator;

    /**
     * Ottieni un'organizzazione per ID.
     */
    public function getById(int $id): Organization;

    /**
     * Crea una nuova organizzazione con piano e tracking iniziale.
     */
    public function create(array $data): Organization;

    /**
     * Aggiorna un'organizzazione.
     */
    public function update(int $organizationId, array $data): Organization;

    /**
     * Sospendi un'organizzazione.
     */
    public function suspend(int $organizationId): Organization;

    /**
     * Attiva un'organizzazione.
     */
    public function activate(int $organizationId): Organization;

    /**
     * Elimina un'organizzazione (soft delete).
     */
    public function delete(int $organizationId): void;
}
