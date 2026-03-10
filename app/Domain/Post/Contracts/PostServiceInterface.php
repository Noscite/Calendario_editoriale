<?php

declare(strict_types=1);

namespace App\Domain\Post\Contracts;

use App\Domain\Post\Models\Post;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;

interface PostServiceInterface
{
    /**
     * Elenca tutti i post di un progetto, ordinati per data/ora.
     */
    public function listByProject(int $projectId): Collection;

    /**
     * Ottieni un post per ID.
     */
    public function getById(int $id): Post;

    /**
     * Crea un post manuale.
     */
    public function create(array $data): Post;

    /**
     * Aggiorna un post esistente (aggiornamento parziale).
     */
    public function update(int $postId, array $data): Post;

    /**
     * Elimina un post.
     */
    public function delete(int $postId): void;

    /**
     * Elimina multipli post per ID.
     *
     * @return int Numero di post eliminati.
     */
    public function batchDelete(array $postIds): int;

    /**
     * Rigenera multipli post con AI (batch replace).
     */
    public function batchReplace(array $postIds, ?string $brief = null): Collection;

    /**
     * Rigenera il contenuto di un singolo post.
     */
    public function regenerate(int $postId, ?string $prompt = null): Post;

    /**
     * Programma un post per la pubblicazione automatica.
     *
     * @param  array<string>  $platforms  Piattaforme target.
     */
    public function schedule(int $postId, \DateTimeInterface $scheduledFor, array $platforms): Post;

    /**
     * Annulla la programmazione di un post.
     */
    public function cancelSchedule(int $postId): Post;

    /**
     * Carica un media (immagine/video) per un post.
     */
    public function uploadMedia(int $postId, UploadedFile $file): Post;

    /**
     * Ottieni lo stato di pubblicazione di un post.
     */
    public function getScheduleStatus(int $postId): array;

    /**
     * Verifica sovrapposizioni in un intervallo di date.
     */
    public function checkOverlap(int $projectId, string $startDate, string $endDate): array;
}
