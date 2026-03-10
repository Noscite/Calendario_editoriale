<?php

declare(strict_types=1);

namespace App\Domain\Document\Repositories;

use App\Domain\Document\Contracts\DocumentRepositoryInterface;
use App\Domain\Document\Models\BrandDocument;
use Illuminate\Database\Eloquent\Collection;

final class DocumentRepository implements DocumentRepositoryInterface
{
    public function __construct(
        private readonly BrandDocument $model,
    ) {}

    public function findByBrand(int $brandId): Collection
    {
        return $this->model
            ->where('brand_id', $brandId)
            ->orderByDesc('uploaded_at')
            ->get();
    }

    public function findByBrandWithChunkCount(int $brandId): Collection
    {
        return $this->model
            ->where('brand_id', $brandId)
            ->withCount('chunks')
            ->orderByDesc('uploaded_at')
            ->get();
    }

    public function findById(int $id): ?BrandDocument
    {
        return $this->model->find($id);
    }

    public function findOrFail(int $id): BrandDocument
    {
        return $this->model->findOrFail($id);
    }

    public function create(array $data): BrandDocument
    {
        return $this->model->create($data);
    }

    public function update(BrandDocument $document, array $data): BrandDocument
    {
        $document->update($data);

        return $document->refresh();
    }

    public function delete(BrandDocument $document): bool
    {
        $document->chunks()->delete();

        return (bool) $document->delete();
    }

    public function resetForReprocessing(BrandDocument $document): BrandDocument
    {
        $document->chunks()->delete();

        $document->update([
            'extraction_status' => 'pending',
            'analysis_status' => 'pending',
            'summary' => null,
            'key_topics' => null,
        ]);

        return $document->refresh();
    }
}
