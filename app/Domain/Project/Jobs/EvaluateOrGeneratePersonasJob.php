<?php

declare(strict_types=1);

namespace App\Domain\Project\Jobs;

use App\Domain\Brand\Models\Brand;
use App\Domain\Document\Contracts\OpenAiEmbeddingClientInterface;
use App\Domain\Generation\Contracts\ContentGeneratorInterface;
use App\Domain\Generation\Services\PersonasEvaluationTracker;
use App\Domain\Project\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * EvaluateOrGeneratePersonasJob — wizard PR-2.
 *
 * Valuta se le personas storiche di altri Project dello stesso brand
 * sono adatte al brief del nuovo project, o se conviene generarne nuove.
 *
 * Flusso:
 *  1. forceGenerateNew=true → bypass diretto a generate_new
 *  2. 0 Project storici con personas → generate_new
 *  3. ≥1 Project storici:
 *     a) embed brief (corrente + storici), cosine similarity → top 3
 *     b) Sonnet evaluate → verdict in {reuse, adapt, regenerate}
 *     c) branch:
 *        - reuse      → copia personas, source = "reused_from:{id}"
 *        - adapt      → 2nd Sonnet call rifinisce, source = "adapted_from:{id}"
 *        - regenerate → ClaudeContentGenerator::generatePersonas, source = "generated_new"
 *  4. Snapshot completo della raccomandazione in personas_ai_suggestion
 *
 * Stato per polling: PersonasEvaluationTracker (Redis).
 *
 * Failure: ogni errore lascia il project con buyer_personas eventualmente
 * popolate in modo parziale; il tracker passa a 'failed'. L'utente può
 * sempre dispatchare di nuovo via force-regenerate.
 */
final class EvaluateOrGeneratePersonasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $maxExceptions = 1;
    public int $timeout = 90;

    /** Numero massimo di project storici considerati come candidati pre-similarity. */
    private const MAX_CANDIDATES_PRE_SIM = 20;

    /** Top N candidati passati al prompt di valutazione. */
    private const TOP_N_FOR_PROMPT = 3;

    public function __construct(
        private readonly int  $projectId,
        private readonly bool $forceGenerateNew = false,
    ) {
        $this->onQueue('generazione');
    }

    public function handle(
        ContentGeneratorInterface $generator,
        OpenAiEmbeddingClientInterface $embedClient,
    ): void {
        PersonasEvaluationTracker::setEvaluating($this->projectId);

        $project = Project::with('brand')->find($this->projectId);
        if (! $project || ! $project->brand) {
            PersonasEvaluationTracker::setFailed($this->projectId, 'project_or_brand_not_found');
            Log::warning("[PERSONAS-JOB] Project {$this->projectId} or brand not found");
            return;
        }

        $brand    = $project->brand;
        $newBrief = (string) ($project->brief ?? '');

        try {
            $generator->useBrandKeys($brand, $this->projectId);
            $embedClient = $embedClient->withBrand($brand);

            // Force = bypass evaluation, vai diretto a generate_new
            if ($this->forceGenerateNew || $newBrief === '') {
                $this->runGenerateNew($project, $brand, $generator, reason: $this->forceGenerateNew
                    ? 'Rigenerazione forzata dall\'utente.'
                    : 'Brief vuoto, fallback a generazione standard.');
                PersonasEvaluationTracker::setReady($this->projectId);
                return;
            }

            // Carica candidati storici
            $candidates = $this->loadHistoricalCandidates($project);

            if ($candidates->isEmpty()) {
                $this->runGenerateNew($project, $brand, $generator, reason: 'Primo project del brand con personas.');
                PersonasEvaluationTracker::setReady($this->projectId);
                return;
            }

            // Embedding similarity
            $topCandidates = $this->selectTopBySimilarity($newBrief, $candidates, $embedClient);

            // Sonnet evaluate fit
            $suggestion = $generator->evaluatePersonasFit($brand, $newBrief, $topCandidates);

            $this->applyVerdict($project, $brand, $newBrief, $suggestion, $topCandidates, $generator);

            PersonasEvaluationTracker::setReady($this->projectId);
        } catch (\Throwable $e) {
            Log::error("[PERSONAS-JOB] Project {$this->projectId} evaluation failed: {$e->getMessage()}");
            // Fallback finale: prova generate_new se non già fatto
            try {
                $this->runGenerateNew($project, $brand, $generator, reason: 'Errore interno: ' . $e->getMessage());
                PersonasEvaluationTracker::setReady($this->projectId);
            } catch (\Throwable $inner) {
                Log::error("[PERSONAS-JOB] Project {$this->projectId} final fallback also failed: {$inner->getMessage()}");
                PersonasEvaluationTracker::setFailed($this->projectId, $inner->getMessage());
            }
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('[PERSONAS-JOB] failed() invoked', [
            'project_id' => $this->projectId,
            'exception'  => $exception?->getMessage(),
        ]);
        PersonasEvaluationTracker::setFailed($this->projectId, $exception?->getMessage() ?? 'job_failed');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Project>
     */
    private function loadHistoricalCandidates(Project $project): \Illuminate\Database\Eloquent\Collection
    {
        return Project::withoutGlobalScope('organization')
            ->where('brand_id', $project->brand_id)
            ->where('id', '!=', $project->id)
            ->whereNotNull('buyer_personas')
            ->whereNotNull('brief')
            ->orderByDesc('created_at')
            ->limit(self::MAX_CANDIDATES_PRE_SIM)
            ->get(['id', 'name', 'brief', 'buyer_personas']);
    }

    /**
     * Embed corrente + storici, ranka per cosine sim, ritorna top N con shape
     * compatibile con buildPersonasEvaluationPrompt.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Project>  $candidates
     * @return array<int, array{project_id: int, name: string, brief: string, personas: array, similarity: float}>
     */
    private function selectTopBySimilarity(
        string $newBrief,
        \Illuminate\Database\Eloquent\Collection $candidates,
        OpenAiEmbeddingClientInterface $embedClient,
    ): array {
        $briefs = [$newBrief];
        foreach ($candidates as $c) {
            $briefs[] = (string) ($c->brief ?? '');
        }

        $vectors = $embedClient->embed($briefs);
        if (count($vectors) !== count($briefs) || empty($vectors[0])) {
            // Embedding non riuscito → ranking neutrale (ordine cronologico)
            Log::warning('[PERSONAS-JOB] Embedding shape mismatch, using chronological order');
            $items = [];
            foreach ($candidates->take(self::TOP_N_FOR_PROMPT) as $c) {
                $items[] = [
                    'project_id' => $c->id,
                    'name'       => (string) $c->name,
                    'brief'      => (string) $c->brief,
                    'personas'   => is_array($c->buyer_personas) ? $c->buyer_personas : [],
                    'similarity' => 0.0,
                ];
            }
            return $items;
        }

        $newVec = $vectors[0];
        $scored = [];
        foreach ($candidates as $idx => $c) {
            $scored[] = [
                'candidate'  => $c,
                'similarity' => self::cosineSimilarity($newVec, $vectors[$idx + 1] ?? []),
            ];
        }

        usort($scored, static fn ($a, $b) => $b['similarity'] <=> $a['similarity']);
        $scored = array_slice($scored, 0, self::TOP_N_FOR_PROMPT);

        return array_map(static fn (array $row): array => [
            'project_id' => $row['candidate']->id,
            'name'       => (string) $row['candidate']->name,
            'brief'      => (string) $row['candidate']->brief,
            'personas'   => is_array($row['candidate']->buyer_personas) ? $row['candidate']->buyer_personas : [],
            'similarity' => round((float) $row['similarity'], 4),
        ], $scored);
    }

    /**
     * @param  array<int, array{project_id: int, name: string, brief: string, personas: array, similarity: float}>  $topCandidates
     */
    private function applyVerdict(
        Project $project,
        Brand $brand,
        string $newBrief,
        array $suggestion,
        array $topCandidates,
        ContentGeneratorInterface $generator,
    ): void {
        $verdict  = $suggestion['verdict'];
        $sourceId = $suggestion['source_project_id'] ?? null;

        $sourceCandidate = null;
        if ($sourceId !== null) {
            foreach ($topCandidates as $cand) {
                if ($cand['project_id'] === $sourceId) {
                    $sourceCandidate = $cand;
                    break;
                }
            }
        }

        if (in_array($verdict, ['reuse', 'adapt'], true) && $sourceCandidate === null) {
            // Sonnet ha indicato un sourceId fuori dai top → degrade a regenerate
            Log::warning('[PERSONAS-JOB] Verdict ' . $verdict . ' senza source candidate valido, downgrade a regenerate', [
                'project_id'        => $project->id,
                'source_project_id' => $sourceId,
            ]);
            $verdict = 'regenerate';
        }

        $personasSnapshot = $this->buildSuggestionSnapshot($suggestion, $topCandidates);

        if ($verdict === 'reuse') {
            $copied = $sourceCandidate['personas'];
            $copied['generated_at'] = now()->toIso8601String();
            $copied['source']       = 'reused';
            $copied['confirmed']    = false;
            $project->update([
                'buyer_personas'         => $copied,
                'personas_source'        => 'reused_from:' . $sourceCandidate['project_id'],
                'personas_ai_suggestion' => $personasSnapshot,
            ]);
            return;
        }

        if ($verdict === 'adapt') {
            $adapted = $generator->adaptPersonas($brand, $newBrief, $sourceCandidate['personas']);
            $adapted['confirmed'] = false;
            $project->update([
                'buyer_personas'         => $adapted,
                'personas_source'        => 'adapted_from:' . $sourceCandidate['project_id'],
                'personas_ai_suggestion' => $personasSnapshot,
            ]);
            return;
        }

        // regenerate / generate_new
        $this->runGenerateNew($project, $brand, $generator, reason: $suggestion['reasoning'] ?: 'Verdict regenerate.');
        // Sovrascrivi snapshot con verdict reale (regenerate)
        $project->update(['personas_ai_suggestion' => $personasSnapshot]);
    }

    /**
     * Genera personas nuove con il path esistente (sincrono) e segna
     * personas_source = "generated_new".
     */
    private function runGenerateNew(
        Project $project,
        Brand $brand,
        ContentGeneratorInterface $generator,
        string $reason,
    ): void {
        // generatePersonas() persiste già personas su project.buyer_personas
        $personas = $generator->generatePersonas($project->id);
        $personas['confirmed'] = false;

        $project->update([
            'buyer_personas'         => $personas,
            'personas_source'        => 'generated_new',
            'personas_ai_suggestion' => [
                'verdict'           => 'generate_new',
                'source_project_id' => null,
                'reasoning'         => $reason,
                'confidence'        => 1.0,
                'evaluated_at'      => now()->toIso8601String(),
                'top_candidates'    => [],
            ],
        ]);
    }

    /**
     * @param  array<int, array{project_id: int, name: string, similarity: float}>  $topCandidates
     */
    private function buildSuggestionSnapshot(array $suggestion, array $topCandidates): array
    {
        return [
            'verdict'           => $suggestion['verdict'],
            'source_project_id' => $suggestion['source_project_id'] ?? null,
            'reasoning'         => $suggestion['reasoning'] ?? '',
            'confidence'        => $suggestion['confidence'] ?? 0.0,
            'evaluated_at'      => now()->toIso8601String(),
            'top_candidates'    => array_map(static fn (array $c): array => [
                'project_id' => $c['project_id'],
                'name'       => $c['name'],
                'similarity' => $c['similarity'],
            ], $topCandidates),
        ];
    }

    /**
     * Cosine similarity tra due vettori float della stessa dimensione.
     */
    private static function cosineSimilarity(array $a, array $b): float
    {
        if (empty($a) || empty($b) || count($a) !== count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $na  = 0.0;
        $nb  = 0.0;
        $len = count($a);
        for ($i = 0; $i < $len; $i++) {
            $av = (float) $a[$i];
            $bv = (float) $b[$i];
            $dot += $av * $bv;
            $na  += $av * $av;
            $nb  += $bv * $bv;
        }

        if ($na === 0.0 || $nb === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($na) * sqrt($nb));
    }
}
