<?php

declare(strict_types=1);

namespace App\Domain\Review\Services;

use App\Domain\Brand\Models\Brand;
use App\Domain\Document\Models\BrandDocument;
use App\Domain\Generation\Services\AnthropicApiClient;
use App\Domain\Review\Contracts\OntologyBootstrapServiceInterface;
use App\Domain\Review\Models\Review;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Bootstrap dell'ontologia dei topic per un brand.
 *
 * Costruisce un prompt per Claude Sonnet con il contesto del brand
 * (settore, descrizione, key_topics dai documenti KB e sample di review)
 * e ottiene 8-15 topic_id pertinenti al settore. Salva il risultato
 * su brands.review_ontology e include sempre "altro" come fallback.
 */
final class OntologyBootstrapService implements OntologyBootstrapServiceInterface
{
    public const MODEL       = 'claude-sonnet-4-20250514';
    private const MAX_TOKENS  = 1500;
    private const MAX_DOC_TOPICS = 20;
    private const MAX_REVIEW_SAMPLE = 10;

    public function __construct(
        private readonly AnthropicApiClient $apiClient,
    ) {
    }

    /**
     * @return array<int, array{id:string,label:string,description:string}>
     */
    public function bootstrapForBrand(Brand $brand): array
    {
        $client = $this->apiClient->withBrand($brand);

        $context      = $this->buildBrandContext($brand);
        $systemPrompt = $this->buildSystemPrompt();

        $response = $client->call(
            prompt: $context,
            maxTokens: self::MAX_TOKENS,
            model: self::MODEL,
            systemPrompt: $systemPrompt,
        );

        $text = $response['content'][0]['text'] ?? '';
        if ($text === '') {
            throw new RuntimeException('Empty response from Claude ontology bootstrap');
        }

        try {
            $parsed = $client->parseJsonResponse($text);
        } catch (\JsonException $e) {
            Log::warning('[REVIEW_ONTOLOGY] JSON malformato', ['raw' => $text]);
            throw new RuntimeException('Invalid JSON from ontology bootstrap: ' . $e->getMessage(), 0, $e);
        }

        $ontology = $this->normalize($parsed);
        $ontology = $this->ensureAltro($ontology);

        $brand->update(['review_ontology' => $ontology]);

        Log::info('[REVIEW_ONTOLOGY] Generata', [
            'brand_id' => $brand->id,
            'count'    => count($ontology),
        ]);

        return $ontology;
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
Sei un esperto di customer experience e tassonomie per PMI italiane.
Il tuo compito: dato un brand (settore, descrizione, key_topics dei
documenti KB e sample di recensioni esistenti), proporre un'ontologia di
8-15 topic pertinenti al settore per categorizzare le recensioni dei
clienti.

REGOLE:
- I topic devono essere SPECIFICI del settore, non generici.
- Ogni topic ha: id (snake_case, lowercase, in inglese, max 30 char),
  label (italiano, max 50 char), description (italiano, max 150 char).
- Coprire le aree principali tipiche del settore (es. ristorante:
  food_quality, service_speed, ambience, value_for_money, cleanliness,
  staff_friendliness, ...).
- Evitare topic ridondanti o sovrapposti.

Rispondi SEMPRE e SOLO con JSON valido (un array di oggetti), senza
markdown, senza testo prima o dopo:

[
  {"id": "food_quality", "label": "Qualità del cibo", "description": "Recensioni che parlano del gusto, freschezza e cura dei piatti."},
  ...
]
PROMPT;
    }

    private function buildBrandContext(Brand $brand): string
    {
        $name        = $brand->name;
        $sector      = $brand->sector ?? 'non specificato';
        $description = $brand->description ?? '(nessuna descrizione)';

        $docTopics = $this->extractDocumentTopics($brand);
        $docTopicsText = $docTopics === []
            ? '(nessun documento KB processato)'
            : implode(', ', $docTopics);

        $reviewSample = $this->extractReviewSample($brand);
        $reviewSampleText = $reviewSample === []
            ? '(nessuna recensione disponibile)'
            : implode("\n---\n", $reviewSample);

        return <<<PROMPT
BRAND: {$name}
SETTORE: {$sector}
DESCRIZIONE: {$description}

TOPIC EMERSI DAI DOCUMENTI KB (top {$this->maxDocTopics()}):
{$docTopicsText}

SAMPLE DI RECENSIONI ESISTENTI (max {$this->maxReviewSample()}):
{$reviewSampleText}

Genera l'ontologia JSON come da istruzioni.
PROMPT;
    }

    private function maxDocTopics(): int
    {
        return self::MAX_DOC_TOPICS;
    }

    private function maxReviewSample(): int
    {
        return self::MAX_REVIEW_SAMPLE;
    }

    /**
     * @return array<int, string>
     */
    private function extractDocumentTopics(Brand $brand): array
    {
        $topics = [];
        $docs   = BrandDocument::where('brand_id', $brand->id)
            ->where('analysis_status', 'completed')
            ->whereNotNull('key_topics')
            ->limit(50)
            ->get(['key_topics']);

        foreach ($docs as $doc) {
            $list = is_array($doc->key_topics) ? $doc->key_topics : [];
            foreach ($list as $t) {
                if (is_string($t) && $t !== '') {
                    $topics[] = $t;
                }
            }
        }

        $unique = array_values(array_unique($topics));
        return array_slice($unique, 0, self::MAX_DOC_TOPICS);
    }

    /**
     * @return array<int, string>
     */
    private function extractReviewSample(Brand $brand): array
    {
        // Sample variato per rating: prova a prendere 2 per ogni rating 1..5
        $samples = [];
        for ($rating = 1; $rating <= 5; $rating++) {
            $rows = Review::withoutGlobalScope('organization')
                ->where('brand_id', $brand->id)
                ->where('rating', $rating)
                ->whereNotNull('comment')
                ->where('comment', '!=', '')
                ->orderBy('review_created_at', 'desc')
                ->limit(2)
                ->get(['rating', 'comment']);

            foreach ($rows as $r) {
                $samples[] = "[{$r->rating}★] " . mb_substr((string) $r->comment, 0, 300);
                if (count($samples) >= self::MAX_REVIEW_SAMPLE) {
                    return $samples;
                }
            }
        }

        return $samples;
    }

    /**
     * @param  array<mixed>  $parsed
     * @return array<int, array{id:string,label:string,description:string}>
     */
    private function normalize(array $parsed): array
    {
        $out = [];
        foreach ($parsed as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $id    = isset($entry['id']) ? (string) $entry['id'] : '';
            $label = isset($entry['label']) ? (string) $entry['label'] : '';
            $desc  = isset($entry['description']) ? (string) $entry['description'] : '';
            if ($id === '' || $label === '') {
                continue;
            }
            $out[] = [
                'id'          => mb_substr($this->slugify($id), 0, 30),
                'label'       => mb_substr($label, 0, 50),
                'description' => mb_substr($desc, 0, 150),
            ];
        }

        return $out;
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;
        return trim($value, '_');
    }

    /**
     * @param  array<int, array{id:string,label:string,description:string}>  $ontology
     * @return array<int, array{id:string,label:string,description:string}>
     */
    private function ensureAltro(array $ontology): array
    {
        foreach ($ontology as $entry) {
            if ($entry['id'] === 'altro') {
                return $ontology;
            }
        }

        $ontology[] = [
            'id'          => 'altro',
            'label'       => 'Altro',
            'description' => 'Recensioni non riconducibili agli altri topic.',
        ];

        return $ontology;
    }
}
