<?php

declare(strict_types=1);

namespace App\Domain\Generation\Services;

use App\Domain\Brand\Models\Brand;
use App\Domain\Generation\Contracts\ContentGeneratorInterface;
use App\Domain\Post\Models\Post;
use App\Domain\Project\Models\Project;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generatore di contenuti via Anthropic Claude.
 *
 * Replica claude_service.py. Dopo il refactoring FASE 2:
 *   - PromptBuilder      → costruzione prompt
 *   - PersonaScheduler   → scheduling e redistribuzione post
 *   - AnthropicApiClient → chiamata HTTP + retry + JSON parse
 */
final class ClaudeContentGenerator implements ContentGeneratorInterface
{
    public const DEFAULT_STYLE_GUIDE = "\n" . <<<'TXT'
LINEE GUIDA CONTENUTI:
- Tono professionale ma accessibile
- Focus su valore pratico per il lettore
- Call-to-action chiare ma non aggressive
- Hashtag pertinenti e non eccessivi (3-5 per post social)
- Contenuti ottimizzati per ogni piattaforma

TXT;

    private const MODEL                 = 'claude-sonnet-4-20250514';
    private const MAX_TOKENS_BATCH      = 16_000;
    private const MAX_TOKENS_REGENERATE = 2_000;
    private const MAX_TOKENS_IMAGE      = 500;
    private const MAX_TOKENS_PERSONAS   = 4_000;
    private const BATCH_SIZE_DAYS       = 14;
    private const RATE_LIMIT_SLEEP      = 3;  // usato solo come fallback minimo

    public function __construct(
        private readonly PromptBuilder      $promptBuilder,
        private readonly PersonaScheduler   $personaScheduler,
        private readonly AnthropicApiClient $apiClient,
    ) {}

    // ── ContentGeneratorInterface ──────────────────────────────

    public function generateCalendar(Project $project): void
    {
        $project->update(['status' => 'generating']);
    }

    public function generateAiPosts(int $projectId, array $params): Collection
    {
        $project = Project::with('brand')->findOrFail($projectId);
        $brand   = $project->brand;

        [$posts, , $tokens] = $this->generateCalendarPosts(
            brandName:    $brand->name,
            brandInfo:    $this->brandInfoArray($brand),
            projectInfo:  $this->projectInfoArray($project),
            startDate:    Carbon::parse($params['start_date'] ?? $project->start_date),
            endDate:      Carbon::parse($params['end_date'] ?? $project->end_date),
            platforms:    $params['platforms'] ?? $project->platforms ?? [],
            postsPerWeek: $project->posts_per_week ?? [],
            themes:       $project->themes ?? [],
            urlContext:   null,
            styleGuide:   $brand->style_guide,
            buyerPersonas: $project->buyer_personas,
            brandId:      $brand->id,
            projectId:    $project->id,
        );

        $created = new Collection();
        foreach ($posts as $raw) {
            $created->push(Post::create([
                'project_id'        => $project->id,
                'platform'          => $raw['platform'] ?? '',
                'scheduled_date'    => $raw['scheduled_date'] ?? null,
                'scheduled_time'    => $raw['scheduled_time'] ?? null,
                'content'           => $raw['content'] ?? '',
                'hashtags'          => $raw['hashtags'] ?? [],
                'content_type'      => $raw['content_type'] ?? 'post',
                'post_type'         => $raw['post_type'] ?? 'educational',
                'pillar'            => $raw['pillar'] ?? null,
                'visual_suggestion' => $raw['visual_suggestion'] ?? null,
                'call_to_action'    => $raw['call_to_action'] ?? $raw['cta'] ?? null,
                'status'            => 'draft',
            ]));
        }

        $this->logTokenUsage($project->organization_id, $tokens, 'generate_ai_posts');
        return $created;
    }

