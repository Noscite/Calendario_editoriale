<?php

declare(strict_types=1);

namespace App\Domain\Generation\Services;

use App\Domain\Brand\Models\Brand;
use App\Domain\Generation\Contracts\ImageGeneratorInterface;
use App\Domain\Generation\Services\ImageModelRouter;
use App\Domain\Generation\Services\PromptBuilder;
use App\Domain\Subscription\Models\UsageLog;
use App\Domain\Post\Models\Post;
use App\Domain\Project\Models\Project;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Implementazione DALL-E del generatore di immagini.
 *
 * Replica il flusso a 2 step del Python (posts.py + openai_service.py):
 *   1. Claude genera prompt DALL-E dettagliato (generate_image_prompt)
 *   2. OpenAI genera immagine (gpt-image-1 → fallback dall-e-3)
 *   3. Salva in storage/app/public/posts/
 *
 * Supporta:
 * - Singola immagine  (generate-image endpoint)
 * - Carousel N slide   (generate-carousel endpoint) — Claude crea N prompt, DALL-E genera ogni slide
 *
 * Mappa formati come Python:
 *   format_to_dalle = { "1080x1080": "1024x1024", "1080x1920": "1024x1792", "1920x1080": "1792x1024" }
 */
final class DalleImageGenerator implements ImageGeneratorInterface
{
    // ── Format mapping (replica esatta dal Python) ───────────
    private const FORMAT_TO_DALLE = [
        '1080x1080' => '1024x1024',
        '1080x1920' => '1024x1792',   // Verticale (story/reel)
        '1920x1080' => '1792x1024',   // Orizzontale (landscape)
    ];

    private const CLAUDE_MODEL        = 'claude-sonnet-4-20250514';
    private const CLAUDE_API_URL      = 'https://api.anthropic.com/v1/messages';
    private const OPENAI_API_URL      = 'https://api.openai.com/v1/images/generations';
    private const OPENAI_CHAT_API_URL = 'https://api.openai.com/v1/chat/completions';

    private const MAX_CAROUSEL_SLIDES = 5;

    private string $anthropicKey;
    private string $openaiKey;

    public function __construct(
        private readonly PromptBuilder $promptBuilder,
    ) {
        $this->anthropicKey = config('services.anthropic.api_key', env('ANTHROPIC_API_KEY', ''));
        $this->openaiKey    = config('services.openai.api_key', env('OPENAI_API_KEY', ''));
    }

    // ══════════════════════════════════════════════════════════
    //  ImageGeneratorInterface
    // ══════════════════════════════════════════════════════════

    /**
     * Genera un'immagine per un post (replica di generate-image endpoint).
     *
     * Flusso: Claude prompt → GPT-4o enhance → gpt-image-1/DALL-E 3 → salva su disco.
     */
    public function generateImage(int $postId, ?string $visualSuggestion = null): string
    {
        $post    = Post::with('project.brand')->findOrFail($postId);
        $project = $post->project;
        $brand   = $project->brand;

        $visual = $visualSuggestion ?? $post->visual_suggestion;

        if (! $visual) {
            throw new \InvalidArgumentException('Nessun suggerimento visual disponibile. Inserisci un prompt per generare l\'immagine.');
        }

        // Aggiorna visual_suggestion se fornito
        if ($visualSuggestion) {
            $post->update(['visual_suggestion' => $visualSuggestion]);
        }

        // Step 1: Claude genera prompt DALL-E dettagliato
        [$detailedPrompt, $promptTokens] = $this->generateImagePromptFromClaude(
            postContent:      $post->content ?? '',
            platform:         $post->platform?->value ?? $post->platform ?? '',
            pillar:           $post->pillar ?? '',
            brandName:        $brand->name ?? '',
            brandSector:      $brand->sector ?? '',
            brandColors:      $brand->colors ?? '',
            visualSuggestion: $visual,
            contentType:      $post->content_type?->value ?? $post->content_type ?? 'post',
        );

        $post->update(['image_prompt' => $detailedPrompt]);

        // Step 2: GPT-4o enhance prompt (come openai_service._enhance_prompt_with_gpt4)
        $enhancedPrompt = $this->enhancePromptWithGpt4($detailedPrompt);

        // Step 3: Router seleziona modello in base a piano + pillar, fallback DALL-E 3 immutato
        $size       = '1024x1024'; // Default per feed
        $routed     = app(ImageModelRouter::class)->selectForPost($post);
        $imageUrl   = $this->callDalle($enhancedPrompt, $size, $routed['openai_model'], $routed['quality']);
        Log::info('[ROUTER] Selected model', ['post_id' => $post->id, 'plan' => $routed['plan'], 'pillar' => $post->pillar, 'tier' => $routed['tier'], 'model' => $routed['openai_model'], 'quality' => $routed['quality'], 'cost_est' => $routed['estimated_cost'], 'hero' => $routed['hero_override']]);

        // Step 4: Salva su disco
        $savedPath = $this->saveImageToDisk($imageUrl, $post->id);
        $post->update(['image_url' => $savedPath]);

        // Usage tracking
        if ($brand) {
            $this->trackUsage($brand->organization_id, 1, $promptTokens);
        }

        return $savedPath;
    }

