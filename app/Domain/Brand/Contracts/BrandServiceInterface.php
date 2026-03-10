<?php

declare(strict_types=1);

namespace App\Domain\Brand\Contracts;

use App\Domain\Brand\Models\Brand;
use Illuminate\Database\Eloquent\Collection;

interface BrandServiceInterface
{
    /**
     * Elenca tutti i brand dell'organizzazione con conteggi.
     */
    public function listForOrganization(int $organizationId): Collection;

    /**
     * Ottieni un brand per ID.
     */
    public function getById(int $id): Brand;

    /**
     * Crea un nuovo brand per un'organizzazione.
     */
    public function create(int $organizationId, array $data): Brand;

    /**
     * Aggiorna un brand esistente (aggiornamento parziale).
     */
    public function update(int $brandId, array $data): Brand;

    /**
     * Elimina un brand e tutte le risorse associate.
     */
    public function delete(int $brandId): void;
}