    public function regeneratePost(int $postId, ?string $userPrompt = null): Post
    {
        $post  = Post::with('project.brand')->findOrFail($postId);
        $brand = $post->project->brand;

        $prompt = $this->promptBuilder->buildRegeneratePrompt(
            postContent:     $post->content,
            platform:        $post->platform,
            pillar:          $post->pillar ?? '',
            userPrompt:      $userPrompt ?? 'Migliora questo post',
            brandContext:    "{$brand->name} — {$brand->sector} — {$brand->description}",
            toneOfVoice:     $brand->tone_of_voice ?? 'professionale',
            brandStyleGuide: $brand->style_guide ?? '',
        );

        try {
            $response   = $this->apiClient->call($prompt, self::MAX_TOKENS_REGENERATE, self::MODEL);
            $tokensUsed = ($response['usage']['input_tokens'] ?? 0) + ($response['usage']['output_tokens'] ?? 0);
            $result     = $this->apiClient->parseJsonResponse(trim($response['content'][0]['text'] ?? ''));
        } catch (\Throwable $e) {
            Log::error("[CLAUDE] regeneratePost error: {$e->getMessage()}");
            $tokensUsed = 0;
            $result     = [];
        }

        $post->update([
            'content'           => $result['content'] ?? $post->content,
            'hashtags'          => $result['hashtags'] ?? $post->hashtags,
            'visual_suggestion' => $result['visual_suggestion'] ?? $post->visual_suggestion,
            'call_to_action'    => $result['cta'] ?? $result['call_to_action'] ?? $post->call_to_action,
        ]);

        $this->logTokenUsage($post->organization_id, $tokensUsed, 'regenerate_post');
        return $post->fresh();
    }

    // ── Personas ───────────────────────────────────────────────

    public function generatePersonas(int $projectId): array
    {
        $project = Project::with('brand')->findOrFail($projectId);
        $brand   = $project->brand;
        Log::info("[PERSONAS] Generating for project {$projectId} — Brand: {$brand->name}");

        $urlContext = '';
        if (!empty($project->reference_urls)) {
            try { $urlContext = $this->fetchUrlContextForPersonas($project->reference_urls); }
            catch (\Throwable $e) { Log::warning("[PERSONAS] URL fetch failed: {$e->getMessage()}"); }
        }

        $prompt = $this->promptBuilder->buildPersonaPrompt($brand, $project->platforms ?? [], $urlContext);

        try {
            $response             = $this->apiClient->call($prompt, self::MAX_TOKENS_PERSONAS, self::MODEL);
            $personas             = $this->apiClient->parseJsonResponse($response['content'][0]['text'] ?? '');
            $personas['generated_at'] = now()->toIso8601String();
            $personas['source']   = 'ai_analysis';
            Log::info('[PERSONAS] Generated ' . count($personas['personas'] ?? []) . ' personas');
        } catch (\Throwable $e) {
            Log::error("[PERSONAS] Claude call failed: {$e->getMessage()}");
            $personas = $this->personaScheduler->getDefaultPersonas($project->platforms ?? []);
        }

        $this->persistPersonas($project, $personas);
        return $personas;
    }

    public function regeneratePersonas(int $projectId, ?string $feedback = null): array
    {
        $project         = Project::with('brand')->findOrFail($projectId);
        $currentPersonas = $project->buyer_personas ?? ['personas' => []];

        $prompt = $this->promptBuilder->buildRegeneratePersonasPrompt(
            $project->brand, $project->platforms ?? [], $currentPersonas, $feedback ?? '',
        );

        try {
            $response             = $this->apiClient->call($prompt, self::MAX_TOKENS_PERSONAS, self::MODEL);
            $personas             = $this->apiClient->parseJsonResponse($response['content'][0]['text'] ?? '');
            $personas['generated_at'] = now()->toIso8601String();
            $personas['source']   = 'ai_regenerated';
        } catch (\Throwable $e) {
            Log::error("[PERSONAS] Regenerate failed: {$e->getMessage()}");
            $personas = $currentPersonas;
        }

        $this->persistPersonas($project, $personas);
        return $personas;
    }

    public function confirmPersonas(int $projectId, ?array $personas = null): array
    {
        $project = Project::findOrFail($projectId);
        if ($personas !== null) {
            $existing = $project->buyer_personas ?? [];
            $existing['personas']  = $personas;
            $existing['confirmed'] = true;
            $project->update(['buyer_personas' => $existing]);
        }
        return ['status' => 'confirmed'];
    }

