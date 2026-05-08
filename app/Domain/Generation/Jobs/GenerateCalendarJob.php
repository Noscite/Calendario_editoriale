<?php

declare(strict_types=1);

namespace App\Domain\Generation\Jobs;

use App\Domain\Brand\Exceptions\MissingBrandApiKeyException;
use App\Domain\Brand\Models\Brand;
use App\Domain\Generation\Contracts\ContentGeneratorInterface;
use App\Domain\Generation\Services\GenerationTracker;
use App\Domain\Notification\Models\Notification;
use App\Domain\Subscription\Models\UsageLog;
use App\Domain\Post\Models\Post;
use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Project\Models\Project;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GenerateCalendarJob — replica esatta di run_generation() da generation.py.
 *
 * Flusso (identico al Python):
 *  1. Carica project + brand
 *  2. Recupera buyer_personas (o default)
 *  3. Recupera url_context (se brand ha reference_urls / website_url)
 *  4. Prepara brand_info + project_info (stessi campi del Python)
 *  5. Chiama ContentGeneratorInterface::generateCalendarPosts()
 *     (che internamente divide in batch da 7 giorni con sleep(8) tra batch)
 *  6. Cancella post esistenti nel range date del progetto
 *  7. Salva nuovi post singolarmente (commit come il Python)
 *  8. Tracka usage (calendars + tokens)
 *  9. Aggiorna status → review
 *
 * In caso di errore: status → draft (vedi failed()).
 *
 * Il Python usa threading.Thread — Laravel usa Jobs + Horizon.
 */
