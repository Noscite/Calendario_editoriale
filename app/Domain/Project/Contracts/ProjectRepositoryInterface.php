<?php

declare(strict_types=1);

namespace App\Domain\Project\Contracts;

use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Project\Models\Project;
use Illuminate\Database\Eloquent\Collection;

interface ProjectRepositoryInterface
{
    /**
     * Trova tutti i progetti di un brand.
     */
    public function findByBrand(int $brandId): Collection;

    /**
     * Trova un progetto per ID.
     */
    public function findById(int $id): ?Project;

    /**
     * Trova un progetto per ID o lancia eccezione.
     */
    public function findOrFail(int $id): Project;

    /**
     * Crea un nuovo progetto.
     */
    public function create(array $data): Project;

    /**
     * Aggiorna un progetto esistente.
     */
    public function update(Project $project, array $data): Project;

    /**
     * Aggiorna lo stato di un progetto.
     */
    public function updateStatus(Project $project, ProjectStatus $status): Project;

    /**
     * Elimina un progetto.
     */
    public function delete(Project $project): bool;

    /**
     * Trova progetti per stato.
     */
    public function findByStatus(ProjectStatus $status): Collection;
}
