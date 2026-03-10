<?php

declare(strict_types=1);

namespace App\Domain\Brand\Contracts;

use App\Domain\Brand\Models\Brand;
use Illuminate\Database\Eloquent\Collection;

interface BrandRepositoryInterface
{
    /**
     * Trova tutti i brand di un'organizzazione.
     */
    public function findByOrganization(int $organizationId): Collection;

    /**
     * Trova tutti i brand di un'organizzazione con conteggi progetti e post.
     */
    public function findByOrganizationWithCounts(int $organizationId): Collection;

    /**
     * Trova un brand per ID.
     */
    public function findById(int $id): ?Brand;

    /**
     * Trova un brand per ID o lancia eccezione.
     */
    public function findOrFail(int $id): Brand;

    /**
     * Crea un nuovo brand.
     */
    public function create(array $data): Brand;

    /**
     * Aggiorna un brand esistente.
     */
    public function update(Brand $brand, array $data): Brand;

    /**
     * Elimina un brand.
     */
    public function delete(Brand $brand): bool;
}
