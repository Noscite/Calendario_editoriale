<?php

declare(strict_types=1);

namespace App\Domain\Generation\Services;

use App\Domain\Brand\Models\Brand;
use Carbon\Carbon;

/**
 * Costruisce le stringhe di prompt per Anthropic Claude.
 *
 * Estratto da ClaudeContentGenerator per separare la logica
 * di costruzione dei prompt dal codice di orchestrazione.
 *
 * Metodi pubblici:
 *   - buildBatchPrompt()          → prompt per generare un batch di post
 *   - formatContentMixForPrompt() → sezione mix contenuti per il prompt
 *   - formatSchedulingFromPersonas() → sezione scheduling dal personas
 *   - buildPersonaPrompt()        → prompt per analisi/generazione personas
 *   - buildRegeneratePrompt()     → prompt rigenerazione singolo post
 *   - buildRegeneratePersonasPrompt() → prompt rigenerazione personas con feedback
 *   - buildAddPersonaPrompt()     → prompt per aggiungere una persona
 *   - buildRegenerateSinglePersonaPrompt() → prompt rigenerazione singola persona
 *   - buildImagePrompt()          → prompt per immagine DALL-E via Claude
 */
final class PromptBuilder
{
    /**
     * Costruisce il prompt per generare un batch di post del calendario.
     * Estratto da ClaudeContentGenerator::generateBatch().
     */
    public function buildBatchPrompt(
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
    ): string {
        $schedulingInfo  = $this->formatSchedulingFromPersonas($buyerPersonas, $platforms);
        $contentMixInfo  = !empty($contentMixData)
            ? $this->formatContentMixForPrompt($contentMixData)
            : 'Usa mix standard: 60% post, 25% stories, 15% reel (dove supportati)';
        $personasText    = $this->formatPersonasForPrompt($buyerPersonas);
        $platformsList   = implode(', ', $platforms);
        $postsPerWeekJson = json_encode($postsPerWeek, JSON_UNESCAPED_UNICODE);
        $themesList      = !empty($themes) ? implode(', ', $themes) : 'Generici per il settore';
        $objectivesList  = implode(', ', $projectInfo['objectives'] ?? ['brand_awareness']);

        return <<<PROMPT
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
  }
]

