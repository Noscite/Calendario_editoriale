<?php

declare(strict_types=1);

namespace App\Domain\Generation\Services;

use App\Domain\Brand\Models\Brand;
use App\Domain\Generation\Data\AiGenerationParams;
use App\Domain\Generation\Models\AiGenerationSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Risolve i parametri effettivi di una chiamata AI per (brand, step):
 * override brand → default globale → costante hardcoded, campo per campo
 * (non riga per riga: un brand può sovrascrivere solo la temperature e
 * lasciare il resto ereditato).
 *
 * Gli step corrispondono 1:1 alle chiamate Anthropic di ClaudeContentGenerator.
 */
class AiGenerationSettingsService
{
    public const STEP_STRATEGY      = 'strategy';
    public const STEP_COPY          = 'copy';
    public const STEP_PERSONAS      = 'personas';
    public const STEP_REGENERATE    = 'regenerate';
    public const STEP_IMAGE_PROMPT  = 'image_prompt';
    public const STEP_EVENT_POST    = 'event_post';
    public const STEP_EVENT_DIGEST  = 'event_digest';

    public static function steps(): array
    {
        return [
            self::STEP_STRATEGY     => 'Strategy plan (pianificazione calendario)',
            self::STEP_COPY         => 'Copy batch (scrittura post)',
            self::STEP_PERSONAS     => 'Generazione buyer personas',
            self::STEP_REGENERATE   => 'Rigenerazione singolo post',
            self::STEP_IMAGE_PROMPT => 'Prompt immagine',
            self::STEP_EVENT_POST   => 'Post evento territoriale (Pro Loco)',
            self::STEP_EVENT_DIGEST => 'Digest mensile eventi territoriali',
        ];
    }

    /**
     * Valori di partenza se non esiste né override brand né default globale
     * in DB. Riflettono il comportamento hardcoded pre-esistente, così
     * l'introduzione della tabella settings non cambia nulla finché nessuno
     * la valorizza dall'admin.
     */
    private const HARDCODED_DEFAULTS = [
        self::STEP_STRATEGY => [
            'model' => 'claude-opus-4-7', 'max_tokens' => 8_000, 'caching' => false,
        ],
        self::STEP_COPY => [
            'model' => 'claude-opus-4-8', 'max_tokens' => 10_000, 'caching' => true,
        ],
        self::STEP_PERSONAS => [
            'model' => 'claude-opus-4-8', 'max_tokens' => 4_000, 'caching' => false,
        ],
        self::STEP_REGENERATE => [
            'model' => 'claude-haiku-4-5-20251001', 'max_tokens' => 2_000, 'caching' => false,
        ],
        self::STEP_IMAGE_PROMPT => [
            'model' => 'claude-haiku-4-5-20251001', 'max_tokens' => 800, 'caching' => false,
        ],
        self::STEP_EVENT_POST => [
            'model' => 'claude-opus-4-7', 'max_tokens' => 1_024, 'caching' => false,
        ],
        self::STEP_EVENT_DIGEST => [
            'model' => 'claude-opus-4-7', 'max_tokens' => 1_536, 'caching' => false,
        ],
    ];

    /**
     * True solo per gli step il cui codice fa realmente più chiamate
     * ravvicinate sullo stesso contesto statico (quindi con benefici reali
     * dalla cache). Per gli altri step il toggle "prompt caching" esiste in
     * DB ma il codice chiamante non lo legge nemmeno: attivarlo non avrebbe
     * alcun effetto (o, se un domani venisse cablato su una chiamata singola,
     * pagherebbe solo il sovrapprezzo di creazione cache senza mai un read).
     */
    public static function cachingApplicable(string $step): bool
    {
        return $step === self::STEP_COPY;
    }

    /**
     * Nome breve per la UI (non usato nelle chiamate API, solo per leggibilità
     * — l'id completo resta sempre visibile accanto, in piccolo).
     */
    public static function modelShortLabel(string $model): string
    {
        return match (true) {
            str_starts_with($model, 'claude-opus-4-8')   => 'Opus 4.8',
            str_starts_with($model, 'claude-opus-4-7')   => 'Opus 4.7',
            str_starts_with($model, 'claude-sonnet-4-6') => 'Sonnet 4.6',
            str_starts_with($model, 'claude-sonnet-4-5') => 'Sonnet 4.5',
            str_starts_with($model, 'claude-haiku-4-5')  => 'Haiku 4.5',
            default                                       => $model,
        };
    }

    /**
     * Valori di fallback per step, esposti in sola lettura per l'admin
     * (tabella "cosa eredito se non imposto nulla" nella pagina Filament).
     * Temperature/top_p/top_k non compaiono: il fallback è "non inviarli
     * all'API", cioè il default Anthropic stesso (temperature=1, top_p/top_k
     * assenti = nessun vincolo di campionamento oltre alla temperature).
     *
     * @return array<string, array{model: string, max_tokens: int, caching: bool}>
     */
    public static function hardcodedDefaults(): array
    {
        return self::HARDCODED_DEFAULTS;
    }