    /**
     * Genera un prompt immagine DALL-E ottimizzato da un post.
     */
    public function generateImagePrompt(int $postId): string
    {
        $post  = Post::with('project.brand')->findOrFail($postId);
        $brand = $post->project->brand;

        [$prompt] = $this->generateImagePromptFromClaude(
            postContent:      $post->content ?? '',
            platform:         $post->platform?->value ?? $post->platform ?? '',
            pillar:           $post->pillar ?? '',
            brandName:        $brand->name ?? '',
            brandSector:      $brand->sector ?? '',
            brandColors:      $brand->colors ?? '',
            visualSuggestion: $post->visual_suggestion ?? '',
            contentType:      $post->content_type?->value ?? $post->content_type ?? 'post',
        );

        return $prompt;
    }

    /**
     * Genera immagini carousel multi-slide (replica di generate-carousel endpoint).
     *
     * Flusso:
     *  - Se carousel (N>1): Claude genera N prompt distinti → DALL-E genera ogni slide
     *  - Se singola: Claude genera 1 prompt → DALL-E genera
     *  - Mappa formati: format_to_dalle
     *  - Salva su disco
     */
    public function generateCarouselImages(int $postId, array $params): array
    {
        $post    = Post::with('project.brand')->findOrFail($postId);
        $project = $post->project;
        $brand   = $project->brand;

        $visualSuggestion = $params['visual_suggestion'] ?? $post->visual_suggestion ?? '';
        $imageFormat      = $params['image_format'] ?? '1080x1080';
        $isCarousel       = $params['is_carousel'] ?? false;
        $numSlides        = $isCarousel
            ? min(max($params['num_slides'] ?? 1, 1), self::MAX_CAROUSEL_SLIDES)
            : 1;

        // Mappa formato a dimensioni DALL-E
        $dalleSize = self::FORMAT_TO_DALLE[$imageFormat] ?? '1024x1024';

        // Genera prompt con Claude
        if ($isCarousel && $numSlides > 1) {
            $prompts = $this->generateCarouselPrompts(
                postContent:      $post->content ?? '',
                visualSuggestion: $visualSuggestion,
                brandName:        $brand->name ?? '',
                brandSector:      $brand->sector ?? '',
                imageFormat:      $imageFormat,
                numSlides:        $numSlides,
            );
        } else {
            $prompts = [$this->generateSingleImagePrompt(
                postContent:      $post->content ?? '',
                visualSuggestion: $visualSuggestion,
                brandName:        $brand->name ?? '',
                brandSector:      $brand->sector ?? '',
                imageFormat:      $imageFormat,
            )];
        }

        // Genera immagini con DALL-E
        $generatedImages = [];

        foreach ($prompts as $i => $prompt) {
            try {
                $enhancedPrompt = $this->enhancePromptWithGpt4($prompt);
                $rawUrl         = $this->callDalle($enhancedPrompt, $dalleSize);
                $savedPath      = $this->saveImageToDisk($rawUrl, $post->id, "carousel_{$i}");

                $generatedImages[] = $savedPath;
            } catch (\Throwable $e) {
                Log::error("[DALLE] Errore generazione immagine {$i}: {$e->getMessage()}");
            }
        }

        // Usage tracking
        if (! empty($generatedImages) && $brand) {
            $this->trackUsage($brand->organization_id, count($generatedImages), 0);
        }

        // Aggiorna post (come il Python)
        $updateData = [
            'image_format' => $imageFormat,
            'is_carousel'  => $isCarousel,
        ];

        if ($isCarousel) {
            $updateData['carousel_images']  = $generatedImages;
            $updateData['carousel_prompts'] = $prompts;
            $updateData['image_url']        = $generatedImages[0] ?? null;
        } else {
            $updateData['image_url']   = $generatedImages[0] ?? null;
            $updateData['image_prompt'] = $prompts[0] ?? null;
        }

        $post->update($updateData);

        return [
            'images'       => $generatedImages,
            'prompts'      => $prompts,
            'image_format' => $imageFormat,
            'is_carousel'  => $isCarousel,
        ];
    }

