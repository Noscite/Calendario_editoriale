<?php

declare(strict_types=1);

namespace App\Domain\Review\Services;

use App\Domain\Generation\Services\AnthropicApiClient;
use App\Domain\Review\Models\Review;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Scoring automatico di una review via Claude Haiku.
 *
 * Output strutturato JSON: sentiment, urgency, topics, is_fake_suspect,
 * marketing_opportunity, rationale. Validato e normalizzato a default
 * sicuri se il modello restituisce valori non aderenti al contratto.
 */
class ReviewScoringService
{
    public const MODEL      = 'claude-haiku-4-5-20251001';
    private const MAX_TOKENS = 800;

    public function __construct(
        private readonly AnthropicApiClient $apiClient,
    ) {
    }

    /**
     * @return array{sentiment:string,urgency:string,topics:array<int,string>,is_fake_suspect:bool,marketing_opportunity:string,rationale:string}
     */
    public function score(Review $review): array
    {
        $brand    = $review->brand;
        $ontology = is_array($brand?->review_ontology) ? $brand->review_ontology : [];

        $client = $this->apiClient->withBrand($brand);

        $systemPrompt = $this->buildSystemPrompt($ontology);
        $userPrompt   = $this->buildUserPrompt($review);

        $response = $client->call(
            prompt: $userPrompt,
            maxTokens: self::MAX_TOKENS,
            model: self::MODEL,
            systemPrompt: $systemPrompt,
        );

        $text = $response['content'][0]['text'] ?? '';
        if ($text === '') {
            throw new RuntimeException('Empty response from Claude scoring');
        }

        try {
            $parsed = $client->parseJsonResponse($text);
        } catch (\JsonException $e) {
            Log::warning('[REVIEW_SCORE] JSON malformato', ['raw' => $text, 'error' => $e->getMessage()]);
            throw new RuntimeException('Invalid JSON in Claude scoring response: ' . $e->getMessage(), 0, $e);
        }

        return $this->validateAndNormalize($parsed);
    }

    /**
     * @param  array<int, array<string,string>>  $ontology
     */
    private function buildSystemPrompt(array $ontology): string
    {
        $ontologyJson = empty($ontology)
            ? '[]  // Ontologia vuota, usa "altro" per topics'
            : json_encode($ontology, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
Sei un analista esperto di customer feedback per PMI italiane. Analizzi
recensioni Google e produci uno scoring strutturato.

Rispondi SEMPRE e SOLO con JSON valido, senza markdown, senza testo prima
o dopo. Il JSON deve avere ESATTAMENTE questa struttura:

{
  "sentiment": "positive|neutral|negative|mixed",
  "urgency": "low|medium|high",
  "topics": ["topic_id1", "topic_id2"],
  "is_fake_suspect": false,
  "marketing_opportunity": "none|recovery|advocacy|upsell|testimonial",
  "rationale": "Breve spiegazione (max 200 caratteri)"
}

REGOLE:
- urgency=high SOLO per: minacce legali, accuse di frode, problemi sicurezza,
  rischio reputazionale grave (es. tema di salute, diffamazione)
- is_fake_suspect=true SOLO per indizi forti: testo generico/copia-incolla,
  reviewer senza foto/storia, contenuto incoerente con il business
- marketing_opportunity:
  * recovery: critica recuperabile (problema specifico, cliente reale, tono
    civile)
  * advocacy: 5★ con dettaglio, cliente entusiasta, potenziale ambassador
  * upsell: soddisfatto che menziona altri bisogni o servizi correlati
  * testimonial: 5★ con storia/risultato concreto, perfetta per case study
  * none: 1-2★ generiche, 3★ tiepide, fake suspect, contenuto irrilevante
- topics: array di topic_id presi DALL'ONTOLOGIA fornita sotto. Se nessun
  topic dell'ontologia è pertinente, usa ["altro"]. Mai topic inventati.

ONTOLOGIA TOPIC DEL BRAND:
{$ontologyJson}
PROMPT;
    }

    private function buildUserPrompt(Review $review): string
    {
        $brand     = $review->brand;
        $reviewer  = $review->reviewer_name ?? 'Anonimo';
        $rating    = $review->rating;
        $comment   = $review->comment ?? '(nessun commento, solo stelle)';
        $sector    = $brand?->sector ?? 'non specificato';
        $brandName = $brand?->name ?? 'Brand';

        return <<<PROMPT
RECENSIONE DA ANALIZZARE:

Brand: {$brandName} (settore: {$sector})
Reviewer: {$reviewer}
Rating: {$rating}/5 stelle
Commento: "{$comment}"

Restituisci il JSON di scoring.
PROMPT;
    }

    /**
     * @param  array<string,mixed>  $response
     * @return array{sentiment:string,urgency:string,topics:array<int,string>,is_fake_suspect:bool,marketing_opportunity:string,rationale:string}
     */
    private function validateAndNormalize(array $response): array
    {
        $sentiment = in_array($response['sentiment'] ?? null, ['positive', 'neutral', 'negative', 'mixed'], true)
            ? (string) $response['sentiment']
            : 'neutral';

        $urgency = in_array($response['urgency'] ?? null, ['low', 'medium', 'high'], true)
            ? (string) $response['urgency']
            : 'low';

        $topicsRaw = $response['topics'] ?? null;
        $topics    = is_array($topicsRaw)
            ? array_values(array_filter($topicsRaw, 'is_string'))
            : [];
        if ($topics === []) {
            $topics = ['altro'];
        }

        $opportunity = in_array(
            $response['marketing_opportunity'] ?? null,
            ['none', 'recovery', 'advocacy', 'upsell', 'testimonial'],
            true,
        )
            ? (string) $response['marketing_opportunity']
            : 'none';

        return [
            'sentiment'             => $sentiment,
            'urgency'               => $urgency,
            'topics'                => $topics,
            'is_fake_suspect'       => (bool) ($response['is_fake_suspect'] ?? false),
            'marketing_opportunity' => $opportunity,
            'rationale'             => mb_substr((string) ($response['rationale'] ?? ''), 0, 200),
        ];
    }
}