    public function getPersonas(int $projectId): array
    {
        $project = Project::findOrFail($projectId);
        return ['personas' => $project->buyer_personas, 'confirmed' => $project->buyer_personas['confirmed'] ?? false];
    }

    public function addPersona(int $projectId, ?string $description = null): array
    {
        $project = Project::with('brand')->findOrFail($projectId);
        $bp      = $project->buyer_personas ?? ['personas' => []];

        $prompt = $this->promptBuilder->buildAddPersonaPrompt(
            $project->brand, $project->platforms ?? [], $bp['personas'] ?? [], $description,
        );

        try {
            $response   = $this->apiClient->call($prompt, self::MAX_TOKENS_PERSONAS, self::MODEL);
            $newPersona = $this->apiClient->parseJsonResponse($response['content'][0]['text'] ?? '');
        } catch (\Throwable $e) {
            Log::error("[PERSONAS] Add persona failed: {$e->getMessage()}");
            $newPersona = ['name' => 'Nuova Persona', 'description' => $description ?? 'Persona generata', 'weight' => 0.3];
        }

        $bp['personas'][]        = $newPersona;
        $bp['confirmed']         = false;
        $project->buyer_personas = $bp;
        $project->save();

        return ['success' => true, 'persona' => $newPersona, 'total_personas' => count($bp['personas'])];
    }

    public function deletePersona(int $projectId, int $personaIndex): array
    {
        $project  = Project::findOrFail($projectId);
        $bp       = $project->buyer_personas;

        if (!$bp || !isset($bp['personas'])) throw new \InvalidArgumentException('Nessuna persona trovata');

        $personas = $bp['personas'];
        if ($personaIndex < 0 || $personaIndex >= count($personas)) {
            throw new \InvalidArgumentException('Indice persona non valido');
        }

        $deleted = $personas[$personaIndex];
        array_splice($personas, $personaIndex, 1);
        $bp['personas'] = $personas;
        $bp['confirmed'] = false;
        $project->buyer_personas = $bp;
        $project->save();

        return ['success' => true, 'deleted' => $deleted['name'] ?? 'Persona', 'remaining_personas' => count($personas)];
    }

    public function regenerateSinglePersona(int $projectId, int $personaIndex, ?string $description = null): array
    {
        $project  = Project::with('brand')->findOrFail($projectId);
        $bp       = $project->buyer_personas;

        if (!$bp || !isset($bp['personas'])) throw new \InvalidArgumentException('Nessuna persona trovata');

        $personas = $bp['personas'];
        if ($personaIndex < 0 || $personaIndex >= count($personas)) {
            throw new \InvalidArgumentException('Indice persona non valido');
        }

        $oldPersona = $personas[$personaIndex];
        $prompt     = $this->promptBuilder->buildRegenerateSinglePersonaPrompt(
            $project->brand, $project->platforms ?? [], $oldPersona, $description,
        );

        try {
            $response   = $this->apiClient->call($prompt, self::MAX_TOKENS_PERSONAS, self::MODEL);
            $newPersona = $this->apiClient->parseJsonResponse($response['content'][0]['text'] ?? '');
        } catch (\Throwable $e) {
            Log::error("[PERSONAS] Regenerate single failed: {$e->getMessage()}");
            $newPersona = $oldPersona;
        }

        $personas[$personaIndex] = $newPersona;
        $bp['personas']          = $personas;
        $bp['confirmed']         = false;
        $project->buyer_personas = $bp;
        $project->save();

        return ['success' => true, 'old_persona' => $oldPersona['name'] ?? 'Vecchia Persona', 'new_persona' => $newPersona];
    }

    public function getGenerationStatus(int $projectId): array
    {
        return GenerationTracker::get($projectId) ?? [
            'status' => 'idle', 'post_count' => 0, 'percent' => 0, 'current_batch' => 0, 'total_batches' => 0,
        ];
    }

    // ── Core generation ────────────────────────────────────────

