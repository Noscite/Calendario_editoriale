<?php

declare(strict_types=1);

namespace App\Domain\Organization\Contracts;

use App\Domain\Organization\Enums\OrganizationStatus;
use App\Domain\Organization\Models\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface OrganizationRepositoryInterface
{
    /**
     * Trova un'organizzazione per ID.
     */
    public function findById(int $id): ?Organization;

    /**
     * Trova un'organizzazione per ID o lancia eccezione.
     */
    public function findOrFail(int $id): Organization;

    /**
     * Trova un'organizzazione per slug.
     */
    public function findBySlug(string $slug): ?Organization;

    /**
     * Elenca tutte le organizzazioni con filtri e paginazione.
     */
    public function listPaginated(
        ?string $search = null,
        ?int $planId = null,
        ?OrganizationStatus $status = null,
        int $perPage = 20,
    ): LengthAwarePaginator;

    /**
     * Crea una nuova organizzazione.
     */
    public function create(array $data): Organization;

    /**
     * Aggiorna un'organizzazione esistente.
     */
    public function update(Organization $organization, array $data): Organization;

    /**
     * Elimina un'organizzazione (soft delete).
     */
    public function delete(Organization $organization): bool;

    /**
     * Genera uno slug unico a partire dal nome.
     */
    public function generateUniqueSlug(string $name): string;
}