    // ══════════════════════════════════════════════════════════
    //  Claude prompt generation (replica di claude_service.generate_image_prompt)
    // ══════════════════════════════════════════════════════════

    /**
     * Genera prompt DALL-E via Claude.
     * Replica esatta di generate_image_prompt() da claude_service.py.
     *
     * @return array{0: string, 1: int} [prompt, tokens]
     */
    private function generateImagePromptFromClaude(
        string $postContent,
        string $platform,
        string $pillar,
        string $brandName,
        string $brandSector,
        string $brandColors,
        string $visualSuggestion,
        string $contentType = 'post',
    ): array {
        // Delegato a PromptBuilder per single source of truth
        $prompt = $this->promptBuilder->buildImagePrompt(
            postContent:      $postContent,
            platform:         $platform,
            pillar:           $pillar,
            brandName:        $brandName,
            brandSector:      $brandSector,
            brandColors:      $brandColors,
            visualSuggestion: $visualSuggestion,
            contentType:      $contentType,
        );

        try {
            $response = $this->callClaude($prompt, 800);

            $inputTokens  = $response['usage']['input_tokens'] ?? 0;
            $outputTokens = $response['usage']['output_tokens'] ?? 0;
            $tokens       = $inputTokens + $outputTokens;

            Log::info("[CLAUDE] Image prompt tokens: {$tokens}");

            $text = trim($response['content'][0]['text'] ?? '');

            return [$text, $tokens];
        } catch (\Throwable $e) {
            Log::error("[CLAUDE] generate_image_prompt error: {$e->getMessage()}");

            return ["Professional {$brandSector} business image, modern and clean style", 0];
        }
    }

    /**
     * Genera N prompt per carousel via Claude.
     * Replica della logica carousel in posts.py → generate_carousel_images().
     *
     * @return string[]
     */
    private function generateCarouselPrompts(
        string $postContent,
        string $visualSuggestion,
        string $brandName,
        string $brandSector,
        string $imageFormat,
        int    $numSlides,
    ): array {
        $formatLabel = match (true) {
            str_contains($imageFormat, '1920') => 'verticale',
            $imageFormat === '1080x1080'       => 'quadrato',
            default                            => 'orizzontale',
        };

        $prompt = <<<PROMPT
Genera {$numSlides} prompt DALL-E per un carosello Instagram.

CONTENUTO POST:
{$postContent}

SUGGERIMENTO VISUAL:
{$visualSuggestion}

BRAND: {$brandName}
SETTORE: {$brandSector}
FORMATO: {$imageFormat} ({$formatLabel})

ISTRUZIONI:
1. Ogni slide deve essere collegata ma avere un focus diverso
2. La prima slide deve catturare l'attenzione (hook visivo)
3. Le slide centrali sviluppano il concetto
4. L'ultima slide può avere una CTA visiva
5. Stile coerente tra tutte le slide
6. NO TESTO nelle immagini
7. Prompt in inglese, dettagliati

Rispondi SOLO con un JSON array di {$numSlides} prompt:
["prompt slide 1", "prompt slide 2", ...]
PROMPT;

        try {
            $response = $this->callClaude($prompt, 2000);
            $content  = trim($response['content'][0]['text'] ?? '');

            // Estrai JSON array — regex come il Python
            if (preg_match('/\[[\s\S]*\]/', $content, $matches)) {
                $prompts = json_decode($matches[0], true);
                if (is_array($prompts) && ! empty($prompts)) {
                    return $prompts;
                }
            }
        } catch (\Throwable $e) {
            Log::error("[CLAUDE] Carousel prompt error: {$e->getMessage()}");
        }

        // Fallback: usa visual_suggestion per tutte le slide
        return array_fill(0, $numSlides, $visualSuggestion);
    }

