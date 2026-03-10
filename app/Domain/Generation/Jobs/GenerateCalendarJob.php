<?php

declare(strict_types=1);

namespace App\Domain\Generation\Jobs;

use App\Domain\Brand\Models\Brand;
use App\Domain\Generation\Contracts\ContentGeneratorInterface;
use App\Domain\Generation\Services\GenerationTracker;
use App\Domain\Organization\Models\UsageLog;
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

    /**
     * Tentativi massimi.
     */
    public int $tries = 3;

    /**
     * Timeout in secondi (10 minuti — generazioni grandi possono richiedere tempo).
     */
    public int $timeout = 600;

    public function __construct(
        private readonly int $projectId,
    ) {}

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
        $brandInfo = [
            'sector'               => $brand->sector,
            'description'          => $brand->description,
            'target_audience'      => $brand->target_audience,
            'unique_selling_points' => $brand->unique_selling_points,
            'brand_values'         => $brand->brand_values,
            'tone_of_voice'        => $brand->tone_of_voice,
            'style_guide'          => $brand->style_guide,
        ];

        $projectInfo = [
            'brief'           => $project->brief,
            'target_audience' => $project->target_audience,
            'custom_prompt'   => $project->custom_prompt,
            'objectives'      => $project->objectives ?? [],
        ];

        $themes = $project->content_pillars ?? $project->themes ?? [];

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

        // ── 7. Salva nuovi post (commit individuali come il Python) ──
        foreach ($posts as $postData) {
            Post::create([
                'project_id'        => $this->projectId,
                'platform'          => $postData['platform'] ?? '',
                'scheduled_date'    => $postData['scheduled_date'] ?? null,
                'scheduled_time'    => $postData['scheduled_time'] ?? '09:00',
                'content'           => $postData['content'] ?? '',
                'hashtags'          => $postData['hashtags'] ?? [],
                'pillar'            => $postData['pillar'] ?? '',
                'post_type'         => $postData['post_type'] ?? '',
                'content_type'      => $postData['content_type'] ?? 'post',
                'visual_suggestion' => $postData['visual_suggestion'] ?? '',
                'call_to_action'    => $postData['call_to_action'] ?? '',
                'cta'               => $postData['cta'] ?? '',
                'status'            => 'draft',
            ]);
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

    public function failed(?\Throwable $exception): void
    {
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
        $contents = [];

        foreach (array_slice($urls, 0, 5) as $url) {
            try {
                $response = Http::timeout(15)
                    ->withUserAgent('NosciteCalendar/1.0')
                    ->get($url);

                if ($response->successful()) {
                    // Estrai testo dal body HTML (versione semplificata)
                    $body = $response->body();
                    $text = strip_tags($body);
                    $text = preg_replace('/\s+/', ' ', $text);
                    $text = trim(mb_substr($text, 0, 5000));

                    if (strlen($text) > 100) {
                        $contents[] = "--- {$url} ---\n{$text}";
                    }
                }
            } catch (\Throwable $e) {
                Log::info("[GEN] URL fetch error for {$url}: {$e->getMessage()}");
            }
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