    /** @return array{0: list, 1: array, 2: int} [posts, personas, tokens] */
    public function generateCalendarPosts(
        string  $brandName,
        array   $brandInfo,
        array   $projectInfo,
        Carbon  $startDate,
        Carbon  $endDate,
        array   $platforms,
        array   $postsPerWeek,
        array   $themes = [],
        ?string $urlContext = null,
        ?string $styleGuide = null,
        ?array  $buyerPersonas = null,
        ?int    $brandId = null,
        ?int    $projectId = null,
    ): array {
        $ragContext = $brandId ? $this->getRagContext($brandId, "{$brandName} " . implode(' ', $themes)) : '';

        if (empty($buyerPersonas)) {
            $buyerPersonas = $this->personaScheduler->getDefaultPersonas($platforms);
        }

        $contentMixData  = $this->personaScheduler->getContentMixData($platforms, $brandInfo);
        $allPosts        = [];
        $totalTokensUsed = 0;
        $batchStartTime  = 0.0;
        $totalDays       = $startDate->diffInDays($endDate) + 1;
        $batches         = (int) ceil($totalDays / self::BATCH_SIZE_DAYS);

        for ($batchNum = 0; $batchNum < $batches; $batchNum++) {
            $batchStart = $startDate->copy()->addDays($batchNum * self::BATCH_SIZE_DAYS);
            $batchEnd   = $batchStart->copy()->addDays(self::BATCH_SIZE_DAYS - 1)->min($endDate);

            Log::info("[CLAUDE] Batch " . ($batchNum + 1) . "/{$batches}: {$batchStart->toDateString()} → {$batchEnd->toDateString()}");

            if ($projectId) {
                GenerationTracker::update($projectId, $batchNum, $batches, (int) (($batchNum / $batches) * 100));
            }

            if ($batchNum > 0) {
                // Sleep adattivo: aspetta solo il tempo necessario per rispettare
                // l'intervallo minimo tra batch (3s a Tier 2 è abbondantemente sicuro).
                // Se la chiamata precedente ha già impiegato >3s, non dormiamo affatto.
                $elapsed = microtime(true) - ($batchStartTime ?? 0);
                $waitFor = self::RATE_LIMIT_SLEEP - $elapsed;
                if ($waitFor > 0) {
                    Log::info("[CLAUDE] Batch interval sleep: {$waitFor}s (elapsed {$elapsed}s)");
                    usleep((int) ($waitFor * 1_000_000));
                }
            }
            $batchStartTime = microtime(true);

            [$posts, $batchTokens] = $this->generateBatch(
                $brandName, $brandInfo, $projectInfo, $batchStart, $batchEnd,
                $platforms, $postsPerWeek, $themes, $urlContext, $ragContext,
                $styleGuide ?? self::DEFAULT_STYLE_GUIDE, $buyerPersonas, $contentMixData,
                $batchNum + 1, $batches,
            );

            Log::info("[CLAUDE] Batch " . ($batchNum + 1) . " returned " . count($posts) . " posts");
            array_push($allPosts, ...$posts);
            $totalTokensUsed += $batchTokens;
        }

        $allPosts = $this->personaScheduler->redistributePostsWithPersonas(
            $allPosts, $postsPerWeek, $startDate, $endDate, $buyerPersonas,
        );

        Log::info('[CLAUDE] Total posts generated: ' . count($allPosts));
        return [$allPosts, $buyerPersonas, $totalTokensUsed];
    }

    /** @return array{0: list, 1: int} [posts, tokens] */
    public function generateBatch(
        string  $brandName,
        array   $brandInfo,
        array   $projectInfo,
        Carbon  $startDate,
        Carbon  $endDate,
        array   $platforms,
        array   $postsPerWeek,
        array   $themes,
        ?string $urlContext,
        string  $ragContext,
        string  $styleGuide,
        array   $buyerPersonas,
        array   $contentMixData,
        int     $batchNum,
        int     $totalBatches,
    ): array {
        $prompt = $this->promptBuilder->buildBatchPrompt(
            $brandName, $brandInfo, $projectInfo, $startDate, $endDate,
            $platforms, $postsPerWeek, $themes, $urlContext, $ragContext,
            $styleGuide, $buyerPersonas, $contentMixData,
        );

        Log::info("[CLAUDE] API call — {$brandName}, {$startDate->toDateString()} → {$endDate->toDateString()}");

        try {
            $response     = $this->apiClient->call($prompt, self::MAX_TOKENS_BATCH, self::MODEL);
            $batchTokens  = ($response['usage']['input_tokens'] ?? 0) + ($response['usage']['output_tokens'] ?? 0);
            $posts        = $this->apiClient->parseJsonResponse(trim($response['content'][0]['text'] ?? ''));

            Log::info("[CLAUDE] Batch tokens: {$batchTokens}, posts parsed: " . count($posts));
            return [$posts, $batchTokens];
        } catch (\Throwable $e) {
            Log::error("[CLAUDE] Batch error: {$e->getMessage()}");
            return [[], 0];
        }
    }

