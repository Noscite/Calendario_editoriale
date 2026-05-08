<?php

declare(strict_types=1);

namespace App\Domain\Generation\Contracts;

use App\Domain\Brand\Models\Brand;
use App\Domain\Post\Models\Post;
use App\Domain\Project\Models\Project;
use Illuminate\Database\Eloquent\Collection;

interface ContentGeneratorInterface
{
    /**
     * Configura i client AI per usare le chiavi API del brand corrente.
     * Chiamato dai Job prima di avviare le chiamate Anthropic/OpenAI.
     */
    public function useBrandKeys(Brand $brand): void;

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

    // ── Wizard PR-2: AI personas evaluation ────────────────────

    /**
     * Valuta il fit di personas storiche su un nuovo brief via Sonnet.
     *
     * @param  array<int, array{project_id: int, name: string, brief: string, personas: array, similarity: float}>  $candidates
     * @return array{verdict: string, source_project_id: int|null, reasoning: string, confidence: float}
     */
    public function evaluatePersonasFit(Brand $brand, string $newBrief, array $candidates): array;

    /**
     * Adatta personas esistenti a un nuovo brief via Sonnet (verdict='adapt').
     *
     * @return array  Stessa shape di buyer_personas (personas + scheduling_strategy + ...)
     */
    public function adaptPersonas(Brand $brand, string $newBrief, array $sourcePersonas): array;
}