    /**
     * Genera 1 prompt per singola immagine via Claude.
     * Replica della logica single-image in posts.py → generate_carousel_images() (branch non-carousel).
     */
    private function generateSingleImagePrompt(
        string $postContent,
        string $visualSuggestion,
        string $brandName,
        string $brandSector,
        string $imageFormat,
    ): string {
        $prompt = <<<PROMPT
Genera un prompt DALL-E dettagliato per questa immagine.

CONTENUTO POST:
{$postContent}

SUGGERIMENTO VISUAL:
{$visualSuggestion}

BRAND: {$brandName}
SETTORE: {$brandSector}
FORMATO: {$imageFormat}

ISTRUZIONI:
- Prompt in inglese, molto dettagliato
- Specifica stile, colori, composizione
- NO TESTO nell'immagine
- Adatto per social media professionale

Rispondi SOLO con il prompt, niente altro.
PROMPT;

        try {
            $response = $this->callClaude($prompt, 500);

            return trim($response['content'][0]['text'] ?? $visualSuggestion);
        } catch (\Throwable $e) {
            Log::error("[CLAUDE] Single image prompt error: {$e->getMessage()}");

            return $visualSuggestion;
        }
    }

    // ══════════════════════════════════════════════════════════
    //  OpenAI — GPT-4o enhance + DALL-E/gpt-image-1
    // ══════════════════════════════════════════════════════════