Rispondi SOLO con il JSON array, senza markdown.
PROMPT;
    }

    /**
     * Costruisce il prompt per l'analisi e generazione delle buyer personas.
     * Estratto da ClaudeContentGenerator::callClaudeForPersonas().
     */
    public function buildPersonaPrompt(Brand $brand, array $platforms, string $urlContext = ''): string
    {
        $brandValues   = is_array($brand->brand_values)
            ? implode(', ', $brand->brand_values)
            : ($brand->brand_values ?? 'Non specificati');
        $platformsList = !empty($platforms) ? implode(', ', $platforms) : 'Tutte';

        return <<<PROMPT
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
    }

    /**
     * Costruisce il prompt per la rigenerazione di un singolo post.
     * Estratto da ClaudeContentGenerator::regenerateSinglePost().
     */
    public function buildRegeneratePrompt(
        string $postContent,
        mixed  $platform,
        string $pillar,
        string $userPrompt,
        string $brandContext,
        string $toneOfVoice,
        string $brandStyleGuide = '',
    ): string {
        $platformStr    = is_object($platform) ? $platform->value : (string) $platform;
        $styleGuideText = $this->str($brandStyleGuide, ClaudeContentGenerator::DEFAULT_STYLE_GUIDE);

        return <<<PROMPT
Rigenera questo post social.

## POST ORIGINALE
Piattaforma: {$platformStr}
Pillar: {$pillar}
Contenuto: {$postContent}

## CONTESTO BRAND
{$brandContext}
Tono di voce: {$toneOfVoice}

## ISTRUZIONI UTENTE
{$userPrompt}

## LINEE GUIDA
{$styleGuideText}

## OUTPUT (JSON)
{
  "content": "Nuovo testo del post",
  "hashtags": ["hashtag1", "hashtag2"],
  "visual_suggestion": "Suggerimento per visual",
  "cta": "Call to action"
}

Rispondi SOLO con il JSON.
PROMPT;
    }

    /**
     * Costruisce il prompt per rigenerare le personas con feedback utente.
     * Estratto da ClaudeContentGenerator::regeneratePersonas().
     */
    public function buildRegeneratePersonasPrompt(
        Brand  $brand,
        array  $platforms,
        array  $currentPersonas,
        string $feedback,
    ): string {
        $currentJson   = json_encode($currentPersonas, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $brandValues   = is_array($brand->brand_values)
            ? implode(', ', $brand->brand_values)
            : ($brand->brand_values ?? '');
        $platformsList = implode(', ', $platforms);

        return <<<PROMPT
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
    }

    /**
     * Costruisce il prompt per aggiungere una nuova buyer persona.
     * Estratto da ClaudeContentGenerator::addPersona().
     */
    public function buildAddPersonaPrompt(
        Brand   $brand,
        array   $platforms,
        array   $existingPersonas,
        ?string $description,
    ): string {
        $existingNames   = implode(', ', array_column($existingPersonas, 'name'));
        $platformsList   = implode(', ', $platforms);
        $brandValues     = is_array($brand->brand_values)
            ? implode(', ', $brand->brand_values)
            : ($brand->brand_values ?? '');
        $descriptionText = $description
            ? "Genera una persona che corrisponde a: {$description}"
            : 'Genera una nuova persona diversa da quelle esistenti';

        return <<<PROMPT
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
    }

    /**
     * Costruisce il prompt per rigenerare una singola buyer persona.
     * Estratto da ClaudeContentGenerator::regenerateSinglePersona().
     */
    public function buildRegenerateSinglePersonaPrompt(
        Brand   $brand,
        array   $platforms,
        array   $oldPersona,
        ?string $description,
    ): string {
        $oldPersonaJson = json_encode($oldPersona, JSON_UNESCAPED_UNICODE);
        $platformsList  = implode(', ', $platforms);
        $brandValues    = is_array($brand->brand_values)
            ? implode(', ', $brand->brand_values)
            : ($brand->brand_values ?? '');
        $descText       = $description
            ? "Tieni conto di questa indicazione: {$description}"
            : 'Migliora la persona rendendola più precisa e dettagliata';

        return <<<PROMPT
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
    }

    /**
     * Costruisce il prompt per generare un prompt immagine DALL-E.
     * Estratto da ClaudeContentGenerator::generateImagePrompt().
     */
    public function buildImagePrompt(
        string $postContent,
        string $platform,
        string $pillar,
        string $brandName,
        string $brandSector,
        string $brandColors = '',
        string $visualSuggestion = '',
    ): string {
        $colorsText    = $this->str($brandColors, 'Non specificati');
        $suggestionText = $this->str($visualSuggestion, 'Non specificato');

        return <<<PROMPT
Crea un prompt dettagliato per DALL-E per generare un'immagine per questo post social.

## POST
Piattaforma: {$platform}
Contenuto: {$postContent}
Pillar: {$pillar}

## BRAND
Nome: {$brandName}
Settore: {$brandSector}
Colori: {$colorsText}
Stile richiesto: {$suggestionText}

## ISTRUZIONI
- Crea un prompt in inglese per DALL-E
- Stile professionale e moderno
- Adatto per {$platform}
- IMPORTANTE: Nessun testo, nessuna scritta, nessuna parola, nessun numero nell'immagine
- NO loghi o marchi
- Formato: descrizione dettagliata in 1-2 frasi

Rispondi SOLO con il prompt in inglese, senza altro testo.
PROMPT;
    }

    // ──────────────────────────────────────────────────────────
    //  Metodi di formattazione (già presenti in CCG — spostati qui)
    // ──────────────────────────────────────────────────────────

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

            usort($slots, fn ($a, $b) => ($a['priority'] ?? 99) <=> ($b['priority'] ?? 99));
            $topSlots = array_slice($slots, 0, 3);

            $slotStrs = [];
            foreach ($topSlots as $s) {
                $day        = $s['day'] ?? 0;
                $time       = $s['time'] ?? '12:00';
                $slotStrs[] = ($dayNames[$day] ?? 'N/A') . ' ' . $time;
            }

            $lines[] = '- ' . strtoupper($platform) . ': ' . (!empty($slotStrs) ? implode(', ', $slotStrs) : 'flessibile');
            if (!empty($avoid)) {
                $lines[] = '  (evitare: ' . implode(', ', array_slice($avoid, 0, 2)) . ')';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Formatta i dati del mix contenuti per includerli nel prompt.
     * Replica esatta di format_content_mix_for_prompt().
     */
    public function formatContentMixForPrompt(array $contentMixData): string
    {
        if (empty($contentMixData)) {
            return 'Nessuna ricerca disponibile - usa mix standard';
        }

        $lines = ["## MIX CONTENUTI RACCOMANDATO (basato su ricerca Perplexity)\n"];

        foreach ($contentMixData as $platform => $data) {
            $platformUpper = strtoupper($platform);

            $lines[] = ($data['source'] ?? '') === 'perplexity'
                ? "### {$platformUpper} (confidence: " . ($data['confidence'] ?? 'medium') . ')'
                : "### {$platformUpper} (default)";

            $supportsStories = $data['supports_stories'] ?? false;
            $supportsReels   = $data['supports_reels'] ?? false;
            $mix             = $data['format_mix'] ?? [];
            $weekly          = $data['format_weekly_count'] ?? [];
            $total           = $data['recommended_weekly_total'] ?? 5;

            $lines[] = "- Contenuti settimanali totali: {$total}";
            $lines[] = '- POST: ' . ($mix['post_percentage'] ?? 100) . '% (' . ($weekly['posts'] ?? $total) . ' a settimana)';

            $lines[] = $supportsStories
                ? '- STORIES: ' . ($mix['story_percentage'] ?? 0) . '% (' . ($weekly['stories'] ?? 0) . ' a settimana)'
                : "- STORIES: Non supportate su {$platform}";

            $lines[] = $supportsReels
                ? '- REELS: ' . ($mix['reel_percentage'] ?? 0) . '% (' . ($weekly['reels'] ?? 0) . ' a settimana)'
                : "- REELS: Non supportati su {$platform}";

            $ideas = $data['best_content_ideas'] ?? [];
            if (!empty($ideas['posts'])) {
                $lines[] = '- Idee POST: ' . implode(', ', array_slice($ideas['posts'], 0, 3));
            }
            if (!empty($ideas['stories']) && $supportsStories) {
                $lines[] = '- Idee STORIES: ' . implode(', ', array_slice($ideas['stories'], 0, 3));
            }
            if (!empty($ideas['reels']) && $supportsReels) {
                $lines[] = '- Idee REELS: ' . implode(', ', array_slice($ideas['reels'], 0, 3));
            }

            $tips = $data['sector_specific_tips'] ?? null;
            if ($tips) {
                $lines[] = '- Tips settore: ' . mb_substr($tips, 0, 200);
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    // ──────────────────────────────────────────────────────────
    //  Helpers privati
    // ──────────────────────────────────────────────────────────

    /**
     * Formatta le buyer personas per inserirle nel prompt batch.
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

            $weightPct = number_format($weight * 100, 0);
            $ageRange  = $demo['age_range'] ?? 'N/A';
            $role      = $demo['role'] ?? 'N/A';
            $location  = $demo['location'] ?? 'N/A';
            $ppStr     = !empty($painPoints) ? implode(', ', array_slice($painPoints, 0, 3)) : 'N/A';
            $intStr    = !empty($interests) ? implode(', ', array_slice($interests, 0, 3)) : 'N/A';

            $lines[] = <<<PERSONA

### {$name} (peso: {$weightPct}%)
- Profilo: {$ageRange}, {$role}, {$location}
- Pain points: {$ppStr}
- Interessi: {$intStr}
PERSONA;
        }

        return implode("\n", $lines);
    }

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
