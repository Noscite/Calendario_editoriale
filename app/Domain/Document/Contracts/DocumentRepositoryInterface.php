<?php

declare(strict_types=1);

namespace App\Domain\Document\Contracts;

use App\Domain\Document\Models\BrandDocument;
use Illuminate\Database\Eloquent\Collection;

interface DocumentRepositoryInterface
{
    /**
     * Trova tutti i documenti di un brand, ordinati per data upload desc.
     */
    public function findByBrand(int $brandId): Collection;

    /**
     * Trova tutti i documenti di un brand con conteggio chunk.
     */
    public function findByBrandWithChunkCount(int $brandId): Collection;

    /**
     * Trova un documento per ID.
     */
    public function findById(int $id): ?BrandDocument;

    /**
     * Trova un documento per ID o lancia eccezione.
     */
    public function findOrFail(int $id): BrandDocument;

    /**
     * Crea un nuovo record documento.
     */
    public function create(array $data): BrandDocument;

    /**
     * Aggiorna un documento.
     */
    public function update(BrandDocument $document, array $data): BrandDocument;

    /**
     * Elimina un documento e i suoi chunk.
     */
    public function delete(BrandDocument $document): bool;

    /**
     * Resetta lo stato di processing e rimuove i chunk per riprocessamento.
     */
    public function resetForReprocessing(BrandDocument $document): BrandDocument;
}
