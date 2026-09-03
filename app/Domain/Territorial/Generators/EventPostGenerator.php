<?php

declare(strict_types=1);

namespace App\Domain\Territorial\Generators;

use App\Domain\AiUsage\Data\UsageRecord;
use App\Domain\Brand\Models\Brand;
use App\Domain\Generation\Services\AiGenerationSettingsService;
use App\Domain\Generation\Services\AnthropicApiClient;
use App\Domain\Post\Enums\Platform;
use App\Domain\Territorial\Models\TerritorialEvent;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EventPostGenerator
{
    public function __construct(
        private AnthropicApiClient $apiClient,
        private readonly AiGenerationSettingsService $aiSettings,
    ) {}

    /**
     * Genera contenuto post per un evento e una fase specifica.
     * Phase: 'announcement' (T-3) | 'recap' (T+1)
     *
     * @return array{title:string, content:string, hashtags:string, cta:string}
     */
    public function generate(TerritorialEvent $event, Brand $brand, string $phase, Platform $platform): array
    {
        $prompt = $this->buildPrompt($event, $brand, $phase, $platform);
        $params = $this->prepare($brand, AiGenerationSettingsService::STEP_EVENT_POST);

        $response = $this->apiClient->call(
            prompt:      $prompt,
            maxTokens:   $params->maxTokens,
            model:       $params->model,
            purpose:     AiGenerationSettingsService::STEP_EVENT_POST,
            temperature: $params->temperature,
            topP:        $params->topP,
            topK:        $params->topK,
        );

        return $this->parseResponse($response);
    }

    private function prepare(Brand $brand, string $step): \App\Domain\Generation\Data\AiGenerationParams
    {
        $this->apiClient = $this->apiClient->withBrandOrSystemFallback($brand);
        return $this->aiSettings->resolve($brand, $step);
    }

    /**
     * Variante di generate() che ritorna anche UsageRecord per tracking costi.
     *
     * @return array{content: array{title:string, content:string, hashtags:string, cta:string}, usage: UsageRecord}
     */
    public function generateWithUsage(TerritorialEvent $event, Brand $brand, string $phase, Platform $platform): array
    {
        $prompt = $this->buildPrompt($event, $brand, $phase, $platform);
        $params = $this->prepare($brand, AiGenerationSettingsService::STEP_EVENT_POST);

        $result = $this->apiClient->callWithUsage(
            prompt:      $prompt,
            maxTokens:   $params->maxTokens,
            model:       $params->model,
            purpose:     AiGenerationSettingsService::STEP_EVENT_POST,
            temperature: $params->temperature,
            topP:        $params->topP,
            topK:        $params->topK,
        );

        return [
            'content' => $this->parseResponse($result['response']),
            'usage'   => $result['usage'],
        ];
    }

    private function buildPrompt(TerritorialEvent $event, Brand $brand, string $phase, Platform $platform): string
    {
        $brandSection = "Brand: {$brand->name}\nSettore: {$brand->sector}";
        if ($brand->tone_of_voice) {
            $brandSection .= "\nTono di voce: {$brand->tone_of_voice}";
        }

        $when  = $event->start_at?->locale('it')->isoFormat('dddd D MMMM YYYY [alle ore] HH:mm') ?? 'data da definire';
        $where = trim(implode(', ', array_filter([$event->venue_name, $event->city, $event->province])));

        $eventSection = "Titolo evento: {$event->title}\nQuando: {$when}\nDove: {$where}";
        if ($event->description) {
            $eventSection .= "\nDescrizione: " . mb_substr($event->description, 0, 800);
        }
        if (! empty($event->categories)) {
            $eventSection .= "\nCategorie: " . implode(', ', $event->categories);
        }

        $phaseInstr = match ($phase) {
            'announcement' => 'Genera un post di ANTICIPAZIONE da pubblicare 3 giorni prima dell\'evento. Tono di richiamo concreto: la data è vicina, è il momento di prenotarsi / partecipare. Includi data, orario, luogo. Spinge all\'azione senza essere insistente.',
            'recap'        => 'Genera un post di RECAP da pubblicare il giorno dopo l\'evento. Ringrazia partecipanti, racconta un momento o un\'emozione, invita a tornare e a seguire i prossimi eventi della Pro Loco.',
            default        => throw new \InvalidArgumentException("Phase non valida: {$phase}"),
        };

        $platformLimits = match ($platform) {
            Platform::Instagram      => 'Instagram (max 2200 caratteri, hashtag importanti, emoji ok ma non eccessive)',
            Platform::Facebook       => 'Facebook (max ~600 caratteri ottimali, hashtag minimi, tono conversazionale)',
            Platform::LinkedIn       => 'LinkedIn (max ~1300 caratteri, tono professionale ma caldo, poche hashtag)',
            Platform::GoogleBusiness => 'Google Business Profile (max ~1500 caratteri, focus su info pratiche, niente hashtag)',
        };

        return <<<PROMPT
Sei un copywriter specializzato in promozione turistico-territoriale italiana per Pro Loco e UNPLI.
Registro: caldo, autentico, vicino al territorio. Niente toni glossy o da agenzia. Niente claim esagerati.

{$brandSection}

{$eventSection}

Piattaforma di destinazione: {$platformLimits}.

{$phaseInstr}

Includi 5-7 hashtag locali e tematici (es. #nomecomune, #nomeprovincia, #lombardiadavivere, #proloco, categoria evento).

Rispondi SOLO con un oggetto JSON valido, senza alcun testo prima o dopo, in questo formato esatto:
{
  "title": "titolo breve interno per il calendario, max 80 caratteri",
  "content": "testo del post completo, già formattato per la piattaforma",
  "hashtags": "#tag1 #tag2 #tag3 #tag4 #tag5",
  "cta": "call to action breve (es. 'Salva la data', 'Tagga chi vuoi portare', 'Raccontaci com\\'è andata')"
}
PROMPT;
    }

    /**
     * Genera il post "Eventi di [mese]" — digest mensile aggregato.
     *
     * @param  \Illuminate\Support\Collection<int, TerritorialEvent>  $events
     * @return array{title:string, content:string, hashtags:string, cta:string}
     */
    public function generateMonthlyDigest(
        $events,
        Brand $brand,
        \Carbon\Carbon $monthStart,
        Platform $platform,
    ): array {
        $prompt = $this->buildMonthlyDigestPrompt($events, $brand, $monthStart, $platform);
        $params = $this->prepare($brand, AiGenerationSettingsService::STEP_EVENT_DIGEST);

        $response = $this->apiClient->call(
            prompt:      $prompt,
            maxTokens:   $params->maxTokens,
            model:       $params->model,
            purpose:     AiGenerationSettingsService::STEP_EVENT_DIGEST,
            temperature: $params->temperature,
            topP:        $params->topP,
            topK:        $params->topK,
        );

        return $this->parseResponse($response);
    }

    /**
     * Variante di generateMonthlyDigest() che ritorna anche UsageRecord.
     *
     * @param  \Illuminate\Support\Collection<int, TerritorialEvent>  $events
     * @return array{content: array{title:string, content:string, hashtags:string, cta:string}, usage: UsageRecord}
     */
    public function generateMonthlyDigestWithUsage(
        $events,
        Brand $brand,
        \Carbon\Carbon $monthStart,
        Platform $platform,
    ): array {
        $prompt = $this->buildMonthlyDigestPrompt($events, $brand, $monthStart, $platform);
        $params = $this->prepare($brand, AiGenerationSettingsService::STEP_EVENT_DIGEST);

        $result = $this->apiClient->callWithUsage(
            prompt:      $prompt,
            maxTokens:   $params->maxTokens,
            model:       $params->model,
            purpose:     AiGenerationSettingsService::STEP_EVENT_DIGEST,
            temperature: $params->temperature,
            topP:        $params->topP,
            topK:        $params->topK,
        );

        return [
            'content' => $this->parseResponse($result['response']),
            'usage'   => $result['usage'],
        ];
    }

    private function buildMonthlyDigestPrompt(
        $events,
        Brand $brand,
        \Carbon\Carbon $monthStart,
        Platform $platform,
    ): string {
        $monthName = $monthStart->locale('it')->isoFormat('MMMM YYYY');

        $eventsBlock = $events->map(function (TerritorialEvent $e) {
            $when = $e->start_at->format('d/m');
            if ($e->end_at && ! $e->start_at->isSameDay($e->end_at)) {
                $when .= '–' . $e->end_at->format('d/m');
            }
            $where = $e->city ? " a {$e->city}" : '';
            $abstract = $e->description ? ' — ' . mb_substr($e->description, 0, 120) : '';
            return "- {$e->title}{$where} ({$when}){$abstract}";
        })->join("\n");

        $platformLimits = match ($platform) {
            Platform::Instagram      => 'Instagram (max 2200 caratteri, hashtag importanti, emoji ok ma non eccessive)',
            Platform::Facebook       => 'Facebook (max ~800 caratteri ottimali, hashtag minimi, tono conversazionale)',
            Platform::LinkedIn       => 'LinkedIn (max ~1500 caratteri, tono professionale ma caldo, poche hashtag)',
            Platform::GoogleBusiness => 'Google Business Profile (max ~1500 caratteri, focus su info pratiche, niente hashtag)',
        };

        $brandSection = "Brand: {$brand->name}\nSettore: {$brand->sector}";
        if ($brand->tone_of_voice) {
            $brandSection .= "\nTono di voce: {$brand->tone_of_voice}";
        }

        return <<<PROMPT
Sei un copywriter specializzato in promozione turistico-territoriale italiana per Pro Loco e UNPLI.
Genera un post di DIGEST MENSILE per {$monthName}.

OBIETTIVO: panoramica degli eventi del mese che il pubblico locale dovrebbe segnare in calendario. Tono caldo, di paese, da chi conosce e ama il territorio.

{$brandSection}

EVENTI DEL MESE ({$events->count()} eventi):
{$eventsBlock}

Piattaforma di destinazione: {$platformLimits}.

ISTRUZIONI:
- Apertura accattivante che presenta il mese (es. "{$monthName} porta a {$brand->name}…").
- Elenca gli eventi in modo discorsivo (no bullet point freddi). Per ognuno dai: nome, data, luogo, mezza riga di sapore.
- Chiusura con invito a salvare il post / mettere in calendario / seguirci per gli aggiornamenti.
- Se ci sono rassegne lunghe (multi-mese), nominale ma chiarisci "in corso" / "fino a [data]".
- Includi 5-7 hashtag locali e tematici.

Rispondi SOLO con un oggetto JSON valido, senza alcun testo prima o dopo, in questo formato esatto:
{
  "title": "titolo breve interno per il calendario, max 80 caratteri",
  "content": "testo del post completo, già formattato per la piattaforma",
  "hashtags": "#tag1 #tag2 #tag3 #tag4 #tag5",
  "cta": "call to action breve (es. 'Salva il post', 'Segnati le date', 'Seguici per gli aggiornamenti')"
}
PROMPT;
    }

    /**
     * @return array{title:string, content:string, hashtags:string, cta:string}
     */
    private function parseResponse(array $response): array
    {
        // Anthropic API response shape: { content: [{ type: 'text', text: '...' }], ... }
        $text = $response['content'][0]['text'] ?? '';
        $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $text) ?? '');

        $parsed = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('[TERRITORIAL] EventPostGenerator JSON parse failed', [
                'error' => json_last_error_msg(),
                'text'  => mb_substr($text, 0, 500),
            ]);
            throw new RuntimeException('AI response not valid JSON: ' . json_last_error_msg());
        }

        // Validazione campi minimi
        foreach (['title', 'content', 'hashtags', 'cta'] as $key) {
            if (! isset($parsed[$key]) || ! is_string($parsed[$key])) {
                throw new RuntimeException("AI response missing required field: {$key}");
            }
        }

        return $parsed;
    }
}
