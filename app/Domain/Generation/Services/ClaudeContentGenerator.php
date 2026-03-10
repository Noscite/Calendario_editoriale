<?php

declare(strict_types=1);

namespace App\Domain\Generation\Services;

use App\Domain\Brand\Models\Brand;
use App\Domain\Generation\Contracts\ContentGeneratorInterface;
use App\Domain\Generation\Data\GeneratedPostData;
use App\Domain\Post\Models\Post;
use App\Domain\Project\Models\Project;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Implementazione reale del generatore di contenuti via Anthropic Claude.
 *
 * Replica esattamente la logica di claude_service.py:
 * - generate_calendar_posts()        → generateCalendarPosts()
 * - generate_batch()                 → generateBatch()
 * - redistribute_posts_with_personas → redistributePostsWithPersonas()
 * - format_scheduling_from_personas  → formatSchedulingFromPersonas()
 * - format_content_mix_for_prompt    → formatContentMixForPrompt()
 * - regenerate_single_post           → regeneratePost()
 * - generate_image_prompt            → generateImagePrompt()
 * - DEFAULT_STYLE_GUIDE              → DEFAULT_STYLE_GUIDE
 */
final class ClaudeContentGenerator implements ContentGeneratorInterface
{
    // ──────────────────────────────────────────────────────────
    //  Costanti
    // ──────────────────────────────────────────────────────────

    // Python usa triple-quotes con newline iniziale: """\nLINEE GUIDA...
    // Replicato con \n iniziale per identità prompt.
    public const DEFAULT_STYLE_GUIDE = "\n" . <<<'TXT'
LINEE GUIDA CONTENUTI:
- Tono professionale ma accessibile
- Focus su valore pratico per il lettore
- Call-to-action chiare ma non aggressive
- Hashtag pertinenti e non eccessivi (3-5 per post social)
- Contenuti ottimizzati per ogni piattaforma

TXT;

    private const MODEL = 'claude-sonnet-4-20250514';

    private const MAX_TOKENS_BATCH      = 16_000;
    private const MAX_TOKENS_REGENERATE = 2_000;
    private const MAX_TOKENS_IMAGE      = 500;
    private const MAX_TOKENS_PERSONAS   = 4_000;
    private const BATCH_SIZE_DAYS       = 7;
    private const RATE_LIMIT_SLEEP      = 8;  // seconds between batches

