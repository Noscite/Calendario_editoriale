<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Brand\Models\Brand;
use App\Domain\Generation\Services\AnthropicApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Voice Profiling — corrisponde a voice_profiling.py (Python).
 *
 * Implementazione reale dei profili voce brand tramite sessioni Redis
 * e analisi AI via Claude.
 *
 * Prefisso Python: /api/voice-profiling
 * Tutti gli endpoint sono PUBBLICI nel backend Python.
 *
 * Flusso:
 *   1. init()    — crea sessione Redis + genera 5 domande iniziali via Claude
 *   2. profile() — recupera stato sessione (domande + risposte)
 *   3. save()    — analizza risposte via Claude → salva profilo su Brand
 *   4. analyze() — analisi testo libero → restituisce profilo (senza salvare)
 */
final class VoiceProfilingController extends Controller
{
    private const SESSION_TTL_MINUTES = 30;

    public function __construct(
        private readonly AnthropicApiClient $apiClient,
    ) {}

    // GET /api/voice-profiling/check
    public function check(): JsonResponse
    {
        return response()->json([
            'status'  => 'available',
            'service' => 'voice-profiling',
        ]);
    }

    // POST /api/voice-profiling/brand/{brand_id}/init
    public function init(int $brandId, Request $request): JsonResponse
    {
        $brand = Brand::find($brandId);

        if (!$brand) {
            return response()->json(['error' => 'Brand non trovato'], 404);
        }

        $sessionId  = Str::uuid()->toString();
        $sessionKey = "voice_profiling:{$brandId}:{$sessionId}";

        $questions = $this->generateInitialQuestions($brand);

        $sessionData = [
            'brand_id'   => $brandId,
            'session_id' => $sessionId,
            'questions'  => $questions,
            'answers'    => [],
            'created_at' => now()->toIso8601String(),
            'step'       => 'questions',
        ];

        Cache::put($sessionKey, $sessionData, now()->addMinutes(self::SESSION_TTL_MINUTES));

        Log::info("[VOICE] Sessione inizializzata", ['brand_id' => $brandId, 'session_id' => $sessionId]);

        return response()->json([
            'brand_id'   => $brandId,
            'session_id' => $sessionId,
            'questions'  => $questions,
            'session'    => 'initialized',
        ]);
    }

    // GET /api/voice-profiling/brand/{brand_id}/profile
    public function profile(int $brandId, Request $request): JsonResponse
    {
        $sessionId  = $request->query('session_id');
        $sessionKey = "voice_profiling:{$brandId}:{$sessionId}";

        if ($sessionId) {
            $sessionData = Cache::get($sessionKey);

            if ($sessionData) {
                return response()->json([
                    'brand_id'  => $brandId,
                    'questions' => $sessionData['questions'] ?? [],
                    'answers'   => $sessionData['answers'] ?? [],
                    'step'      => $sessionData['step'] ?? 'questions',
                    'profile'   => $sessionData['profile'] ?? null,
                ]);
            }
        }

        $brand = Brand::find($brandId);
        if (!$brand) {
            return response()->json(['error' => 'Brand non trovato'], 404);
        }

        return response()->json([
            'brand_id' => $brandId,
            'profile'  => [
                'tone_of_voice'   => $brand->tone_of_voice,
                'brand_values'    => $brand->brand_values,
                'target_audience' => $brand->target_audience,
                'ai_profile'      => $brand->ai_profile,
            ],
        ]);
    }

