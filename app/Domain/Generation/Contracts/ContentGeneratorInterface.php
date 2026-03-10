<?php

declare(strict_types=1);

namespace App\Domain\Generation\Contracts;

use App\Domain\Post\Models\Post;
use App\Domain\Project\Models\Project;
use Illuminate\Database\Eloquent\Collection;

interface ContentGeneratorInterface
{
    /**
     * Genera un piano editoriale completo (batch di post) per un progetto.
     * La generazione avviene in background tramite job/thread.
     */
    public function generateCalendar(Project $project): void;

    /**
     * Genera post AI per un progetto con parametri personalizzati.
     *
     * @param  array{
     *     platforms?: array<string>,
     *     ai_decide_platforms?: bool,
     *     start_date?: string,
     *     end_date?: string,
     *     num_posts?: int,
     *     ai_decide_num_posts?: bool,
     *     brief?: string,
     *     pillar?: string,
     * }  $params
     */
    public function generateAiPosts(int $projectId, array $params): Collection;

    /**
     * Rigenera il contenuto di un singolo post con prompt opzionale.
     */
    public function regeneratePost(int $postId, ?string $userPrompt = null): Post;

    /**
     * Genera le buyer personas per un progetto.
     */
    public function generatePersonas(int $projectId): array;

    /**
     * Rigenera le buyer personas con feedback utente.
     */
    public function regeneratePersonas(int $projectId, ?string $feedback = null): array;

    /**
     * Conferma le buyer personas (con possibilità di modifica manuale).
     */
    public function confirmPersonas(int $projectId, ?array $personas = null): array;

    /**
     * Ottieni le buyer personas correnti di un progetto.
     */
    public function getPersonas(int $projectId): array;

    /**
     * Aggiungi una singola buyer persona al progetto.
     */
    public function addPersona(int $projectId, ?string $description = null): array;

    /**
     * Rimuovi una buyer persona per indice.
     */
    public function deletePersona(int $projectId, int $personaIndex): array;

    /**
     * Rigenera una singola buyer persona per indice.
     */
    public function regenerateSinglePersona(int $projectId, int $personaIndex, ?string $description = null): array;

    /**
     * Ottieni lo stato della generazione in corso.
     *
     * @return array{
     *     status: string,
     *     post_count: int,
     *     percent: int,
     *     current_batch: int,
     *     total_batches: int,
     * }
     */
    public function getGenerationStatus(int $projectId): array;
}
