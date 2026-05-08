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

    /**
     * Aggiunge nuovi pillar al set default_content_pillars del brand.
     *
     * Idempotente (dedup case-insensitive via PillarNameNormalizer), FIFO su
     * overflow del massimo (6 pillar). Non sovrascrive le description di pillar
     * preesistenti — pillar con stesso name normalizzato sono saltati.
     *
     * @param  array<int, array{name: string, description?: string}>  $newPillars
     * @return array{
     *   pillars: array<int, array{name: string, description: string}>,
     *   added_count: int,
     *   dropped_count: int,
     *   skipped_duplicates: int
     * }
     */
    public function mergeDefaultPillars(Brand $brand, array $newPillars): array;
}
