<?php

declare(strict_types=1);

namespace App\Domain\Territorial\Generators;

use App\Domain\Brand\Models\Brand;
use App\Domain\Generation\Services\AnthropicApiClient;
use App\Domain\Post\Enums\Platform;
use App\Domain\Territorial\Models\TerritorialEvent;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EventPostGenerator
{
    public function __construct(
        private readonly AnthropicApiClient $apiClient,
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

        $response = $this->apiClient->call(
            prompt:    $prompt,
            maxTokens: 1024,
            model:     config('services.anthropic.opus_model', 'claude-opus-4-7'),
        );

        return $this->parseResponse($response);
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