    /**
     * Spiegazione di ogni parametro per la UI admin: cosa fa e a cosa serve.
     *
     * @return array<string, array{label: string, help: string}>
     */
    public static function parameterHelp(): array
    {
        return [
            'model' => [
                'label' => 'Modello',
                'help'  => 'Il modello Claude che genera la risposta per questo step. '
                    . 'Opus è il più capace ma costa ~1,7× Sonnet a parità di token; '
                    . 'Haiku è il più economico, adatto a task semplici/brevi (rigenerazione, prompt immagine). '
                    . 'Cambiarlo qui incide direttamente sul costo mostrato in "AI Usage & Costi".',
            ],
            'temperature' => [
                'label' => 'Temperature',
                'help'  => 'Controlla quanto il modello "rischia" nelle scelte di parole/struttura: 0 = quasi '
                    . 'deterministico e ripetitivo, 1 = massima varietà/creatività (default Anthropic se lasci vuoto). '
                    . 'Range 0–1. Utile abbassarla per output più prevedibili (es. dati strutturati), alzarla per copy più vario.',
            ],
            'max_tokens' => [
                'label' => 'Max tokens',
                'help'  => 'Limite massimo di token che il modello può generare in output per questa chiamata. '
                    . 'Troppo basso tronca la risposta (es. JSON incompleto, post tagliati); troppo alto non cambia '
                    . 'la qualità ma alza il costo massimo teorico della chiamata. Va dimensionato sul contenuto atteso dello step.',
            ],
            'top_p' => [
                'label' => 'Top P',
                'help'  => 'Campionamento nucleus: il modello sceglie solo tra le parole la cui probabilità cumulata '
                    . 'raggiunge questa soglia (es. 0.9 = 90%). Alternativa/complemento alla temperature per controllare '
                    . 'la varietà. Lascialo vuoto se già regoli la temperature: usarli insieme in modo aggressivo può rendere l\'output incoerente.',
            ],
            'top_k' => [
                'label' => 'Top K',
                'help'  => 'Limita la scelta del modello alle K parole più probabili ad ogni passo (es. 40 = solo le 40 '
                    . 'più probabili). Riduce ulteriormente la varietà rispetto a top_p. Parametro avanzato, di norma non serve toccarlo.',
            ],
            'prompt_caching_enabled' => [
                'label' => 'Prompt caching',
                'help'  => 'Se attivo, la parte statica del prompt (contesto brand, strategy plan) viene cachata da '
                    . 'Anthropic per ~5 minuti: le chiamate successive dello stesso batch la rileggono a 1/10 del costo '
                    . 'invece di ripagarla per intero. Ha senso SOLO per step che fanno più chiamate ravvicinate sullo '
                    . 'stesso contesto — oggi è così per "Copy batch" (una chiamata per ogni blocco di 14 giorni dello '
                    . 'stesso calendario). Tutti gli altri step fanno una sola chiamata a botta (una strategy, una '
                    . 'rigenerazione, un digest…): non c\'è una seconda chiamata che possa leggere la cache, quindi il '
                    . 'toggle è disattivato di default e — per questi step — il codice non lo legge nemmeno: attivarlo '
                    . 'qui non avrebbe alcun effetto finché non verrà cablato un flusso a chiamate multiple.',
            ],
        ];
    }

    public function resolve(?Brand $brand, string $step): AiGenerationParams
    {
        $defaults = self::HARDCODED_DEFAULTS[$step]
            ?? throw new \InvalidArgumentException("Step di generazione sconosciuto: {$step}");

        $global = $this->cachedRow(null, $step);
        $override = $brand ? $this->cachedRow($brand->id, $step) : null;

        return new AiGenerationParams(
            model: $override?->model ?? $global?->model ?? $defaults['model'],
            maxTokens: $override?->max_tokens ?? $global?->max_tokens ?? $defaults['max_tokens'],
            temperature: $override?->temperature ?? $global?->temperature,
            topP: $override?->top_p ?? $global?->top_p,
            topK: $override?->top_k ?? $global?->top_k,
            promptCachingEnabled: $override?->prompt_caching_enabled
                ?? $global?->prompt_caching_enabled
                ?? $defaults['caching'],
        );
    }

    /**
     * Cache breve in-process/Redis: questa risoluzione avviene ad ogni singola
     * chiamata Anthropic (anche più volte per batch), niente query ripetute.
     */
    private function cachedRow(?int $brandId, string $step): ?AiGenerationSetting
    {
        $key = 'ai_gen_settings:' . ($brandId ?? 'global') . ':' . $step;

        return Cache::remember($key, now()->addMinutes(5), function () use ($brandId, $step) {
            return AiGenerationSetting::where('brand_id', $brandId)
                ->where('step', $step)
                ->first();
        });
    }

    public function forgetCache(?int $brandId, string $step): void
    {
        Cache::forget('ai_gen_settings:' . ($brandId ?? 'global') . ':' . $step);
    }
}