final class GenerateCalendarJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 0;
    public int $maxExceptions = 1;

    /**
     * Timeout in secondi (30 minuti — generazioni grandi con molti batch richiedono tempo).
     */
    public int $timeout = 1800;

    public function __construct(
        private readonly int $projectId,
        private readonly string $historyContext = '',
    ) {
        $this->onQueue('generazione');
    }

    // ──────────────────────────────────────────────────────────
    //  handle() — replica di run_generation()
    // ──────────────────────────────────────────────────────────

    public function handle(ContentGeneratorInterface $generator): void
    {
        $project = Project::find($this->projectId);
        if (! $project) {
            Log::info("[GEN] Project {$this->projectId} not found");
            return;
        }

        $brand = Brand::find($project->brand_id);
        if (! $brand) {
            Log::info("[GEN] Brand not found for project {$this->projectId}");
            return;
        }

        try {
            $this->runGeneration($generator, $project, $brand);
        } catch (MissingBrandApiKeyException $e) {
            Log::warning('[GEN] Generazione bloccata: chiave API mancante', [
                'project_id' => $this->projectId,
                'brand_id'   => $brand->id,
                'brand_name' => $brand->name,
                'key_name'   => $e->keyName,
            ]);

            // Notifica in-app all'organizzazione del brand
            try {
                Notification::create([
                    'organization_id' => $brand->organization_id,
                    'type'            => 'missing_api_key',
                    'title'           => "Generazione bloccata — chiave API mancante",
                    'message'         => $e->getMessage(),
                    'brand_id'        => $brand->id,
                    'project_id'      => $this->projectId,
                ]);
            } catch (\Throwable) {}

            $project->update(['status' => ProjectStatus::Draft]);
            GenerationTracker::clear($this->projectId);

            // Nessun retry — finché la chiave manca non ha senso
            $this->fail($e);
        }
    }

    private function runGeneration(ContentGeneratorInterface $generator, Project $project, Brand $brand): void
    {
        // Ri-imposta status a generating (potrebbe essere tornato a draft dopo un retry)
        if ($project->status !== ProjectStatus::Generating) {
            $project->update(['status' => ProjectStatus::Generating]);
        }

        // Configura i client AI per usare le chiavi del brand (lancia MissingBrandApiKeyException se assenti)
        $generator->useBrandKeys($brand);

        Log::info("[GEN] Starting generation for project {$this->projectId} — Brand: {$brand->name}");

        // ── 1. Recupera buyer personas (devono essere già generate/confermate) ──
        $buyerPersonas = $project->buyer_personas;
        if (empty($buyerPersonas)) {
            Log::info('[GEN] No personas found, using defaults');
            $buyerPersonas = $this->getDefaultPersonas($project->platforms ?? []);
        }

        // ── 2. Analizza URL se servono contesto aggiuntivo ──
        $urlContext    = '';
        $referenceUrls = $project->reference_urls ?? [];
        if (! empty($referenceUrls)) {
            Log::info('[GEN] Analyzing ' . count($referenceUrls) . ' reference URLs...');
            try {
                $urlContext = $this->fetchUrlContext($referenceUrls, $brand->name);
                Log::info('[GEN] URL context: ' . strlen($urlContext) . ' chars');
            } catch (\Throwable $e) {
                Log::info("[GEN] URL analysis error: {$e->getMessage()}");
            }
        }

        // ── 3. Prepara posts_per_week (stessa logica Python) ──
        $postsPerWeek = [];
        foreach ($project->platforms ?? [] as $platform) {
            $postsPerWeek[$platform] = ($project->posts_per_week[$platform] ?? null)
                ? (int) $project->posts_per_week[$platform]
                : 2;
        }

        // ── 4. Prepara brand_info e project_info (stessi campi del Python) ──
        // NB: tenuto coerente con ClaudeContentGenerator::brandInfoArray() per
        // evitare drift tra path legacy e path API generateAiPosts.
        $brandInfo = [
            'sector'                  => $brand->sector,
            'description'             => $brand->description,
            'target_audience'         => $brand->target_audience,
            'unique_selling_points'   => $brand->unique_selling_points,
            'brand_values'            => $brand->brand_values,
            'tone_of_voice'           => $brand->tone_of_voice,
            'style_guide'             => $brand->style_guide,
            // ── Wizard PR-1: fonti citabili strutturate ──────────────
            'tagline'                 => $brand->tagline,
            'founder'                 => $brand->founder ?? null,
            'narrative_assets'        => $brand->narrative_assets ?? [],
            'default_content_pillars' => $brand->default_content_pillars ?? [],
            'forbidden_topics'        => $brand->forbidden_topics ?? [],
        ];

        $projectInfo = [
            'brief'           => $project->brief,
            'target_audience' => $project->target_audience,
            'custom_prompt'   => $project->custom_prompt,
            'objectives'      => $project->objectives ?? [],
            'history_context' => $this->historyContext,
            // Propagati per buildBrandAssetsSection (anti-invenzione):
            // special_dates → fonte di aneddoti reali citabili
            // competitors  → sotto-sezione "ASSET DA NON CITARE"
            'special_dates'   => $project->special_dates ?? [],
            'competitors'     => $project->competitors ?? [],
        ];

        // Defensive normalization: il primo rollout di EditProjectWizardV2 (PR-WIZARD-2)
        // ha temporaneamente salvato content_pillars come array di {name, description}
        // objects invece di strings. Questo helper auto-cura entrambi gli shape, così
        // matchPillar e PromptBuilder ricevono sempre list<string>.
        $themes = Project::normalizeContentPillarsList($project->content_pillars);
        if (empty($themes)) {
            $themes = $project->themes ?? [];
        }

        // ── 5. Genera con Claude (batch + rate-limit + sleep) ──
        [$posts, $updatedPersonas, $totalTokensUsed] = $generator->generateCalendarPosts(
            brandName:     $brand->name,
            brandInfo:     $brandInfo,
            projectInfo:   $projectInfo,
            startDate:     Carbon::parse($project->start_date),
            endDate:       Carbon::parse($project->end_date),
            platforms:     $project->platforms ?? [],
            postsPerWeek:  $postsPerWeek,
            themes:        $themes,
            urlContext:     $urlContext,
            styleGuide:    $brand->style_guide,
            buyerPersonas: $buyerPersonas,
            brandId:       $brand->id,
            projectId:     $this->projectId,
        );

        Log::info("[GEN] Claude returned " . count($posts) . " posts");

        // ── 6. Cancella post nel range date del progetto (come fa il Python) ──
        $deleted = Post::where('project_id', $this->projectId)
            ->where('scheduled_date', '>=', $project->start_date)
            ->where('scheduled_date', '<=', $project->end_date)
            ->delete();

        Log::info("[GEN] Deleted {$deleted} future posts (kept past posts)");

        // ── 7. Salva nuovi post — bulk insert (1 query invece di N) ──
        // NOTA: Post::insert() bypassa i Model casts. La costruzione delle
        // righe (incluso json_encode di hashtags e generation_metadata) è
        // centralizzata in ClaudeContentGenerator::buildPostRow($forBulkInsert=true).
        if (! empty($posts)) {
            $contentPillars = $project->content_pillars ?? $project->themes ?? [];
            $organizationId = $project->organization_id;
            // Risolvi git_commit una sola volta per evitare N shell_exec.
            $gitCommit = $generator->getCurrentGitCommit();

            $bulkRows = array_map(
                fn (array $raw): array => $generator->buildPostRow(
                    $raw,
                    $this->projectId,
                    $organizationId,
                    $contentPillars,
                    $gitCommit,
                    forBulkInsert: true,
                ),
                $posts,
            );

            // Inserisci in chunk da 100 per sicurezza su VPS con poca RAM
            foreach (array_chunk($bulkRows, 100) as $chunk) {
                Post::insert($chunk);
            }
        }

        // ── 8. Aggiorna personas se rigenerate ──
        if (! empty($updatedPersonas)) {
            $project->update(['buyer_personas' => $updatedPersonas]);
        }

        // ── 9. Usage tracking (calendars=1 + tokens) ──
        $this->incrementUsage($brand->organization_id, $totalTokensUsed);

        // ── 10. Status → review ──
        $project->update(['status' => ProjectStatus::Review]);
        GenerationTracker::clear($this->projectId);

        Log::info("[GEN] ✅ Saved " . count($posts) . " posts, status set to review");
    }

    // ──────────────────────────────────────────────────────────
    //  failed() — resetta status a draft (come il Python)
    // ──────────────────────────────────────────────────────────
    // Nota: MissingBrandApiKeyException chiama $this->fail() esplicitamente
    // quindi failed() viene invocato — il reset a draft è già fatto nel catch.

    public function failed(?\Throwable $exception): void
    {
        if ($exception && function_exists('\Sentry\captureException')) {
            \Sentry\captureException($exception);
        }

        Log::error("[GEN] ❌ Error: " . ($exception?->getMessage() ?? 'unknown'));

        try {
            $project = Project::find($this->projectId);
            if ($project) {
                $project->update(['status' => ProjectStatus::Draft]);
            }
        } catch (\Throwable) {
            // Silenzioso — non si può fare altro
        }

        GenerationTracker::clear($this->projectId);
    }

    // ══════════════════════════════════════════════════════════
    //  Private helpers
    // ══════════════════════════════════════════════════════════

    /**
     * Incrementa contatori usage.
     * Replica di increment_usage() da subscriptions.py.
     */
    private function incrementUsage(int $organizationId, int $tokens): void
    {
        try {
            $periodStart = now()->startOfMonth()->toDateString();
            $periodEnd   = now()->endOfMonth()->toDateString();

            $usage = UsageLog::firstOrCreate(
                [
                    'organization_id' => $organizationId,
                    'period_start'    => $periodStart,
                ],
                [
                    'period_end' => $periodEnd,
                ]
            );

            // calendars=1 + tokens
            $usage->increment('calendar_generations_used', 1);
            if ($tokens > 0) {
                $usage->increment('text_tokens_used', $tokens);
            }

            Log::info("[GEN] Usage tracked: org={$organizationId}, tokens={$tokens}, calendars=1");
        } catch (\Throwable $e) {
            Log::error("[GEN] Usage tracking error: {$e->getMessage()}");
        }
    }

    /**
     * Fetch URL context — replica di get_brand_context_from_urls().
     *
     * Usa HTTP per scaricare il contenuto delle URL di riferimento e
     * restituisce il testo concatenato (versione semplificata; la versione
     * completa con analisi Claude è in UrlAnalyzerService).
     */
    private function fetchUrlContext(array $urls, string $brandName): string
    {
        $targets  = array_slice($urls, 0, 5);
        $contents = [];

        if (empty($targets)) {
            return '';
        }

        try {
            // Http::pool() lancia tutte le richieste in parallelo (Guzzle Promise)
            // Timeout ridotto a 8s: se un sito è lento non blocca tutto
            $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($targets): array {
                return collect($targets)->mapWithKeys(function (string $url) use ($pool): array {
                    return [$url => $pool->as($url)->timeout(8)->withUserAgent('NosciteCalendar/1.0')->get($url)];
                })->toArray();
            });

            foreach ($targets as $url) {
                try {
                    $response = $responses[$url] ?? null;
                    if ($response instanceof \Illuminate\Http\Client\Response && $response->successful()) {
                        $text = strip_tags($response->body());
                        $text = trim(mb_substr(preg_replace('/\s+/', ' ', $text), 0, 2000));
                        if (strlen($text) > 100) {
                            $contents[] = "--- {$url} ---\n{$text}";
                        }
                    }
                } catch (\Throwable $e) {
                    Log::info("[GEN] URL parse error for {$url}: {$e->getMessage()}");
                }
            }
        } catch (\Throwable $e) {
            Log::info("[GEN] URL pool fetch error: {$e->getMessage()}");
        }

        return implode("\n\n", $contents);
    }

    /**
     * Personas di default (stessi valori del Python get_default_personas()).
     */
    private function getDefaultPersonas(array $platforms): array
    {
        $defaultSlots = [];
        foreach ($platforms as $platform) {
            $defaultSlots[$platform] = [
                'optimal_slots' => [
                    ['day' => 1, 'time' => '10:00', 'priority' => 1],
                    ['day' => 3, 'time' => '14:00', 'priority' => 2],
                    ['day' => 5, 'time' => '10:00', 'priority' => 3],
                ],
                'avoid' => [],
            ];
        }

        return [
            'personas' => [
                [
                    'name'         => 'Professionista Target',
                    'weight'       => 1.0,
                    'demographics' => [
                        'age_range' => '30-50',
                        'role'      => 'Decision maker',
                        'location'  => 'Italia',
                    ],
                    'pain_points'  => ['Mancanza di tempo', 'Bisogno di efficienza'],
                    'interests'    => ['Innovazione', 'Best practices'],
                ],
            ],
            'scheduling_strategy' => $defaultSlots,
        ];
    }
}
