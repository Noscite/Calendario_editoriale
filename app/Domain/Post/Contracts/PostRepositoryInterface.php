<?php

declare(strict_types=1);

namespace App\Domain\Post\Contracts;

use App\Domain\Post\Enums\Platform;
use App\Domain\Post\Enums\PublicationStatus;
use App\Domain\Post\Models\Post;
use Illuminate\Database\Eloquent\Collection;

interface PostRepositoryInterface
{
    /**
     * Trova tutti i post di un progetto, ordinati per data e ora.
     */
    public function findByProject(int $projectId): Collection;

    /**
     * Trova un post per ID.
     */
    public function findById(int $id): ?Post;

    /**
     * Trova un post per ID o lancia eccezione.
     */
    public function findOrFail(int $id): Post;

    /**
     * Crea un nuovo post.
     */
    public function create(array $data): Post;

    /**
     * Inserisci multipli post in bulk.
     */
    public function createMany(array $posts): Collection;

    /**
     * Aggiorna un post esistente.
     */
    public function update(Post $post, array $data): Post;

    /**
     * Elimina un post.
     */
    public function delete(Post $post): bool;

    /**
     * Elimina multipli post per ID.
     */
    public function deleteMany(array $postIds): int;

    /**
     * Trova post programmati per la pubblicazione.
     */
    public function findScheduledForPublishing(): Collection;

    /**
     * Trova post in un intervallo di date per un progetto.
     */
    public function findByProjectAndDateRange(
        int $projectId,
        string $startDate,
        string $endDate,
    ): Collection;

    /**
     * Conta i post sovrapposti in un intervallo di date.
     */
    public function countOverlapping(
        int $projectId,
        string $startDate,
        string $endDate,
    ): int;
}