    private string $apiKey;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key', env('ANTHROPIC_API_KEY', ''));
        $this->apiUrl = 'https://api.anthropic.com/v1/messages';
    }

    // ══════════════════════════════════════════════════════════
    //  ContentGeneratorInterface — public API
    // ══════════════════════════════════════════════════════════

    /**
     * Genera un piano editoriale completo (async via job).
     */
    public function generateCalendar(Project $project): void
    {
        // Dispatch real generation in a queued job.
        // The job itself calls generateCalendarPosts() below.
        // Qui salviamo lo stato "generating".
        $project->update(['status' => 'generating']);
    }

    /**
     * Genera post AI con parametri personalizzati.
     */
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
            urlContext:    null,
            styleGuide:   $brand->style_guide,
            buyerPersonas: $project->buyer_personas,
            brandId:      $brand->id,
            projectId:    $project->id,
        );

        // Persist posts
        $created = new Collection();
        foreach ($posts as $raw) {
            $created->push(Post::create([
                'project_id'        => $project->id,
                'brand_id'          => $brand->id,
                'organization_id'   => $project->organization_id,
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

        // Log tokens
        $this->logTokenUsage($project->organization_id, $tokens, 'generate_ai_posts');

        return $created;
    }

    /**
     * Rigenera un singolo post (regenerate_single_post).
     */
    public function regeneratePost(int $postId, ?string $userPrompt = null): Post
    {
        $post  = Post::with('project.brand')->findOrFail($postId);
        $brand = $post->project->brand;

        $result = $this->regenerateSinglePost(
            postContent:     $post->content,
            platform:        $post->platform,
            pillar:          $post->pillar ?? '',
            userPrompt:      $userPrompt ?? 'Migliora questo post',
            brandContext:     "{$brand->name} — {$brand->sector} — {$brand->description}",
            toneOfVoice:     $brand->tone_of_voice ?? 'professionale',
            brandStyleGuide: $brand->style_guide ?? '',
        );

        $tokensUsed = $result['_tokens_used'] ?? 0;
        unset($result['_tokens_used']);

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

    /**
     * Genera buyer personas via Claude (replica di analyze_buyer_personas()).
     * Aggiorna buyer_personas e posts_per_week sul progetto.
     */
    public function generatePersonas(int $projectId): array
    {
        $project = Project::with('brand')->findOrFail($projectId);
        $brand   = $project->brand;

        Log::info("[PERSONAS] Generating for project {$projectId} — Brand: {$brand->name}");

        // Fetch URL context
        $urlContext = '';
        if (! empty($project->reference_urls)) {
            try {
                $urlContext = $this->fetchUrlContextForPersonas($project->reference_urls);
            } catch (\Throwable $e) {
                Log::warning("[PERSONAS] URL fetch failed: {$e->getMessage()}");
            }
        }

        $personas = $this->callClaudeForPersonas($brand, $project->platforms ?? [], $urlContext);

        // Persist
        if (isset($personas['recommended_posts_per_week'])) {
            $recommended = $personas['recommended_posts_per_week'];
            $project->posts_per_week = collect($project->platforms ?? [])
                ->mapWithKeys(fn ($p) => [$p => $recommended[$p] ?? 3])
                ->toArray();
        }
        $project->buyer_personas = $personas;
        $project->save();

        return $personas;
    }

    /**
     * Rigenera buyer personas con feedback utente.
     */
    public function regeneratePersonas(int $projectId, ?string $feedback = null): array
    {
        $project = Project::with('brand')->findOrFail($projectId);
        $brand   = $project->brand;

        Log::info("[PERSONAS] Regenerating for project {$projectId} with feedback");

        $currentPersonas = $project->buyer_personas ?? ['personas' => []];
        $currentJson     = json_encode($currentPersonas, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $brandValues   = is_array($brand->brand_values) ? implode(', ', $brand->brand_values) : ($brand->brand_values ?? '');
        $platformsList = implode(', ', $project->platforms ?? []);

        $prompt = <<<PROMPT
Sei un esperto di marketing digitale. Stai migliorando le buyer personas di un brand.

## BRAND
- Nome: {$brand->name}
- Settore: {$brand->sector}
- Descrizione: {$brand->description}
- Target dichiarato: {$brand->target_audience}
- Valori: {$brandValues}
- Tono di voce: {$brand->tone_of_voice}

## PIATTAFORME ATTIVE
{$platformsList}

## PERSONAS ATTUALI
{$currentJson}

## FEEDBACK UTENTE
{$feedback}

## ISTRUZIONI
Rigenera le buyer personas tenendo conto del feedback dell'utente. Mantieni la stessa struttura JSON ma migliora/modifica le personas secondo le indicazioni.

## FORMATO OUTPUT (JSON valido, stessa struttura)
Rispondi SOLO con il JSON, senza markdown o altro testo.
PROMPT;

        try {
            $response = $this->callClaude($prompt, self::MAX_TOKENS_PERSONAS);
            $content  = $response['content'][0]['text'] ?? '';
            $personas = $this->parseJsonResponse($content);
            $personas['generated_at'] = now()->toIso8601String();
            $personas['source']       = 'ai_regenerated';
        } catch (\Throwable $e) {
            Log::error("[PERSONAS] Regenerate failed: {$e->getMessage()}");
            $personas = $currentPersonas;
        }

        if (isset($personas['recommended_posts_per_week'])) {
            $recommended = $personas['recommended_posts_per_week'];
            $project->posts_per_week = collect($project->platforms ?? [])
                ->mapWithKeys(fn ($p) => [$p => $recommended[$p] ?? 3])
                ->toArray();
        }
        $project->buyer_personas = $personas;
        $project->save();

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

        return [
            'personas'  => $project->buyer_personas,
            'confirmed' => $project->buyer_personas['confirmed'] ?? false,
        ];
    }

    /**
     * Genera una nuova buyer persona via Claude e la aggiunge al progetto.
     */
    public function addPersona(int $projectId, ?string $description = null): array
    {
        $project = Project::with('brand')->findOrFail($projectId);
        $brand   = $project->brand;

        $bp              = $project->buyer_personas ?? ['personas' => []];
        $existingCount   = count($bp['personas'] ?? []);
        $existingNames   = implode(', ', array_column($bp['personas'] ?? [], 'name'));
        $platformsList   = implode(', ', $project->platforms ?? []);
        $brandValues     = is_array($brand->brand_values) ? implode(', ', $brand->brand_values) : ($brand->brand_values ?? '');
        $descriptionText = $description ? "Genera una persona che corrisponde a: {$description}" : 'Genera una nuova persona diversa da quelle esistenti';

        $prompt = <<<PROMPT
Sei un esperto di marketing digitale.

## BRAND
- Nome: {$brand->name}
- Settore: {$brand->sector}
- Descrizione: {$brand->description}
- Target: {$brand->target_audience}
- Valori: {$brandValues}

## PIATTAFORME
{$platformsList}

## PERSONAS ESISTENTI (non duplicare)
{$existingNames}

## ISTRUZIONI
{$descriptionText}
Genera UNA SOLA buyer persona aggiuntiva, diversa da quelle già presenti.

## FORMATO OUTPUT (JSON valido)
```json
{
  "name": "Nome - Ruolo",
  "demographics": {
    "age_range": "30-45",
    "gender": "...",
    "role": "...",
    "location": "...",
    "income": "..."
  },
  "digital_behavior": {
    "linkedin": {"usage": "...", "best_days": [1,3], "best_times": ["08:30"], "content_preferences": []},
    "instagram": {"usage": "...", "best_days": [5,6], "best_times": ["19:00"], "content_preferences": []}
  },
  "pain_points": ["...", "..."],
  "interests": ["...", "..."],
  "buying_triggers": ["...", "..."],
  "weight": 0.3
}
```
Rispondi SOLO con il JSON della persona, senza markdown.
PROMPT;

        try {
            $response   = $this->callClaude($prompt, self::MAX_TOKENS_PERSONAS);
            $content    = $response['content'][0]['text'] ?? '';
            $newPersona = $this->parseJsonResponse($content);
        } catch (\Throwable $e) {
            Log::error("[PERSONAS] Add persona failed: {$e->getMessage()}");
            $newPersona = [
                'name'        => 'Nuova Persona',
                'description' => $description ?? 'Persona generata automaticamente',
                'weight'      => 0.3,
            ];
        }

        $bp['personas'][]  = $newPersona;
        $bp['confirmed']   = false;
        $project->buyer_personas = $bp;
        $project->save();

        return [
            'success'        => true,
            'persona'        => $newPersona,
            'total_personas' => count($bp['personas']),
        ];
    }

    public function deletePersona(int $projectId, int $personaIndex): array
    {
        $project = Project::findOrFail($projectId);
        $bp      = $project->buyer_personas;

        if (! $bp || ! isset($bp['personas'])) {
            throw new \InvalidArgumentException('Nessuna persona trovata');
        }

        $personas = $bp['personas'];
        if ($personaIndex < 0 || $personaIndex >= count($personas)) {
            throw new \InvalidArgumentException('Indice persona non valido');
        }

        $deleted = $personas[$personaIndex];
        array_splice($personas, $personaIndex, 1);

        $bp['personas']  = $personas;
        $bp['confirmed'] = false;
        $project->buyer_personas = $bp;
        $project->save();

        return [
            'success'            => true,
            'deleted'            => $deleted['name'] ?? 'Persona',
            'remaining_personas' => count($personas),
        ];
    }

    /**
     * Rigenera una singola buyer persona per indice.
     */
    public function regenerateSinglePersona(int $projectId, int $personaIndex, ?string $description = null): array
    {
        $project = Project::with('brand')->findOrFail($projectId);
        $brand   = $project->brand;

        $bp = $project->buyer_personas;
        if (! $bp || ! isset($bp['personas'])) {
            throw new \InvalidArgumentException('Nessuna persona trovata');
        }

        $personas = $bp['personas'];
        if ($personaIndex < 0 || $personaIndex >= count($personas)) {
            throw new \InvalidArgumentException('Indice persona non valido');
        }

        $oldPersona    = $personas[$personaIndex];
        $oldPersonaJson = json_encode($oldPersona, JSON_UNESCAPED_UNICODE);
        $platformsList  = implode(', ', $project->platforms ?? []);
        $brandValues    = is_array($brand->brand_values) ? implode(', ', $brand->brand_values) : ($brand->brand_values ?? '');
        $descText       = $description ? "Tieni conto di questa indicazione: {$description}" : 'Migliora la persona rendendola più precisa e dettagliata';

        $prompt = <<<PROMPT
Sei un esperto di marketing digitale. Devi rigenerare una buyer persona.

## BRAND
- Nome: {$brand->name}
- Settore: {$brand->sector}
- Descrizione: {$brand->description}
- Target: {$brand->target_audience}
- Valori: {$brandValues}

## PIATTAFORME
{$platformsList}

## PERSONA DA RIGENERARE
{$oldPersonaJson}

## ISTRUZIONI
{$descText}
Genera una versione migliorata di questa persona mantenendo la stessa struttura JSON.

## FORMATO OUTPUT (JSON valido, stessa struttura della persona originale)
Rispondi SOLO con il JSON, senza markdown.
PROMPT;

        try {
            $response   = $this->callClaude($prompt, self::MAX_TOKENS_PERSONAS);
            $content    = $response['content'][0]['text'] ?? '';
            $newPersona = $this->parseJsonResponse($content);
        } catch (\Throwable $e) {
            Log::error("[PERSONAS] Regenerate single failed: {$e->getMessage()}");
            $newPersona = $oldPersona;
        }

        $personas[$personaIndex] = $newPersona;
        $bp['personas']          = $personas;
        $bp['confirmed']         = false;
        $project->buyer_personas = $bp;
        $project->save();

        return [
            'success'     => true,
            'old_persona' => $oldPersona['name'] ?? 'Vecchia Persona',
            'new_persona' => $newPersona,
        ];
    }

    public function getGenerationStatus(int $projectId): array
    {
        $cached = GenerationTracker::get($projectId);

        return $cached ?? [
            'status'        => 'idle',
            'post_count'    => 0,
            'percent'       => 0,
            'current_batch' => 0,
            'total_batches' => 0,
        ];
    }

    // ══════════════════════════════════════════════════════════
    //  Core Generation — replica esatta di claude_service.py
    // ══════════════════════════════════════════════════════════

    /**
     * generate_calendar_posts() — orchestratore principale.
     *
     * STEP 0: Recupera contesto dalla Knowledge Base (RAG)
     * STEP 1: Analizza/genera buyer personas se non fornite
     * STEP 1.5: Ricerca mix contenuti ottimale via Perplexity
     * STEP 2: Genera contenuti in batch (7 giorni per batch)
     * STEP 3: Redistribuisci con scheduling da personas
     *
     * @return array{0: list, 1: array, 2: int} [posts, personas, tokens]
     */
    public function generateCalendarPosts(
        string $brandName,
        array  $brandInfo,
        array  $projectInfo,
        Carbon $startDate,
        Carbon $endDate,
        array  $platforms,
        array  $postsPerWeek,
        array  $themes = [],
        ?string $urlContext = null,
        ?string $styleGuide = null,
        ?array  $buyerPersonas = null,
        ?int    $brandId = null,
        ?int    $projectId = null,
    ): array {
        // STEP 0: RAG context
        $ragContext = '';
        if ($brandId) {
            try {
                $searchQuery = "{$brandName} {$projectInfo['brief']} " . implode(' ', $themes);
                $ragContext   = $this->getRagContext($brandId, $searchQuery);
                if ($ragContext) {
                    Log::info("[RAG] Found relevant context from documents (" . strlen($ragContext) . " chars)");
                }
            } catch (\Throwable $e) {
                Log::warning("[RAG] Error getting context: {$e->getMessage()}");
            }
        }

        // STEP 1: Buyer personas (se non fornite, usa default)
        if (empty($buyerPersonas)) {
            Log::info('[CLAUDE] No personas provided — using defaults');
            $buyerPersonas = $this->getDefaultPersonas($platforms);
        }

        // STEP 1.5: Mix contenuti (Perplexity — placeholder)
        $contentMixData = $this->getContentMixData($platforms, $brandInfo);

        // STEP 2: Genera in batch da 7 giorni
        $allPosts        = [];
        $totalTokensUsed = 0;
        $totalDays       = $startDate->diffInDays($endDate) + 1;
        $batches         = (int) ceil($totalDays / self::BATCH_SIZE_DAYS);

        for ($batchNum = 0; $batchNum < $batches; $batchNum++) {
            $batchStart = $startDate->copy()->addDays($batchNum * self::BATCH_SIZE_DAYS);
            $batchEnd   = $batchStart->copy()->addDays(self::BATCH_SIZE_DAYS - 1)->min($endDate);

            Log::info("[CLAUDE] Batch " . ($batchNum + 1) . "/{$batches}: {$batchStart->toDateString()} to {$batchEnd->toDateString()}");

            // Progress tracker (usa GenerationTracker → Redis)
            if ($projectId) {
                $percent = (int) (($batchNum / $batches) * 100);
                GenerationTracker::update($projectId, $batchNum, $batches, $percent);
            }

            // Rate limit sleep between batches
            if ($batchNum > 0) {
                Log::info('[CLAUDE] Waiting ' . self::RATE_LIMIT_SLEEP . 's for rate limit...');
                sleep(self::RATE_LIMIT_SLEEP);
            }

            [$posts, $batchTokens] = $this->generateBatch(
                brandName:      $brandName,
                brandInfo:      $brandInfo,
                projectInfo:    $projectInfo,
                startDate:      $batchStart,
                endDate:        $batchEnd,
                platforms:      $platforms,
                postsPerWeek:   $postsPerWeek,
                themes:         $themes,
                urlContext:      $urlContext,
                ragContext:      $ragContext,
                styleGuide:     $styleGuide ?? self::DEFAULT_STYLE_GUIDE,
                buyerPersonas:  $buyerPersonas,
                contentMixData: $contentMixData,
                batchNum:       $batchNum + 1,
                totalBatches:   $batches,
            );

            Log::info("[CLAUDE] Batch " . ($batchNum + 1) . " returned " . count($posts) . " posts");
            array_push($allPosts, ...$posts);
            $totalTokensUsed += $batchTokens;
        }

        // STEP 3: Redistribuisci con scheduling da personas
        $allPosts = $this->redistributePostsWithPersonas(
            $allPosts,
            $postsPerWeek,
            $startDate,
            $endDate,
            $buyerPersonas,
        );

        Log::info('[CLAUDE] Total posts generated: ' . count($allPosts));

        return [$allPosts, $buyerPersonas, $totalTokensUsed];
    }

    // ══════════════════════════════════════════════════════════
    //  generateBatch() — il PROMPT esatto usato per Claude
    // ══════════════════════════════════════════════════════════

    /**
     * Genera un batch di post — replica esatta di generate_batch().
     *
     * @return array{0: list, 1: int} [posts, tokens]
     */
    public function generateBatch(
        string $brandName,
        array  $brandInfo,
        array  $projectInfo,
        Carbon $startDate,
        Carbon $endDate,
        array  $platforms,
        array  $postsPerWeek,
        array  $themes,
        ?string $urlContext,
        string $ragContext,
        string $styleGuide,
        array  $buyerPersonas,
        array  $contentMixData,
        int    $batchNum,
        int    $totalBatches,
    ): array {
        // Estrai scheduling strategy dalle personas
        $schedulingInfo = $this->formatSchedulingFromPersonas($buyerPersonas, $platforms);

        // Formatta mix contenuti per il prompt
        $contentMixInfo = ! empty($contentMixData)
            ? $this->formatContentMixForPrompt($contentMixData)
            : 'Usa mix standard: 60% post, 25% stories, 15% reel (dove supportati)';

        // Formatta personas per il prompt
        $personasText = $this->formatPersonasForPrompt($buyerPersonas);

        $platformsList  = implode(', ', $platforms);
        $postsPerWeekJson = json_encode($postsPerWeek, JSON_UNESCAPED_UNICODE);
        $themesList     = ! empty($themes) ? implode(', ', $themes) : 'Generici per il settore';
        $objectivesList = implode(', ', $projectInfo['objectives'] ?? ['brand_awareness']);

        $prompt = <<<PROMPT
Genera contenuti per il calendario editoriale.

## BRAND
Nome: {$brandName}
Settore: {$this->arr($brandInfo, 'sector', 'N/A')}
Descrizione: {$this->arr($brandInfo, 'description', 'N/A')}
Tono di voce: {$this->arr($brandInfo, 'tone_of_voice', 'professionale')}
Valori: {$this->arrJson($brandInfo, 'brand_values', '[]')}

## CONTESTO DAL SITO
{$this->str($urlContext, 'Non disponibile')}

## KNOWLEDGE BASE AZIENDALE
{$this->str($ragContext, 'Non disponibile')}

## BUYER PERSONAS
{$personasText}

## SCHEDULING OTTIMALE (basato sulle personas)
{$schedulingInfo}

## MIX FORMATI CONTENUTO (basato su ricerca Perplexity)
{$contentMixInfo}

## PROGETTO
Periodo: {$startDate->toDateString()} - {$endDate->toDateString()}
Piattaforme: {$platformsList}
Post per settimana: {$postsPerWeekJson}
Temi: {$themesList}
Brief: {$this->arr($projectInfo, 'brief', 'N/A')}
Obiettivi: {$objectivesList}

## CALL TO ACTION
Genera una CTA specifica e coinvolgente per OGNI post basandoti sugli obiettivi del progetto:
- Per **lead_generation**: invita a scaricare risorse, prenotare call, richiedere preventivi, iscriversi
- Per **brand_awareness**: invita a seguire, condividere, taggare altri
- Per **engagement**: stimola commenti, risposte, interazioni, opinioni
- Per **sales**: spingi all'acquisto, offerte, promozioni
- Per **traffic**: rimanda al sito, blog, link in bio

IMPORTANTE: Il campo "call_to_action" è OBBLIGATORIO per ogni post. La CTA deve essere naturale, contestuale al contenuto e coerente con l'obiettivo

## LINEE GUIDA
{$styleGuide}

## FORMATI CONTENUTO DISPONIBILI
- **post**: Contenuto standard (immagine + testo). Per tutti i canali.
- **story**: Contenuto effimero 24h verticale. SOLO Instagram e Facebook.
- **reel**: Video breve verticale 15-60s. SOLO Instagram, Facebook, TikTok.

## ISTRUZIONI
1. Genera i contenuti per questo periodo RISPETTANDO IL MIX di formati indicato sopra
2. USA GLI ORARI E I GIORNI indicati nello scheduling
3. Adatta tono e contenuto alle personas identificate
4. VARIA i formati (post/story/reel) secondo le percentuali raccomandate per ogni piattaforma
5. Per STORY: testo breve, call-to-action diretta, emoji, interattività (sondaggi, domande)
6. Per REEL: testo brevissimo (hook iniziale), descrizione video, hashtag trending
7. Ogni contenuto deve avere: platform, scheduled_date, scheduled_time, content, hashtags, content_type (post/story/reel), post_type, pillar, visual_suggestion, call_to_action

## FORMATO OUTPUT (JSON array)
[
  {
    "platform": "instagram",
    "scheduled_date": "2025-01-07",
    "scheduled_time": "08:30",
    "content": "Testo lungo del post con valore educativo...",
    "hashtags": ["hashtag1", "hashtag2"],
    "content_type": "post",
    "post_type": "educational",
    "pillar": "thought leadership",
    "visual_suggestion": "Carousel con 5 slide infografiche"
  },
  {
    "platform": "instagram",
    "scheduled_date": "2025-01-07",
    "scheduled_time": "12:00",
    "content": "Oggi in ufficio... indovina cosa stiamo preparando! 🎬\n\nRispondi con un emoji!",
    "hashtags": ["behindthescenes", "team"],
    "content_type": "story",
    "post_type": "engagement",
    "pillar": "brand awareness",
    "visual_suggestion": "Video 10s del team al lavoro con sticker sondaggio"
  },
  {
    "platform": "instagram",
    "scheduled_date": "2025-01-08",
    "scheduled_time": "18:00",
    "content": "3 errori che tutti fanno! 🚫\n\nGuarda fino alla fine per il bonus tip 💡",
    "hashtags": ["tips", "tutorial", "imparacontiktok"],
    "content_type": "reel",
    "post_type": "educational",
    "pillar": "thought leadership",
    "visual_suggestion": "Video verticale 30s: hook 3s + 3 tips con testo overlay + CTA finale. Musica trending."
  }
]

Rispondi SOLO con il JSON array, senza markdown.
PROMPT;

        Log::info("[CLAUDE] Calling API — Brand: {$brandName}, Period: {$startDate->toDateString()} to {$endDate->toDateString()}");

        try {
            $response = $this->callClaude($prompt, self::MAX_TOKENS_BATCH);

            $inputTokens  = $response['usage']['input_tokens'] ?? 0;
            $outputTokens = $response['usage']['output_tokens'] ?? 0;
            $batchTokens  = $inputTokens + $outputTokens;

            Log::info("[CLAUDE] Batch tokens: {$batchTokens} (in={$inputTokens}, out={$outputTokens})");

            $content = $response['content'][0]['text'] ?? '';
            $content = trim($content);

            Log::info('[CLAUDE] Response length: ' . strlen($content) . ' chars');

            // Parse JSON — rimuovi backtick markdown come fa il Python
            $posts = $this->parseJsonResponse($content);

            Log::info('[CLAUDE] Parsed ' . count($posts) . ' posts');

            return [$posts, $batchTokens];
        } catch (\JsonException $e) {
            Log::error("[CLAUDE] JSON parse error: {$e->getMessage()}");

            return [[], 0];
        } catch (\Throwable $e) {
            Log::error("[CLAUDE] API error: {$e->getMessage()}");

            return [[], 0];
        }
    }

    // ══════════════════════════════════════════════════════════
    //  redistributePostsWithPersonas()
    // ══════════════════════════════════════════════════════════

    /**
     * Redistribuisce i post usando lo scheduling delle buyer personas.
     * Replica esatta di redistribute_posts_with_personas().
     */
    public function redistributePostsWithPersonas(
        array  $posts,
        array  $postsPerWeek,
        Carbon $startDate,
        Carbon $endDate,
        array  $personasData,
    ): array {
        if (empty($posts)) {
            return [];
        }

        Log::info('[CLAUDE] Redistributing ' . count($posts) . ' posts with persona-based scheduling');

        $strategy = $personasData['scheduling_strategy'] ?? [];

        // Raggruppa post per piattaforma
        $byPlatform = [];
        foreach ($posts as $post) {
            $plat = strtolower($post['platform'] ?? '');
            $byPlatform[$plat][] = $post;
        }

        $redistributed = [];
        $totalDays  = $startDate->diffInDays($endDate) + 1;
        $totalWeeks = (int) ceil($totalDays / 7);

        foreach ($byPlatform as $platform => $platformPosts) {
            // Ottieni slots dalla strategy
            $platStrategy  = $strategy[$platform] ?? [];
            $optimalSlots  = $platStrategy['optimal_slots'] ?? [];

            // Fallback se non ci sono slots
            if (empty($optimalSlots)) {
                $optimalSlots = [
                    ['day' => 1, 'time' => '10:00', 'priority' => 1],
                    ['day' => 3, 'time' => '10:00', 'priority' => 2],
                ];
            }

            // Ordina per priorità
            usort($optimalSlots, fn ($a, $b) => ($a['priority'] ?? 99) <=> ($b['priority'] ?? 99));

            // Quanti post per settimana
            $ppw = $postsPerWeek[$platform] ?? 2;

            $postIdx = 0;
            for ($week = 0; $week < $totalWeeks; $week++) {
                $weekStart = $startDate->copy()->addWeeks($week);

                foreach ($optimalSlots as $slotNum => $slot) {
                    if ($slotNum >= $ppw || $postIdx >= count($platformPosts)) {
                        break;
                    }

                    $dayOfWeek     = $slot['day'] ?? 0;
                    $time          = $slot['time'] ?? '10:00';
                    $daysUntilTarget = ($dayOfWeek - $weekStart->dayOfWeekIso + 7) % 7;
                    // Python uses weekday() (Monday=0) — Carbon dayOfWeekIso is Monday=1
                    // Adjust: Python day 0 = Monday = Carbon 1
                    $daysUntilTarget = (($dayOfWeek) - ($weekStart->dayOfWeekIso - 1) + 7) % 7;
                    $postDate      = $weekStart->copy()->addDays($daysUntilTarget);

                    // Verifica range
                    if ($postDate->lt($startDate)) {
                        $postDate->addDays(7);
                    }
                    if ($postDate->gt($endDate)) {
                        continue;
                    }

                    $p = $platformPosts[$postIdx];
                    $p['scheduled_date'] = $postDate->toDateString();
                    $p['scheduled_time'] = $time;
                    $redistributed[]     = $p;
                    $postIdx++;
                }
            }
        }

        // Ordina per data e ora
        usort($redistributed, function (array $a, array $b): int {
            return ($a['scheduled_date'] ?? '') <=> ($b['scheduled_date'] ?? '')
                ?: ($a['scheduled_time'] ?? '') <=> ($b['scheduled_time'] ?? '');
        });

        Log::info('[CLAUDE] Redistributed to ' . count($redistributed) . ' posts with persona scheduling');

        // Log distribuzione
        $platformCounts = [];
        foreach ($redistributed as $p) {
            $plat = $p['platform'] ?? 'unknown';
            $platformCounts[$plat] = ($platformCounts[$plat] ?? 0) + 1;
        }
        Log::info('[CLAUDE] Distribution: ' . json_encode($platformCounts));

        return $redistributed;
    }

    // ══════════════════════════════════════════════════════════
    //  formatSchedulingFromPersonas()
    // ══════════════════════════════════════════════════════════

    /**
     * Formatta lo scheduling strategy per il prompt.
     * Replica esatta di format_scheduling_from_personas().
     */
    public function formatSchedulingFromPersonas(array $personasData, array $platforms): string
    {
        $strategy = $personasData['scheduling_strategy'] ?? [];

        $dayNames = ['Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato', 'Domenica'];

        $lines = [];
        foreach ($platforms as $platform) {
            $platStrategy = $strategy[$platform] ?? [];
            $slots        = $platStrategy['optimal_slots'] ?? [];
            $avoid        = $platStrategy['avoid'] ?? [];

            // Ordina per priorità e prendi top 3
            usort($slots, fn ($a, $b) => ($a['priority'] ?? 99) <=> ($b['priority'] ?? 99));
            $topSlots = array_slice($slots, 0, 3);

            $slotStrs = [];
            foreach ($topSlots as $s) {
                $day  = $s['day'] ?? 0;
                $time = $s['time'] ?? '12:00';
                $slotStrs[] = ($dayNames[$day] ?? 'N/A') . ' ' . $time;
            }

            $lines[] = '- ' . strtoupper($platform) . ': ' . (! empty($slotStrs) ? implode(', ', $slotStrs) : 'flessibile');
            if (! empty($avoid)) {
                $lines[] = '  (evitare: ' . implode(', ', array_slice($avoid, 0, 2)) . ')';
            }
        }

        return implode("\n", $lines);
    }

    // ══════════════════════════════════════════════════════════
    //  formatContentMixForPrompt()
    // ══════════════════════════════════════════════════════════

    /**
     * Formatta i dati del mix contenuti per includerli nel prompt di Claude.
     * Replica esatta di format_content_mix_for_prompt() da perplexity_content_mix_research.py.
     */
    public function formatContentMixForPrompt(array $contentMixData): string
    {
        if (empty($contentMixData)) {
            return 'Nessuna ricerca disponibile - usa mix standard';
        }

        $lines = ["## MIX CONTENUTI RACCOMANDATO (basato su ricerca Perplexity)\n"];

        foreach ($contentMixData as $platform => $data) {
            $platformUpper = strtoupper($platform);

            if (($data['source'] ?? '') === 'perplexity') {
                $confidence = $data['confidence'] ?? 'medium';
                $lines[] = "### {$platformUpper} (confidence: {$confidence})";
            } else {
                $lines[] = "### {$platformUpper} (default)";
            }

            $supportsStories = $data['supports_stories'] ?? false;
            $supportsReels   = $data['supports_reels'] ?? false;

            $mix    = $data['format_mix'] ?? [];
            $weekly = $data['format_weekly_count'] ?? [];
            $total  = $data['recommended_weekly_total'] ?? 5;

            $lines[] = "- Contenuti settimanali totali: {$total}";
            $lines[] = '- POST: ' . ($mix['post_percentage'] ?? 100) . '% (' . ($weekly['posts'] ?? $total) . ' a settimana)';

            if ($supportsStories) {
                $lines[] = '- STORIES: ' . ($mix['story_percentage'] ?? 0) . '% (' . ($weekly['stories'] ?? 0) . ' a settimana)';
            } else {
                $lines[] = "- STORIES: Non supportate su {$platform}";
            }

            if ($supportsReels) {
                $lines[] = '- REELS: ' . ($mix['reel_percentage'] ?? 0) . '% (' . ($weekly['reels'] ?? 0) . ' a settimana)';
            } else {
                $lines[] = "- REELS: Non supportati su {$platform}";
            }

            // Idee contenuto
            $ideas = $data['best_content_ideas'] ?? [];
            if (! empty($ideas['posts'])) {
                $lines[] = '- Idee POST: ' . implode(', ', array_slice($ideas['posts'], 0, 3));
            }
            if (! empty($ideas['stories']) && $supportsStories) {
                $lines[] = '- Idee STORIES: ' . implode(', ', array_slice($ideas['stories'], 0, 3));
            }
            if (! empty($ideas['reels']) && $supportsReels) {
                $lines[] = '- Idee REELS: ' . implode(', ', array_slice($ideas['reels'], 0, 3));
            }

            // Tips settoriali
            $tips = $data['sector_specific_tips'] ?? null;
            if ($tips) {
                $lines[] = '- Tips settore: ' . mb_substr($tips, 0, 200);
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    // ══════════════════════════════════════════════════════════
    //  regenerateSinglePost() — rigenerazione singolo post
    // ══════════════════════════════════════════════════════════

    /**
     * Rigenera un singolo post con feedback utente.
     * Replica esatta di regenerate_single_post().
     */
    public function regenerateSinglePost(
        string $postContent,
        string $platform,
        string $pillar,
        string $userPrompt,
        string $brandContext,
        string $toneOfVoice,
        string $brandStyleGuide = '',
    ): array {
        $prompt = <<<PROMPT
Rigenera questo post social.

## POST ORIGINALE
Piattaforma: {$platform}
Pillar: {$pillar}
Contenuto: {$postContent}

## CONTESTO BRAND
{$brandContext}
Tono di voce: {$toneOfVoice}

## ISTRUZIONI UTENTE
{$userPrompt}

## LINEE GUIDA
{$this->str($brandStyleGuide, self::DEFAULT_STYLE_GUIDE)}

## OUTPUT (JSON)
{
  "content": "Nuovo testo del post",
  "hashtags": ["hashtag1", "hashtag2"],
  "visual_suggestion": "Suggerimento per visual",
  "cta": "Call to action"
}

Rispondi SOLO con il JSON.
PROMPT;

        try {
            $response = $this->callClaude($prompt, self::MAX_TOKENS_REGENERATE);

            $inputTokens  = $response['usage']['input_tokens'] ?? 0;
            $outputTokens = $response['usage']['output_tokens'] ?? 0;
            $regenTokens  = $inputTokens + $outputTokens;

            Log::info("[CLAUDE] Regen tokens: {$regenTokens} (in={$inputTokens}, out={$outputTokens})");

            $content = $response['content'][0]['text'] ?? '';
            $result  = $this->parseJsonResponse(trim($content));

            // Il Python mette _tokens_used nel risultato
            if (is_array($result)) {
                $result['_tokens_used'] = $regenTokens;
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error("[CLAUDE] regenerate_single_post error: {$e->getMessage()}");

            return ['content' => $postContent, '_tokens_used' => 0];
        }
    }

    // ══════════════════════════════════════════════════════════
    //  generateImagePrompt() — prompt DALL-E via Claude
    // ══════════════════════════════════════════════════════════

    /**
     * Genera un prompt dettagliato per DALL-E per un'immagine di un post.
     * Replica esatta di generate_image_prompt().
     *
     * @return array{0: string, 1: int} [prompt, tokens]
     */
    public function generateImagePrompt(
        string $postContent,
        string $platform,
        string $pillar,
        string $brandName,
        string $brandSector,
        string $brandColors = '',
        string $visualSuggestion = '',
    ): array {
        $prompt = <<<PROMPT
Crea un prompt dettagliato per DALL-E per generare un'immagine per questo post social.

## POST
Piattaforma: {$platform}
Contenuto: {$postContent}
Pillar: {$pillar}

## BRAND
Nome: {$brandName}
Settore: {$brandSector}
Colori: {$this->str($brandColors, 'Non specificati')}
Stile richiesto: {$this->str($visualSuggestion, 'Non specificato')}

## ISTRUZIONI
- Crea un prompt in inglese per DALL-E
- Stile professionale e moderno
- Adatto per {$platform}
- IMPORTANTE: Nessun testo, nessuna scritta, nessuna parola, nessun numero nell'immagine
- NO loghi o marchi
- Formato: descrizione dettagliata in 1-2 frasi

Rispondi SOLO con il prompt in inglese, senza altro testo.
PROMPT;

        try {
            $response = $this->callClaude($prompt, self::MAX_TOKENS_IMAGE);

            $inputTokens  = $response['usage']['input_tokens'] ?? 0;
            $outputTokens = $response['usage']['output_tokens'] ?? 0;
            $imgTokens    = $inputTokens + $outputTokens;

            Log::info("[CLAUDE] Image prompt tokens: {$imgTokens}");

            $text = trim($response['content'][0]['text'] ?? '');

            return [$text, $imgTokens];
        } catch (\Throwable $e) {
            Log::error("[CLAUDE] generate_image_prompt error: {$e->getMessage()}");

            return ["Professional {$brandSector} business image, modern and clean style", 0];
        }
    }

    // ══════════════════════════════════════════════════════════
    //  Private helpers
    // ══════════════════════════════════════════════════════════

    /**
     * Chiama l'API Anthropic Messages via Http::withHeaders().
     *
     * @return array Risposta decodificata (usage, content, etc.)
     *
     * @throws \RuntimeException se la risposta non è 2xx
     */
    /**
     * Chiama Claude con il prompt di analisi personas (replica di analyze_buyer_personas()).
     */
    private function callClaudeForPersonas(Brand $brand, array $platforms, string $urlContext = ''): array
    {
        $brandValues   = is_array($brand->brand_values) ? implode(', ', $brand->brand_values) : ($brand->brand_values ?? 'Non specificati');
        $platformsList = ! empty($platforms) ? implode(', ', $platforms) : 'Tutte';

        $prompt = <<<PROMPT
Sei un esperto di marketing digitale e analisi comportamentale.

Analizza le seguenti informazioni sull'azienda/brand e genera le BUYER PERSONAS più probabili.

## INFORMAZIONI BRAND
- Nome: {$brand->name}
- Settore: {$brand->sector}
- Descrizione: {$brand->description}
- Target dichiarato: {$brand->target_audience}
- Prodotti/Servizi: {$brand->unique_selling_points}
- Valori brand: {$brandValues}
- Tono di voce: {$brand->tone_of_voice}

## CONTESTO DAL SITO WEB
{$this->str($urlContext, 'Non disponibile')}

## PIATTAFORME ATTIVE
{$platformsList}

## IL TUO COMPITO

Genera 2-3 buyer personas REALISTICHE per questo brand e CALCOLA LA FREQUENZA OTTIMALE di posting.

### ANALISI FREQUENZA POSTING
Basandoti su:
- Tipo di brand (B2B vs B2C)
- Risorse presumibili dell'azienda
- Comportamento delle buyer personas
- Best practices del settore

Determina quanti post a settimana per ogni piattaforma. Linee guida:
- LinkedIn B2B: 2-4/settimana (qualità > quantità)
- LinkedIn B2C: 3-5/settimana
- Instagram B2B: 3-4/settimana
- Instagram B2C: 5-7/settimana (incluse stories)
- Facebook: 1-3/settimana (reach organico basso)
- Newsletter: 1-2/settimana max
- Blog: 1-2/settimana

### PER OGNI PERSONA, DEFINISCI:

1. **Profilo demografico**: età, genere, ruolo, area geografica, reddito
2. **Comportamento digitale**: quando e come usa ogni piattaforma
3. **Orari ottimali di accesso** per OGNI piattaforma (basati su statistiche reali del mercato italiano 2024-2025)
4. **Pain points**: problemi che il brand può risolvere
5. **Interessi**: topic che catturano l'attenzione
6. **Weight**: importanza relativa (0.0-1.0, totale deve fare 1.0)

IMPORTANTE sugli ORARI:
- LinkedIn B2B Italia: 7:30-8:30 mattina, 12:30-13:30 pausa pranzo (martedì-giovedì top)
- Instagram consumer: 12:00-13:00, 19:00-21:00 (weekend inclusi)
- Facebook: 13:00-16:00, weekend mattina
- Newsletter B2B: 7:00-8:00 martedì/giovedì
- Newsletter consumer: 10:00-11:00 o 20:00-21:00

## FORMATO OUTPUT (JSON valido)
{"personas": [{"name": "Nome - Ruolo", "demographics": {"age_range": "35-50", "gender": "...", "role": "...", "location": "...", "income": "..."}, "digital_behavior": {"linkedin": {"usage": "...", "best_days": [1,2,3], "best_times": ["07:30","12:30"], "content_preferences": []}, "instagram": {"usage": "...", "best_days": [4,5,6], "best_times": ["13:00","21:00"], "content_preferences": []}}, "pain_points": ["..."], "interests": ["..."], "buying_triggers": ["..."], "weight": 0.6}], "recommended_posts_per_week": {"linkedin": 3, "instagram": 5, "facebook": 2}, "frequency_rationale": "...", "scheduling_strategy": {"linkedin": {"posts_distribution": "...", "avoid": [], "optimal_slots": [{"day": 1, "time": "08:30", "priority": 1}]}, "instagram": {"posts_distribution": "...", "avoid": [], "optimal_slots": [{"day": 0, "time": "12:00", "priority": 1}]}}, "analysis_notes": "..."}

Rispondi SOLO con il JSON, senza markdown o altro testo.
PROMPT;

        try {
            $response = $this->callClaude($prompt, self::MAX_TOKENS_PERSONAS);
            $content  = $response['content'][0]['text'] ?? '';
            $personas = $this->parseJsonResponse($content);
            $personas['generated_at'] = now()->toIso8601String();
            $personas['source']       = 'ai_analysis';

            Log::info('[PERSONAS] Generated ' . count($personas['personas'] ?? []) . ' personas');

            return $personas;
        } catch (\Throwable $e) {
            Log::error("[PERSONAS] Claude call failed: {$e->getMessage()}");

            return $this->getDefaultPersonas($platforms);
        }
    }

    /**
     * Scarica contenuto dalle URL di riferimento per il contesto delle personas.
     */
    private function fetchUrlContextForPersonas(array $urls): string
    {
        $contents = [];

        foreach (array_slice($urls, 0, 5) as $url) {
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(15)
                    ->withUserAgent('NosciteCalendar/1.0')
                    ->get($url);

                if ($response->successful()) {
                    $text = strip_tags($response->body());
                    $text = preg_replace('/\s+/', ' ', $text);
                    $text = trim(mb_substr($text, 0, 5000));

                    if (strlen($text) > 100) {
                        $contents[] = "--- {$url} ---\n{$text}";
                    }
                }
            } catch (\Throwable $e) {
                Log::info("[PERSONAS] URL fetch error for {$url}: {$e->getMessage()}");
            }
        }

        return implode("\n\n", $contents);
    }

    private function callClaude(string $prompt, int $maxTokens): array
    {
        $response = Http::withHeaders([
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])
            ->timeout(120)
            ->post($this->apiUrl, [
                'model'      => self::MODEL,
                'max_tokens' => $maxTokens,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            $body = $response->body();
            Log::error("[CLAUDE] HTTP {$response->status()}: {$body}");
            throw new \RuntimeException("Claude API error: HTTP {$response->status()}");
        }

        return $response->json();
    }

    /**
     * Parsa la risposta JSON di Claude, rimuovendo eventuali backtick markdown.
     * Replica esatta della logica Python:
     *
     *   if content.startswith("```"):
     *       content = content.split("```")[1]
     *       if content.startswith("json"):
     *           content = content[4:]
     *   content = content.strip()
     */
    private function parseJsonResponse(string $content): array
    {
        if (str_starts_with($content, '```')) {
            $parts   = explode('```', $content);
            $content = $parts[1] ?? $content;
            if (str_starts_with($content, 'json')) {
                $content = substr($content, 4);
            }
        }

        $content = trim($content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Formatta le personas per il prompt.
     * Replica esatta di format_personas_for_prompt().
     */
    private function formatPersonasForPrompt(array $personasData): string
    {
        if (empty($personasData) || empty($personasData['personas'])) {
            return 'Nessuna persona specifica - usa target generico B2B';
        }

        $lines = [];
        foreach ($personasData['personas'] as $p) {
            $name       = $p['name'] ?? 'Persona';
            $demo       = $p['demographics'] ?? [];
            $weight     = $p['weight'] ?? 0;
            $painPoints = $p['pain_points'] ?? [];
            $interests  = $p['interests'] ?? [];

            $weightPct    = number_format($weight * 100, 0);
            $ageRange     = $demo['age_range'] ?? 'N/A';
            $role         = $demo['role'] ?? 'N/A';
            $location     = $demo['location'] ?? 'N/A';
            $ppStr        = ! empty($painPoints) ? implode(', ', array_slice($painPoints, 0, 3)) : 'N/A';
            $intStr       = ! empty($interests) ? implode(', ', array_slice($interests, 0, 3)) : 'N/A';

            $lines[] = <<<PERSONA

### {$name} (peso: {$weightPct}%)
- Profilo: {$ageRange}, {$role}, {$location}
- Pain points: {$ppStr}
- Interessi: {$intStr}
PERSONA;
        }

        return implode("\n", $lines);
    }

    // updateGenerationStatus rimosso — ora usa GenerationTracker::update() direttamente.

    /**
     * Logga i token usati per l'organizzazione.
     */
    private function logTokenUsage(int $organizationId, int $tokens, string $operation): void
    {
        if ($tokens <= 0) {
            return;
        }

        Log::info("[TOKENS] Org #{$organizationId}: {$tokens} tokens ({$operation})");

        // Aggiorna usage log del periodo corrente
        try {
            $now = now();
            $periodStart = $now->copy()->startOfMonth();
            $periodEnd   = $now->copy()->endOfMonth();

            \App\Domain\Organization\Models\UsageLog::updateOrCreate(
                [
                    'organization_id' => $organizationId,
                    'period_start'    => $periodStart->toDateString(),
                    'period_end'      => $periodEnd->toDateString(),
                ],
                []
            )->increment('text_tokens_used', $tokens);
        } catch (\Throwable $e) {
            Log::warning("[TOKENS] Failed to log usage: {$e->getMessage()}");
        }
    }

    /**
     * Recupera contesto RAG per la generazione (stub — sarà implementato con RagService).
     */
    private function getRagContext(int $brandId, string $searchQuery): string
    {
        // TODO: integra con RagService reale quando disponibile
        return '';
    }

    /**
     * Restituisce personas di default se non fornite.
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

    /**
     * Ritorna mix contenuti di default per piattaforma.
     */
    private function getContentMixData(array $platforms, array $brandInfo): array
    {
        $defaults = [
            'instagram' => [
                'platform'                => 'instagram',
                'source'                  => 'default',
                'supports_stories'        => true,
                'supports_reels'          => true,
                'recommended_weekly_total' => 7,
                'format_mix'              => ['post_percentage' => 45, 'story_percentage' => 35, 'reel_percentage' => 20],
                'format_weekly_count'     => ['posts' => 3, 'stories' => 3, 'reels' => 1],
                'best_content_ideas'      => ['posts' => [], 'stories' => [], 'reels' => []],
            ],
            'facebook' => [
                'platform'                => 'facebook',
                'source'                  => 'default',
                'supports_stories'        => true,
                'supports_reels'          => true,
                'recommended_weekly_total' => 5,
                'format_mix'              => ['post_percentage' => 60, 'story_percentage' => 25, 'reel_percentage' => 15],
                'format_weekly_count'     => ['posts' => 3, 'stories' => 1, 'reels' => 1],
                'best_content_ideas'      => ['posts' => [], 'stories' => [], 'reels' => []],
            ],
            'linkedin' => [
                'platform'                => 'linkedin',
                'source'                  => 'default',
                'supports_stories'        => false,
                'supports_reels'          => false,
                'recommended_weekly_total' => 4,
                'format_mix'              => ['post_percentage' => 100, 'story_percentage' => 0, 'reel_percentage' => 0],
                'format_weekly_count'     => ['posts' => 4, 'stories' => 0, 'reels' => 0],
                'best_content_ideas'      => ['posts' => []],
            ],
            'tiktok' => [
                'platform'                => 'tiktok',
                'source'                  => 'default',
                'supports_stories'        => false,
                'supports_reels'          => true,
                'recommended_weekly_total' => 5,
                'format_mix'              => ['post_percentage' => 30, 'story_percentage' => 0, 'reel_percentage' => 70],
                'format_weekly_count'     => ['posts' => 2, 'stories' => 0, 'reels' => 3],
                'best_content_ideas'      => ['posts' => [], 'reels' => []],
            ],
            'twitter' => [
                'platform'                => 'twitter',
                'source'                  => 'default',
                'supports_stories'        => false,
                'supports_reels'          => false,
                'recommended_weekly_total' => 7,
                'format_mix'              => ['post_percentage' => 100, 'story_percentage' => 0, 'reel_percentage' => 0],
                'format_weekly_count'     => ['posts' => 7, 'stories' => 0, 'reels' => 0],
                'best_content_ideas'      => ['posts' => []],
            ],
        ];

        $result = [];
        foreach ($platforms as $platform) {
            $key = strtolower($platform);
            $result[$key] = $defaults[$key] ?? [
                'platform'                => $key,
                'source'                  => 'default',
                'supports_stories'        => false,
                'supports_reels'          => false,
                'recommended_weekly_total' => 5,
                'format_mix'              => ['post_percentage' => 100, 'story_percentage' => 0, 'reel_percentage' => 0],
                'format_weekly_count'     => ['posts' => 5, 'stories' => 0, 'reels' => 0],
                'best_content_ideas'      => ['posts' => []],
            ];
        }

        return $result;
    }

    /**
     * Costruisce l'array brand_info dal modello Brand.
     */
    private function brandInfoArray(Brand $brand): array
    {
        return [
            'sector'               => $brand->sector,
            'description'          => $brand->description,
            'tone_of_voice'        => $brand->tone_of_voice,
            'brand_values'         => $brand->brand_values ?? [],
            'target_audience'      => $brand->target_audience,
            'unique_selling_points' => $brand->unique_selling_points,
        ];
    }

    /**
     * Costruisce l'array project_info dal modello Project.
     */
    private function projectInfoArray(Project $project): array
    {
        return [
            'brief'           => $project->brief,
            'objectives'      => $project->objectives ?? [],
            'target_audience' => $project->target_audience ?? null,
        ];
    }

    // ── Micro helpers ─────────────────────────────────────────

    /** Safe array get with default. */
    private function arr(array $arr, string $key, string $default = ''): string
    {
        $val = $arr[$key] ?? null;

        return is_string($val) ? $val : $default;
    }

    /**
     * Safe array get, renders like Python str() for prompt identity.
     * Python: str(['a', 'b']) → "['a', 'b']" (single quotes, spaces after commas).
     */
    private function arrJson(array $arr, string $key, string $default = '[]'): string
    {
        $val = $arr[$key] ?? null;

        if ($val === null) {
            return $default;
        }

        if (is_string($val)) {
            return $val;
        }

        // Replica Python str(list): ['value1', 'value2']
        if (is_array($val)) {
            $items = array_map(fn ($v) => "'" . str_replace("'", "\\'", (string) $v) . "'", $val);

            return '[' . implode(', ', $items) . ']';
        }

        return json_encode($val, JSON_UNESCAPED_UNICODE);
    }

    /** Return value or fallback if empty. */
    private function str(?string $value, string $fallback): string
    {
        return ($value !== null && $value !== '') ? $value : $fallback;
    }
}