    /**
     * Enhance prompt con GPT-4o (replica di openai_service._enhance_prompt_with_gpt4).
     */
    private function enhancePromptWithGpt4(string $originalPrompt): string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->openaiKey}",
                'Content-Type'  => 'application/json',
            ])
                ->timeout(30)
                ->post(self::OPENAI_CHAT_API_URL, [
                    'model'       => 'gpt-4o',
                    'messages'    => [
                        [
                            'role'    => 'system',
                            'content' => "Sei un esperto di prompt engineering per generazione immagini AI.\n"
                                . "Il tuo compito è trasformare descrizioni semplici in prompt dettagliati e professionali per DALL-E/GPT-Image.\n\n"
                                . "REGOLE:\n"
                                . "- Crea prompt in inglese\n"
                                . "- Sii molto specifico su composizione, colori, stile, illuminazione\n"
                                . "- Per post social media, crea layout tipo infografica quando appropriato\n"
                                . "- Specifica lo stile artistico (fotorealistico, illustrazione, flat design, ecc.)\n"
                                . "- NON includere mai testo/scritte nell'immagine a meno che non sia esplicitamente richiesto\n"
                                . "- Rispondi SOLO con il prompt, niente altro",
                        ],
                        [
                            'role'    => 'user',
                            'content' => "Crea un prompt dettagliato per questa immagine social media:\n\n{$originalPrompt}",
                        ],
                    ],
                    'max_tokens'  => 500,
                    'temperature' => 0.7,
                ]);

            if ($response->successful()) {
                $text = $response->json('choices.0.message.content');

                return trim($text) ?: $originalPrompt;
            }
        } catch (\Throwable $e) {
            Log::warning("[GPT4] Prompt enhancement failed: {$e->getMessage()}");
        }

        return $originalPrompt;
    }

    /**
     * Genera immagine con gpt-image-1 → fallback DALL-E 3.
     * Replica esatta di openai_service.generate_image().
     *
     * @return string  URL o data:image/png;base64,... string
     */
    private function callDalle(
        string $prompt,
        string $size = '1024x1024',
        string $openaiModel = 'gpt-image-1',
        string $quality = 'high',
    ): string {
        // Tentativo 1: modello selezionato dal router
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->openaiKey}",
                'Content-Type'  => 'application/json',
            ])
                ->timeout(120)
                ->post(self::OPENAI_API_URL, [
                    'model'   => $openaiModel,
                    'prompt'  => $prompt,
                    'size'    => $size,
                    'quality' => $quality,
                    'n'       => 1,
                ]);

            if ($response->successful()) {
                $data = $response->json('data.0');

                if (! empty($data['b64_json'])) {
                    return "data:image/png;base64,{$data['b64_json']}";
                }

                return $data['url'] ?? '';
            }

            Log::warning("[DALLE] {$openaiModel} failed ({$response->status()}), falling back to DALL-E 3");
        } catch (\Throwable $e) {
            Log::warning("[DALLE] {$openaiModel} failed, falling back to DALL-E 3: {$e->getMessage()}");
        }

        // Tentativo 2: DALL-E 3 (fallback)
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->openaiKey}",
            'Content-Type'  => 'application/json',
        ])
            ->timeout(120)
            ->post(self::OPENAI_API_URL, [
                'model'   => 'dall-e-3',
                'prompt'  => $prompt,
                'size'    => $size,
                'quality' => 'standard',
                'style'   => 'vivid',
                'n'       => 1,
            ]);

        if ($response->successful()) {
            return $response->json('data.0.url') ?? '';
        }

        throw new \RuntimeException("DALL-E API error: HTTP {$response->status()}");
    }

    // ══════════════════════════════════════════════════════════
    //  Storage & utility helpers
    // ══════════════════════════════════════════════════════════

    /**
     * Salva immagine su disco (storage/app/public/posts/).
     * Supporta sia base64 (gpt-image-1) che URL (DALL-E 3).
     * Replica della logica di salvataggio in posts.py.
     *
     * @return string  Percorso pubblico relativo (es. /storage/posts/123_abc.png)
     */
    private function saveImageToDisk(string $imageData, int $postId, string $suffix = ''): string
    {
        $suffixPart = $suffix ? "_{$suffix}" : '';
        $filename   = "{$postId}{$suffixPart}_" . Str::random(8) . '.png';
        $disk = Storage::disk('public');

        // Assicurati che la directory esista
        $disk->makeDirectory('posts');

        if (str_starts_with($imageData, 'data:image')) {
            // Base64 da gpt-image-1
            $base64 = explode(',', $imageData, 2)[1] ?? '';
            $disk->put("posts/{$filename}", base64_decode($base64));
        } else {
            // URL da DALL-E 3 — scarica
            try {
                $response = Http::timeout(30)->get($imageData);

                if ($response->successful()) {
                    $disk->put("posts/{$filename}", $response->body());
                } else {
                    // Fallback: salva URL come riferimento
                    return $imageData;
                }
            } catch (\Throwable $e) {
                Log::warning("[DALLE] Cannot download image: {$e->getMessage()}");

                return $imageData;
            }
        }

        return "/storage/posts/{$filename}";
    }

    /**
     * Chiama Claude API (per prompt generation).
     */
    private function callClaude(string $prompt, int $maxTokens): array
    {
        $response = Http::withHeaders([
            'x-api-key'         => $this->anthropicKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])
            ->timeout(60)
            ->post(self::CLAUDE_API_URL, [
                'model'      => self::CLAUDE_MODEL,
                'max_tokens' => $maxTokens,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException("Claude API error: HTTP {$response->status()}");
        }

        return $response->json();
    }

    /**
     * Tracka usage immagini + token.
     */
    private function trackUsage(int $organizationId, int $images, int $tokens): void
    {
        try {
            $usage = UsageLog::firstOrCreate(
                [
                    'organization_id' => $organizationId,
                    'period_start'    => now()->startOfMonth()->toDateString(),
                ],
                [
                    'period_end' => now()->endOfMonth()->toDateString(),
                ],
            );

            if ($images > 0) {
                $usage->increment('images_generated', $images);
            }
            if ($tokens > 0) {
                $usage->increment('text_tokens_used', $tokens);
            }

            Log::info("[DALLE] Usage tracked: org={$organizationId}, images={$images}, tokens={$tokens}");
        } catch (\Throwable $e) {
            Log::warning("[DALLE] Usage tracking error: {$e->getMessage()}");
        }
    }
}