    // POST /api/voice-profiling/brand/{brand_id}/save
    public function save(int $brandId, Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => 'nullable|string',
            'answers'    => 'nullable|array',
            'text'       => 'nullable|string',
        ]);

        $brand = Brand::find($brandId);
        if (!$brand) {
            return response()->json(['error' => 'Brand non trovato'], 404);
        }

        $answers = $data['answers'] ?? [];

        if (!empty($data['session_id'])) {
            $sessionKey  = "voice_profiling:{$brandId}:{$data['session_id']}";
            $sessionData = Cache::get($sessionKey);

            if ($sessionData) {
                $answers = array_merge($sessionData['answers'] ?? [], $answers);
            }
        }

        $inputText = $data['text'] ?? $this->formatAnswersAsText($answers);
        $profile   = $this->extractBrandProfile($brand, $inputText);

        if (!empty($profile)) {
            $brand->update([
                'tone_of_voice'   => $profile['tone_of_voice'] ?? $brand->tone_of_voice,
                'brand_values'    => $profile['brand_values'] ?? $brand->brand_values,
                'target_audience' => $profile['target_audience'] ?? $brand->target_audience,
                'ai_profile'      => json_encode($profile),
            ]);

            Log::info("[VOICE] Profilo salvato per brand #{$brandId}");
        }

        if (!empty($data['session_id'])) {
            Cache::forget("voice_profiling:{$brandId}:{$data['session_id']}");
        }

        return response()->json([
            'brand_id' => $brandId,
            'saved'    => true,
            'profile'  => $profile,
        ]);
    }

    // POST /api/voice-profiling/brand/{brand_id}/analyze
    public function analyze(int $brandId, Request $request): JsonResponse
    {
        $data = $request->validate([
            'text' => 'required|string|min:10',
        ]);

        $brand = Brand::find($brandId);
        if (!$brand) {
            return response()->json(['error' => 'Brand non trovato'], 404);
        }

        $profile = $this->extractBrandProfile($brand, $data['text']);

        return response()->json([
            'brand_id' => $brandId,
            'profile'  => $profile,
            'saved'    => false,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    //  Helpers privati
    // ──────────────────────────────────────────────────────────

    /**
     * Genera 5 domande aperte sul brand tramite Claude.
     */
    private function generateInitialQuestions(Brand $brand): array
    {
        $prompt = <<<PROMPT
Sei un consulente di brand identity. Devi fare delle domande per capire la voce e lo stile comunicativo di un brand.

## BRAND
- Nome: {$brand->name}
- Settore: {$brand->sector}
- Descrizione: {$brand->description}
- Target: {$brand->target_audience}

## COMPITO
Genera 5 domande aperte e specifiche per capire:
1. Il tono di voce del brand (formale/informale, professionale/amichevole, etc.)
2. I valori fondamentali che il brand vuole comunicare
3. Il pubblico target e come si relaziona con loro
4. Lo stile narrativo preferito
5. Come il brand vuole essere percepito rispetto ai competitor

## FORMATO OUTPUT (JSON array di stringhe)
["Domanda 1?", "Domanda 2?", "Domanda 3?", "Domanda 4?", "Domanda 5?"]

Rispondi SOLO con il JSON array, senza markdown.
PROMPT;

        try {
            $response  = $this->apiClient->call($prompt, 500);
            $content   = trim($response['content'][0]['text'] ?? '');
            $questions = $this->apiClient->parseJsonResponse($content);

            if (is_array($questions) && count($questions) >= 3) {
                return $questions;
            }
        } catch (\Throwable $e) {
            Log::warning("[VOICE] Generazione domande fallita: {$e->getMessage()}");
        }

        return [
            'Come descriveresti il tono di voce ideale per il tuo brand? (es. formale, amichevole, autorevole)',
            'Quali sono i 3 valori fondamentali che vuoi comunicare con i tuoi contenuti?',
            'Come vuoi che i tuoi clienti si sentano dopo aver interagito con i tuoi contenuti?',
            'Cosa ti differenzia dai tuoi competitor in termini di comunicazione?',
            'Ci sono brand che ammiri per come comunicano? Perché?',
        ];
    }

    /**
     * Estrae profilo brand strutturato da testo libero via Claude.
     */
    private function extractBrandProfile(Brand $brand, string $inputText): array
    {
        if (empty(trim($inputText))) {
            return [];
        }

        $prompt = <<<PROMPT
Sei un esperto di brand strategy. Analizza il seguente testo e estrai un profilo brand strutturato.

## BRAND
- Nome: {$brand->name}
- Settore: {$brand->sector}

## TESTO DA ANALIZZARE
{$inputText}

## COMPITO
Estrai dal testo: tono di voce, valori fondamentali, pubblico target, stile comunicativo.

## FORMATO OUTPUT (JSON)
{
  "tone_of_voice": "professionale e accessibile",
  "brand_values": ["innovazione", "trasparenza", "qualità"],
  "target_audience": "Professionisti PMI italiani 35-55 anni",
  "communication_style": "Contenuti educativi con linguaggio diretto",
  "positioning_notes": "Brand che unisce expertise tecnica a comunicazione umana"
}

Rispondi SOLO con il JSON, senza markdown.
PROMPT;

        try {
            $response = $this->apiClient->call($prompt, 1000);
            $content  = trim($response['content'][0]['text'] ?? '');
            $profile  = $this->apiClient->parseJsonResponse($content);

            Log::info("[VOICE] Profilo estratto per brand #{$brand->id}");

            return is_array($profile) ? $profile : [];
        } catch (\Throwable $e) {
            Log::error("[VOICE] Estrazione profilo fallita: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Formatta coppie domanda/risposta come testo per Claude.
     */
    private function formatAnswersAsText(array $answers): string
    {
        if (empty($answers)) {
            return '';
        }

        $lines = [];
        foreach ($answers as $index => $item) {
            if (is_array($item)) {
                $question = $item['question'] ?? "Domanda " . ($index + 1);
                $answer   = $item['answer'] ?? '';
                $lines[]  = "D: {$question}\nR: {$answer}";
            } elseif (is_string($item)) {
                $lines[] = "Risposta " . ($index + 1) . ": {$item}";
            }
        }

        return implode("\n\n", $lines);
    }
}