    /** @return array{0: string, 1: int} [prompt, tokens] */
    public function generateImagePrompt(
        string $postContent,
        string $platform,
        string $pillar,
        string $brandName,
        string $brandSector,
        string $brandColors = '',
        string $visualSuggestion = '',
    ): array {
        $prompt = $this->promptBuilder->buildImagePrompt(
            $postContent, $platform, $pillar, $brandName, $brandSector, $brandColors, $visualSuggestion
        );

        try {
            $response  = $this->apiClient->call($prompt, self::MAX_TOKENS_IMAGE, self::MODEL);
            $imgTokens = ($response['usage']['input_tokens'] ?? 0) + ($response['usage']['output_tokens'] ?? 0);
            return [trim($response['content'][0]['text'] ?? ''), $imgTokens];
        } catch (\Throwable $e) {
            Log::error("[CLAUDE] generate_image_prompt error: {$e->getMessage()}");
            return ["Professional {$brandSector} business image, modern and clean style", 0];
        }
    }

    // ── Private helpers ────────────────────────────────────────

    private function persistPersonas(Project $project, array $personas): void
    {
        if (isset($personas['recommended_posts_per_week'])) {
            $recommended             = $personas['recommended_posts_per_week'];
            $project->posts_per_week = collect($project->platforms ?? [])
                ->mapWithKeys(fn ($p) => [$p => $recommended[$p] ?? 3])
                ->toArray();
        }
        $project->buyer_personas = $personas;
        $project->save();
    }

    private function fetchUrlContextForPersonas(array $urls): string
    {
        $contents = [];
        foreach (array_slice($urls, 0, 5) as $url) {
            try {
                $response = Http::timeout(15)->withUserAgent('NosciteCalendar/1.0')->get($url);
                if ($response->successful()) {
                    $text = trim(mb_substr(preg_replace('/\s+/', ' ', strip_tags($response->body())), 0, 5000));
                    if (strlen($text) > 100) $contents[] = "--- {$url} ---\n{$text}";
                }
            } catch (\Throwable $e) {
                Log::info("[PERSONAS] URL fetch error for {$url}: {$e->getMessage()}");
            }
        }
        return implode("\n\n", $contents);
    }

    private function logTokenUsage(int $organizationId, int $tokens, string $operation): void
    {
        if ($tokens <= 0) return;
        Log::info("[TOKENS] Org #{$organizationId}: {$tokens} tokens ({$operation})");
        try {
            $now = now();
            \App\Domain\Subscription\Models\UsageLog::updateOrCreate([
                'organization_id' => $organizationId,
                'period_start'    => $now->copy()->startOfMonth()->toDateString(),
                'period_end'      => $now->copy()->endOfMonth()->toDateString(),
            ], [])->increment('text_tokens_used', $tokens);
        } catch (\Throwable $e) {
            Log::warning("[TOKENS] Failed to log usage: {$e->getMessage()}");
        }
    }

    private function getRagContext(int $brandId, string $searchQuery): string
    {
        return ''; // TODO: integra con RagService reale
    }

    private function brandInfoArray(Brand $brand): array
    {
        return [
            'sector'                => $brand->sector,
            'description'           => $brand->description,
            'tone_of_voice'         => $brand->tone_of_voice,
            'brand_values'          => $brand->brand_values ?? [],
            'target_audience'       => $brand->target_audience,
            'unique_selling_points' => $brand->unique_selling_points,
        ];
    }

    private function projectInfoArray(Project $project): array
    {
        return [
            'brief'           => $project->brief,
            'objectives'      => $project->objectives ?? [],
            'target_audience' => $project->target_audience ?? null,
        ];
    }
}
