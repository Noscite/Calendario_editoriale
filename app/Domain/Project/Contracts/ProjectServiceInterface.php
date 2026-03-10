<?php

declare(strict_types=1);

namespace App\Domain\Project\Contracts;

use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Project\Models\Project;
use Illuminate\Database\Eloquent\Collection;

interface ProjectServiceInterface
{
    /**
     * Elenca tutti i progetti di un brand.
     */
    public function listByBrand(int $brandId): Collection;

    /**
     * Ottieni un progetto per ID.
     */
    public function getById(int $id): Project;

    /**
     * Crea un nuovo progetto per un brand.
     */
    public function create(array $data): Project;

    /**
     * Aggiorna un progetto esistente (aggiornamento parziale).
     */
    public function update(int $projectId, array $data): Project;

    /**
     * Aggiorna lo stato di un progetto.
     */
    public function updateStatus(int $projectId, ProjectStatus $status): Project;

    /**
     * Elimina un progetto e tutti i post associati.
     */
    public function delete(int $projectId): void;

    /**
     * Duplica un progetto (senza post).
     */
    public function duplicate(int $projectId): Project;

    /**
     * Esporta un progetto in formato Excel.
     *
     * @return string Percorso del file generato.
     */
    public function exportToExcel(int $projectId): string;
}
