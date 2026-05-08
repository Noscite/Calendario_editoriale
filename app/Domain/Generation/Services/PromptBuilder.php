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
        $schedulingInfo   = $this->formatSchedulingFromPersonas($buyerPersonas, $platforms);
        $contentMixInfo   = !empty($contentMixData)
            ? $this->formatContentMixForPrompt($contentMixData)
            : 'Usa mix standard: 60% post, 25% stories, 15% reel (dove supportati)';
        $personasText     = $this->formatPersonasForPrompt($buyerPersonas);
        $platformsList    = implode(', ', $platforms);
        $postsPerWeekJson = json_encode($postsPerWeek, JSON_UNESCAPED_UNICODE);
        $themesList       = !empty($themes) ? implode(', ', $themes) : 'Generici per il settore';
        // Quando ci sono pillar configurati, etichettiamo la sezione come
        // "PILLAR DISPONIBILI" con vincolo di matching letterale (no
        // parafrasi/traduzione/slug). Se assenti, fallback al label storico
        // "Temi" per non rompere progetti senza content_pillars.
        $pillarsLabel = !empty($themes)
            ? 'PILLAR DISPONIBILI (USA ESATTAMENTE UNO DI QUESTI VALORI per il campo "pillar", senza parafrasi, traduzione o slug)'
            : 'Temi';
        $objectivesList   = implode(', ', $projectInfo['objectives'] ?? ['brand_awareness']);
        $objectives       = $projectInfo['objectives'] ?? ['brand_awareness'];

        $platformGuidelines    = $this->buildPlatformGuidelines($platforms);
        $voiceExamplesSection  = $this->formatVoiceExamplesForPrompt(
            $brandInfo['voice_examples'] ?? [],
            $platforms
        );
        $ctaExamples        = $this->buildCtaExamples($objectives);
        $styleSection       = ($styleGuide !== '')
            ? "### Stile del brand\n{$styleGuide}\n\n"
            : '';

        // Edition history context (injected via projectInfo)
        $historyContext = $projectInfo['history_context'] ?? '';
        $historySection = ($historyContext !== '')
            ? "\n{$historyContext}\n\n## ISTRUZIONI CONTINUITÀ EDITORIALE\nQuesta è una NUOVA EDIZIONE del piano editoriale. Analizza lo storico sopra e:\n- EVITA di ripetere gli stessi argomenti e hook già usati\n- MANTIENI coerenza di tono e stile con le edizioni precedenti\n- EVOLVI i temi trattati, approfondendo o esplorando nuovi angoli\n- RIPRENDI eventuali topic di successo con prospettive fresche\n- ASSICURA continuità narrativa per il brand\n\n"
            : '';

        return <<<PROMPT
Genera contenuti per il calendario editoriale.
{$historySection}
## BRAND
Nome: {$brandName}
Settore: {$this->arr($brandInfo, 'sector', 'N/A')}
Descrizione: {$this->arr($brandInfo, 'description', 'N/A')}
Tono di voce: {$this->arr($brandInfo, 'tone_of_voice', 'professionale')}
Valori: {$this->arrJson($brandInfo, 'brand_values', '[]')}

{$voiceExamplesSection}## CONTESTO DAL SITO
{$this->str($urlContext, 'Non disponibile')}

## KNOWLEDGE BASE AZIENDALE
{$this->str($ragContext, 'Non disponibile')}

## BUYER PERSONAS
{$personasText}

## STRATEGIA CONTENUTI PER PERSONAS
Per ogni post, considera quale persona stai indirizzando e adatta:

- LINGUAGGIO: usa termini e riferimenti familiari alla persona target
  (un responsabile acquisti parla di "ROI e TCO", un artigiano parla di
  "qualità del lavoro" e "clienti soddisfatti")

- PAIN POINT: ogni post deve toccare almeno uno dei pain points della persona
  o offrire una soluzione concreta a un suo problema reale

- LIVELLO DI CONSAPEVOLEZZA:
  - Se la persona è "problem aware" → contenuto educativo/informativo
  - Se è "solution aware" → contenuto comparativo/differenziante
  - Se è "product aware" → contenuto social proof/offerta

- TONO: adatta il registro in base alla persona
  (formale per B2B executive, diretto per artigiani/PMI operative,
  ispirazionale per settori creativi/moda/food)

## SCHEDULING OTTIMALE (basato sulle personas)
{$schedulingInfo}

## MIX FORMATI CONTENUTO (basato su ricerca Perplexity)
{$contentMixInfo}

## PROGETTO
Periodo: {$startDate->toDateString()} - {$endDate->toDateString()}
Piattaforme: {$platformsList}
Post per settimana: {$postsPerWeekJson}
{$pillarsLabel}: {$themesList}
Brief: {$this->arr($projectInfo, 'brief', 'N/A')}
Obiettivi: {$objectivesList}

## CALL TO ACTION
La CTA è obbligatoria per ogni post. Deve essere:
- Specifica (non "scopri di più" generico, ma "prenota la tua consulenza gratuita")
- Contestuale al contenuto del post (deve sembrare naturale, non appiccicata)
- Adatta alla piattaforma (vedi regole per piattaforma)
- Coerente con l'obiettivo del progetto

{$ctaExamples}

IMPORTANTE: Il campo "call_to_action" è OBBLIGATORIO per ogni post. Non usare mai
CTA vaghe come "clicca qui", "visita il sito", "scopri di più" senza specificare
cosa troverà l'utente.

## LINEE GUIDA
{$styleSection}{$platformGuidelines}
## STRATEGIA HASHTAG
- Usa il MIX 1/3 rule:
  1/3 hashtag brand/settore specifici (alta rilevanza, basso volume)
  1/3 hashtag di nicchia (medio volume, buona rilevanza)
  1/3 hashtag trending pertinenti (alto volume, visibilità)

- MAI hashtag in italiano generico (#marketing, #lavoro, #azienda)
  se esiste una versione più specifica (#marketingdigitale, #PMI, #imprenditoria)

- Per LinkedIn: privilegia hashtag settoriali italiani e internazionali
  (#Manifatturiero, #SupplyChain, #B2BMarketing)

- Per Instagram: mix italiano/inglese, hashtag visivi se il contenuto è fotografico
  (#MadeInItaly, #ArtigianatoItaliano solo se genuinamente pertinente)

- NON ripetere gli stessi hashtag in ogni post — variali per non sembrare bot
- Per Google Business Profile: NON usare hashtag (non supportati)

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
8. STRUTTURA DEL TESTO — framework AIDA adattato per piattaforma:
   - Attention:  prima frase/riga — cattura l'attenzione con un hook
                 (domanda, dato sorprendente, affermazione bold, problema noto)
   - Interest:   sviluppa con valore reale (insight, consiglio, dato)
   - Desire:     collega il contenuto al beneficio concreto per il lettore
   - Action:     CTA specifica e contestuale (vedi sezione CALL TO ACTION)

   Per INSTAGRAM/FACEBOOK: AIDA compressa, hook visibile sopra il fold
   Per LINKEDIN:           AIDA espansa, hook + sviluppo argomentato
   Per GBP:                Solo Attention + Action (testo breve e diretto)

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
    "pillar": "<uno dei pillar disponibili>",
    "visual_suggestion": "Carousel con 5 slide infografiche",
    "call_to_action": "Testo della CTA"
  }
]

Rispondi SOLO con il JSON array, senza markdown.
PROMPT;
    }

    /**
     * Come buildBatchPrompt() ma restituisce il prompt in due parti per il caching.
     *
     * - 'static'  → tutto ciò che è identico tra i batch (brand, personas, guidelines)
     * - 'dynamic' → la sezione ## PROGETTO con le date specifiche del batch
     *
     * Usato da ClaudeContentGenerator::generateBatch() con AnthropicApiClient::callCached()
     * per ridurre il costo dei token di input dei batch 2-N (cache hit = ~10% del costo normale).
     *
     * @return array{static: string, dynamic: string}
     */
    public function buildBatchPromptParts(
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
    ): array {
        $full       = $this->buildBatchPrompt(
            $brandName, $brandInfo, $projectInfo, $startDate, $endDate,
            $platforms, $postsPerWeek, $themes, $urlContext, $ragContext,
            $styleGuide, $buyerPersonas, $contentMixData,
        );
        $splitMark  = "\n## PROGETTO\n";
        $pos        = strpos($full, $splitMark);

        if ($pos === false || $pos < 1024) {
            // Fallback: nessun split (prompt troppo corto o marker assente)
            return ['static' => $full, 'dynamic' => ''];
        }

        return [
            'static'  => substr($full, 0, $pos),
            'dynamic' => substr($full, $pos),
        ];
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
        array  $voiceExamples = [],
    ): string {
        $platformStr    = is_object($platform) ? $platform->value : (string) $platform;
        $styleGuideText = $this->str($brandStyleGuide, ClaudeContentGenerator::DEFAULT_STYLE_GUIDE);
        $voiceSection   = $this->formatVoiceExamplesForPrompt($voiceExamples, [], $platformStr);

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

{$voiceSection}## TIPI DI HOOK (varia rispetto al post originale)
Scegli un tipo di hook DIVERSO da quello del post originale:

- Domanda diretta:      "Sai quante PMI italiane non hanno ancora..."
- Dato sorprendente:    "Il 73% delle aziende fa ancora questo errore..."
- Affermazione bold:    "Il marketing sui social è sopravvalutato. Eccetto quando..."
- Storia/scenario:      "Immagina di aprire LinkedIn e trovare..."
- Problema condiviso:   "Anche tu perdi ore ogni settimana su..."
- Contro-intuizione:    "Meno post, più risultati. Ecco perché..."
- Lista numerata:       "3 cose che nessuno ti dice sul content marketing..."

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
        string $contentType = 'post',
    ): string {
        $colorsText      = $this->str($brandColors, 'Non specificati');
        $suggestionText  = $this->str($visualSuggestion, 'Non specificato');
        $orientationHint = $this->imageOrientationHint($platform, $contentType);
        $styleHint       = $this->imageStyleHint($brandSector);
        $moodHint        = $this->imageMoodHint($pillar);

        return <<<PROMPT
Sei un visual director esperto di prompt engineering per gpt-image-1 e DALL-E 3.

Devi produrre il prompt in INGLESE per generare l'immagine di accompagnamento di questo post social.

## POST
Piattaforma: {$platform}
Tipo contenuto: {$contentType}
Pillar: {$pillar}
Testo del post: {$postContent}

## BRAND
Nome: {$brandName}
Settore: {$brandSector}
Colori brand: {$colorsText}
Indicazione visual dall'editor: {$suggestionText}

## VINCOLI DI FORMATO
- Composizione: {$orientationHint}
- Stile fotografico raccomandato: {$styleHint}
- Mood: {$moodHint}

## REGOLE DURE (DEVONO comparire nel prompt finale)
- VIETATO testo, parole, lettere, numeri, loghi, watermark.
  Ripeti questo vincolo in 3 modi diversi nel prompt finale:
  "no text", "no letters or numbers", "no logos or watermarks".
- Niente persone reali identificabili. Niente celebrità.
- Niente brand visibili (Nike, Apple, ecc.).
- Per settori sanitari: niente foto prima/dopo, niente claim medici visivi.

## STRUTTURA OBBLIGATORIA DEL PROMPT FINALE (in inglese, 3-4 frasi dense)
Componilo seguendo questi blocchi nell'ordine:

1. Subject + Action: cosa è inquadrato e cosa sta facendo (specifico, non generico).
2. Setting: dove, atmosfera, contesto.
3. Photography style + Lighting: es. "editorial photography, soft natural daylight from the side", "studio softbox lighting", "golden hour warm light".
4. Composition + Framing: angolo, framing, profondità, regola dei terzi se rilevante.
5. Color palette: incorpora i colori brand se specificati come tonalità dominante o accenti.
6. Quality modifiers: "professional photography, high detail, sharp focus, 8k".
7. Negative prompts (in coda): "no text, no letters or numbers, no logos or watermarks".

## ESEMPI DI PROMPT BEN FATTI (NON COPIARE, IMITA LO STILE)

Esempio per settore food, pillar lifestyle, instagram post:
"Close-up of a freshly baked focaccia on a rustic wooden board, golden crust glistening with olive oil and rosemary sprigs scattered around, warm natural daylight streaming from the left, shallow depth of field with soft bokeh background, terracotta and warm earth color palette with hints of green from herbs, professional food photography, high detail, sharp focus, no text, no letters or numbers, no logos or watermarks."

Esempio per settore consulenza B2B, linkedin:
"Modern minimalist office desk with an open notebook, a fountain pen, and a steaming espresso cup arranged on a clean walnut wood surface, soft directional window light from the right creating subtle shadows, horizontal landscape composition with subject offset to the left third, muted neutral palette with deep teal accents, editorial photography style, professional, sober, sharp focus, high detail, no text, no letters or numbers, no logos or watermarks, no human faces."

## OUTPUT
SOLO il prompt finale in inglese, in 3-4 frasi dense, senza preambolo,
senza virgolette esterne, senza spiegazioni.
PROMPT;
    }

    /** Composizione/orientamento per piattaforma e tipo contenuto. */
    private function imageOrientationHint(string $platform, string $contentType): string
    {
        if (in_array($contentType, ['story', 'reel'], true)) {
            return 'vertical 9:16 composition (mobile-first, subject vertically centered)';
        }
        return match (mb_strtolower($platform)) {
            'instagram'                 => 'square 1:1 composition (subject centered or rule-of-thirds)',
            'linkedin', 'facebook'      => 'horizontal 1.91:1 composition (landscape, subject offset to allow visual breathing room)',
            'google_business'           => 'horizontal 4:3 composition',
            default                     => 'square 1:1 composition',
        };
    }

    /** Stile fotografico/visivo per settore del brand. */
    private function imageStyleHint(string $sector): string
    {
        $s = mb_strtolower(trim($sector));

        if (preg_match('/(food|ristoran|bar\b|gelat|paneter|pizzer|pasti|cucin|caffe|pasticce)/iu', $s)) {
            return 'close-up food photography with natural daylight, appetizing detail, shallow depth of field';
        }
        if (preg_match('/(fashion|moda|abbigl|beauty|cosmet|salon|hair)/iu', $s)) {
            return 'editorial fashion photography, polished, magazine-quality';
        }
        if (preg_match('/(tech|software|saas|digit|ai\b|cloud|cyber)/iu', $s)) {
            return 'minimalist geometric or abstract composition, clean lines, editorial quality';
        }
        if (preg_match('/(art|design|photogr|creat|architett)/iu', $s)) {
            return 'artistic, conceptual or illustrative composition allowed';
        }
        if (preg_match('/(psicolog|medic|odontoiatr|dentist|fisioterap|farmacist|sanit|clinic|avvocat|legal|notai|commercialist|finanz|banc|consulent|assicurat)/iu', $s)) {
            return 'editorial professional photography, sober and dignified, no client faces, no before/after, no medical procedures';
        }
        return 'editorial professional photography, modern but not flashy, sober';
    }

    /** Mood/tono visivo per pillar di contenuto. */
    private function imageMoodHint(string $pillar): string
    {
        $p = mb_strtolower(trim($pillar));

        if ($p === '') {
            return 'professional, clean, contemporary';
        }
        if (preg_match('/(educ|tutorial|how[- ]to|guida|spieg)/iu', $p)) {
            return 'clear, instructional, well-lit; subject easy to read at first glance';
        }
        if (preg_match('/(behind|team|dietro|quotidian)/iu', $p)) {
            return 'candid, natural, atmospheric; documentary feel';
        }
        if (preg_match('/(product|sales|promo|offert)/iu', $p)) {
            return 'studio-clean, premium, polished; subject as hero';
        }
        if (preg_match('/(thought|leadership|insight|opin)/iu', $p)) {
            return 'professional, executive, confident; clean composition';
        }
        if (preg_match('/(lifestyle|inspirat|aspirat)/iu', $p)) {
            return 'environmental, aspirational, atmospheric';
        }
        return 'professional, clean, contemporary';
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

    /**
     * Formatta voice examples come few-shot nel prompt.
     * Filtraggio per piattaforma:
     *  - $singlePlatform impostato → priorità a quella, fallback ad altre
     *  - $platforms array → solo platform attive nel progetto, fallback a tutti
     *
     * Output sezione "## ESEMPI DI VOCE DEL BRAND" o stringa vuota se nessun esempio valido.
     */
    public function formatVoiceExamplesForPrompt(
        array   $examples,
        array   $platforms = [],
        ?string $singlePlatform = null,
    ): string {
        if (empty($examples)) {
            return '';
        }

        $filtered = $examples;
        if ($singlePlatform !== null) {
            $matching = array_filter(
                $examples,
                fn ($e) => is_array($e) && (($e['platform'] ?? '') === $singlePlatform)
            );
            $filtered = !empty($matching) ? $matching : $examples;
        } elseif (!empty($platforms)) {
            $matching = array_filter(
                $examples,
                fn ($e) => is_array($e) && in_array($e['platform'] ?? '', $platforms, true)
            );
            $filtered = !empty($matching) ? $matching : $examples;
        }

        $platformLabels = [
            'instagram'       => 'Instagram',
            'linkedin'        => 'LinkedIn',
            'facebook'        => 'Facebook',
            'google_business' => 'Google Business Profile',
        ];

        $blocks = [];
        $i = 1;
        foreach (array_slice(array_values($filtered), 0, 5) as $example) {
            if (!is_array($example)) {
                continue;
            }
            $platform = (string) ($example['platform'] ?? '');
            $content  = trim((string) ($example['content'] ?? ''));
            $note     = trim((string) ($example['note'] ?? ''));

            if (mb_strlen($content) < 20) {
                continue;
            }

            $platformLabel = $platformLabels[$platform] ?? ucfirst($platform);
            $block = "### Esempio {$i} — {$platformLabel}\n{$content}";
            if ($note !== '') {
                $block .= "\n> Nota: {$note}";
            }
            $blocks[] = $block;
            $i++;
        }

        if (empty($blocks)) {
            return '';
        }

        $intro = 'Questi sono post pubblicati o approvati dal brand. Imita il REGISTRO, '
               . 'il RITMO, la SCELTA LESSICALE — non copiare i contenuti. '
               . 'Adatta lo stile alla piattaforma in cui scrivi.';

        return "## ESEMPI DI VOCE DEL BRAND\n{$intro}\n\n" . implode("\n\n", $blocks) . "\n\n";
    }

    /**
     * Costruisce la sezione "ELEMENTI DEL BRAND DISPONIBILI" che ridichiarano
     * gli asset del brand (description + USP + brief) come unica fonte di
     * verità per i nomi di prodotti/corsi/libri/partner. Riferisce alle
     * sezioni ## BRAND e ## KNOWLEDGE BASE che appaiono prima nel prompt.
     *
     * Il pattern di ridondanza è intenzionale: il modello vede gli asset
     * names una volta in ## BRAND e una volta qui, sotto un vincolo esplicito.
     */
    private function buildBrandAssetsSection(array $brandInfo, array $projectInfo): string
    {
        $description = trim((string) ($brandInfo['description'] ?? ''));
        $usp         = trim((string) ($brandInfo['unique_selling_points'] ?? ''));
        $brief       = trim((string) ($projectInfo['brief'] ?? ''));

        $section = "## ELEMENTI DEL BRAND DISPONIBILI (UNICA FONTE DI VERITÀ PER NOMI E DETTAGLI)\n\n"
                 . "I nomi di prodotti, corsi, libri, partner, piattaforme, eventi, location e qualsiasi altro asset specifico citabile nei post DEVONO essere SOLO quelli ricavabili dalle fonti elencate sotto. È VIETATO inventare nuovi nomi, anche se suonano plausibili.\n\n"
                 . "Fonti consultabili per gli asset (in ordine di priorità):\n"
                 . "1. Sezione ## BRAND sopra (descrizione, valori, tono di voce)\n"
                 . "2. Sezione ## KNOWLEDGE BASE sopra (documenti caricati dal brand)\n";

        if ($usp !== '') {
            $section .= "3. Unique selling points: {$usp}\n";
        }
        if ($brief !== '') {
            $section .= "4. Brief del progetto: {$brief}\n";
        }
        // La descrizione viene già esposta in ## BRAND, ma la riponiamo qui
        // come refresher dedicato al modello al momento della pianificazione.
        if ($description !== '') {
            $section .= "5. Descrizione brand (riepilogo): {$description}\n";
        }

        $section .= "\nREGOLA OPERATIVA: se per scrivere un post hai bisogno di citare un asset specifico (nome corso, nome libro, durata, prezzo, partner, data evento) che NON è esplicitamente presente in una delle fonti sopra, NON inventarlo, NON parafrasarlo, NON ipotizzarlo. Ometti il dato e riformula, oppure usa un costrutto generico (\"uno dei nostri corsi\", \"un recente intervento\", \"una collaborazione del settore\"). È preferibile un post privo di nomi specifici a un post con nomi inventati.\n";

        return $section;
    }

    /**
     * Sezione "VINCOLI ANTI-INVENZIONE" con i 3 vincoli (brand kit, aneddoti,
     * statistiche). Non parametrica: il contenuto è uguale per tutti i brand,
     * la specificità degli asset è già nella sezione precedente.
     *
     * Stile testuale denso: ogni vincolo ha la sua regola positiva e i suoi
     * controesempi concreti, per ridurre il margine di interpretazione.
     */
    private function buildAntiInventionSection(): string
    {
        return <<<'TXT'
## VINCOLI ANTI-INVENZIONE (priorità assoluta — sopra ogni altra istruzione di stile)

VINCOLO 1 — Aderenza assoluta al brand kit:
I nomi dei prodotti, corsi, libri, partner, piattaforme, eventi citati nei post DEVONO essere SOLO quelli ricavabili dalla sezione "ELEMENTI DEL BRAND DISPONIBILI" sopra. È vietato inventare nomi nuovi, anche se suonano plausibili. Se il brand kit elenca 5 corsi, puoi citare solo quei 5 e non un sesto. Se non hai la durata di un corso, NON la inventi: ometti il dato. Se nominare un corso/libro/prodotto non è strettamente necessario per il post, evitalo del tutto invece di inventare.

VINCOLO 2 — Niente aneddoti specifici inventati:
Vietato aprire post (o includere costrutti) del tipo:
- "Ieri / Settimana scorsa / Stamattina, X mi ha detto/chiesto/raccontato Y"
- "Un imprenditore / Un cliente / Un'azienda mi ha detto..."
- Citazioni dirette tra virgolette di persone non specificate ("Mario mi ha detto: '...'")

ECCEZIONE: se il brief o la KNOWLEDGE BASE contiene aneddoti specifici verificabili (es. "Stefano ha tenuto un workshop alla XYZ Spa il 12 marzo"), allora puoi citare quei fatti specifici. Altrimenti usa costrutti onesti che esprimono esperienza senza inventare l'aneddoto:
- "Una domanda ricorrente nei workshop"
- "Lavorando con PMI italiane si nota che..."
- "Una preoccupazione che emerge spesso..."
- "Capita di sentirsi chiedere..."

VINCOLO 3 — Niente statistiche numeriche inventate:
Vietato citare percentuali, ratio, numeri di aziende, durate di studi senza che il dato venga da un input verificabile (brand kit, brief, KNOWLEDGE BASE, dati pubblici noti). Sostituire con costrutti qualitativi:
- "Una riduzione misurabile" invece di "il 23%"
- "Un incremento significativo" invece di "+34%"
- "Una quota rilevante" invece di "l'89%"
- "Diverse aziende" invece di "7 PMI"

ECCEZIONE COMPLIANCE NORMATIVA: numeri e date normativi (date AI Act, percentuali sanzioni del Regolamento UE 2024/1689, ecc.) DEVONO essere precisi e citare la fonte. Se non sei certo del numero esatto, NON lo citi: usa la fonte normativa generica ("le sanzioni previste dal Regolamento UE 2024/1689") senza specificare la percentuale.
TXT;
    }

    /**
     * Versione condensata dei 3 vincoli anti-invenzione, da iniettare come
     * REMINDER finale nel copy prompt (Sonnet) prima della generazione del
     * testo, per controbilanciare la deriva tipica del modello in fase
     * di scrittura. Mantiene gli stessi divieti ma è più breve della
     * versione strategy per non gonfiare il prompt cached.
     */
    private function buildAntiInventionReminder(): string
    {
        return <<<'TXT'
## REMINDER ANTI-INVENZIONE (priorità assoluta)

1. NOMI BRAND: cita SOLO prodotti/corsi/libri/partner presenti negli input (## BRAND, ## KNOWLEDGE BASE, brief). Vietato inventare nomi nuovi anche se plausibili. Se serve un dato che non hai, omettilo o usa costrutti generici.
2. ANEDDOTI: vietato "Ieri/Settimana scorsa X mi ha detto Y", citazioni virgolettate di persone non specificate. Usa "Una domanda ricorrente", "Capita di sentirsi chiedere", "Lavorando con PMI italiane si nota che...". Eccezione: aneddoti documentati nel brief/KB.
3. STATISTICHE: vietato percentuali/ratio/numeri non documentati. Usa "una riduzione misurabile", "un incremento significativo", "una quota rilevante". Eccezione compliance: cita Regolamento UE 2024/1689 generico se non hai il numero esatto.
TXT;
    }

    /**
     * STRATEGY prompt — passato a Opus 4.7 in 1 sola chiamata.
     * Output atteso: JSON con editorial_narrative + pillar_distribution + posts[].
     */
    public function buildStrategyPrompt(
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
        array   $buyerPersonas,
        array   $contentMixData,
    ): string {
        $totalDays        = $startDate->diffInDays($endDate) + 1;
        $platformsList    = implode(', ', $platforms);
        $themesList       = !empty($themes) ? implode(', ', $themes) : 'Generici per il settore';
        // Stesso pattern del legacy batch: label condizionato a se ci sono
        // pillar configurati (additivo, no breaking).
        $pillarsLabel = !empty($themes)
            ? 'PILLAR DISPONIBILI (USA ESATTAMENTE UNO DI QUESTI VALORI per il campo "pillar", senza parafrasi, traduzione o slug)'
            : 'Temi';
        $pillarConstraintBullet = !empty($themes)
            ? "\n- Il valore del campo \"pillar\" di OGNI post DEVE essere uno dei valori elencati nella sezione PILLAR DISPONIBILI sopra, copiato letteralmente. NON parafrasare, tradurre, abbreviare, trasformare in slug."
            : '';
        $objectivesList   = implode(', ', $projectInfo['objectives'] ?? ['brand_awareness']);
        $postsPerWeekJson = json_encode($postsPerWeek, JSON_UNESCAPED_UNICODE);

        $personasText  = $this->formatPersonasForPrompt($buyerPersonas);
        $schedulingTxt = $this->formatSchedulingFromPersonas($buyerPersonas, $platforms);
        $contentMixTxt = !empty($contentMixData)
            ? $this->formatContentMixForPrompt($contentMixData)
            : 'Mix standard: 60% post, 25% stories, 15% reel (dove supportati)';

        $voiceSection = $this->formatVoiceExamplesForPrompt(
            $brandInfo['voice_examples'] ?? [],
            $platforms
        );

        // Vincoli anti-invenzione (additivi, non rimpiazzano alcuna sezione
        // esistente). Vengono iniettati DOPO ## PROGETTO e PRIMA di
        // ## IL TUO COMPITO, in modo da essere freschi quando il modello
        // inizia a comporre il piano.
        $brandAssetsSection   = $this->buildBrandAssetsSection($brandInfo, $projectInfo);
        $antiInventionSection = $this->buildAntiInventionSection();

        return <<<PROMPT
Pianifica il calendario editoriale per questo periodo. NON scrivere ancora il copy dei post — quello è uno step successivo. Ora devi solo definire la STRATEGIA.

## BRAND
Nome: {$brandName}
Settore: {$this->arr($brandInfo, 'sector', 'N/A')}
Descrizione: {$this->arr($brandInfo, 'description', 'N/A')}
Tono di voce: {$this->arr($brandInfo, 'tone_of_voice', 'professionale')}
Valori: {$this->arrJson($brandInfo, 'brand_values', '[]')}

{$voiceSection}## CONTESTO DAL SITO
{$this->str($urlContext, 'Non disponibile')}

## KNOWLEDGE BASE
{$this->str($ragContext, 'Non disponibile')}

## BUYER PERSONAS
{$personasText}

## SCHEDULING DALLE PERSONAS
{$schedulingTxt}

## MIX FORMATI (PERPLEXITY)
{$contentMixTxt}

## PROGETTO
Periodo: {$startDate->toDateString()} → {$endDate->toDateString()} ({$totalDays} giorni)
Piattaforme: {$platformsList}
Post per settimana: {$postsPerWeekJson}
{$pillarsLabel}: {$themesList}
Brief: {$this->arr($projectInfo, 'brief', 'N/A')}
Obiettivi: {$objectivesList}

{$brandAssetsSection}
{$antiInventionSection}

## IL TUO COMPITO
Produci un PIANO STRATEGICO completo per il periodo. Il piano sarà la base
su cui un copywriter scriverà i singoli post in un secondo momento.

Per OGNI slot di scheduling (data + ora + piattaforma) decidi:
- pillar (categoria di contenuto)
- persona_target (a chi parli prevalentemente)
- angle (l'angolo SPECIFICO di questo post — non cosa scriverai parola per parola, ma di cosa parlerai)
- hook_type (osservazione, domanda, scenario, dato, contro-intuizione, lista, problema)
- cta_goal (lead, engagement, traffic, brand, sales)
- visual_direction (idea generica del visual, 1 frase)

Vincoli sull'angle:
- DEVE essere specifico: NO "L'importanza di X" → SI "Il vero costo del re-work in produzione lean: 7 minuti per pezzo"
- DEVE essere DIVERSO da tutti gli altri post del piano (no ripetizioni concettuali)
- DEVE coprire l'intero arco editoriale, non solo i topic facili
- DEVE rispettare i vincoli deontologici per settori regolamentati

Vincoli su pillar_distribution:
- Almeno 3 pillar diversi
- Nessun pillar oltre il 50% del totale
- Coerente con gli obiettivi del progetto{$pillarConstraintBullet}

## OUTPUT (JSON valido — niente markdown, niente preambolo)
{
  "editorial_narrative": "Frase di 2-3 righe che descrive l'arco narrativo del periodo per questo brand",
  "pillar_distribution": { "<pillar>": 0.0 - 1.0, ... (somma = 1.0) },
  "posts": [
    {
      "scheduled_date": "YYYY-MM-DD",
      "scheduled_time": "HH:MM",
      "platform": "<platform>",
      "content_type": "post|story|reel",
      "pillar": "<pillar>",
      "persona_target": "<nome persona>",
      "angle": "<l'angolo specifico>",
      "hook_type": "<tipo hook>",
      "cta_goal": "<lead|engagement|traffic|brand|sales>",
      "visual_direction": "<1 frase>"
    },
    ...
  ]
}
PROMPT;
    }

    /**
     * COPY prompt — restituito come 3 parti per il caching:
     *   - 'static_brand'    → brand context (cachable, identico tra batch)
     *   - 'static_strategy' → strategy plan completo (cachable, identico tra batch)
     *   - 'dynamic'         → subset dei post da scrivere in questo batch
     *
     * @return array{static_brand: string, static_strategy: string, dynamic: string}
     */
    public function buildCopyPromptParts(
        string $brandName,
        array  $brandInfo,
        string $ragContext,
        array  $strategyPlan,
        array  $batchPosts,
        int    $batchNum,
        int    $totalBatches,
    ): array {
        $voiceSection = $this->formatVoiceExamplesForPrompt(
            $brandInfo['voice_examples'] ?? [],
            []  // batch ha sempre più platform, mostra tutti gli esempi
        );

        $staticBrand = "## BRAND\n"
                     . "Nome: {$brandName}\n"
                     . "Settore: " . $this->arr($brandInfo, 'sector', 'N/A') . "\n"
                     . "Descrizione: " . $this->arr($brandInfo, 'description', 'N/A') . "\n"
                     . "Tono di voce: " . $this->arr($brandInfo, 'tone_of_voice', 'professionale') . "\n"
                     . "Valori: " . $this->arrJson($brandInfo, 'brand_values', '[]') . "\n\n"
                     . $voiceSection
                     . "## KNOWLEDGE BASE\n"
                     . $this->str($ragContext, 'Non disponibile') . "\n";

        $strategyJson = json_encode($strategyPlan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $staticStrategy = "## STRATEGY PLAN (già approvato — rispetta angle/pillar/hook/cta_goal di ogni post)\n"
                        . "{$strategyJson}\n";

        $batchPostsJson    = json_encode($batchPosts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $antiInventionTldr = $this->buildAntiInventionReminder();
        $dynamic = "## BATCH {$batchNum}/{$totalBatches}\n"
                 . "Scrivi il copy finale SOLO per questi post (estratti dal piano strategico sopra):\n\n"
                 . "{$batchPostsJson}\n\n"
                 . "## ISTRUZIONI\n"
                 . "Per ogni post produci: content (testo finale completo), hashtags (array), visual_suggestion (più dettagliato di visual_direction), call_to_action (testo specifico).\n"
                 . "NON ridiscutere angle/pillar/hook_type — sono già decisi nel piano. Tu li ESEGUI nel copy.\n"
                 . "Mantieni date, time, platform, content_type esattamente come nel piano.\n"
                 . "Rispetta tutte le regole dal system prompt (anti-slop, anti-allucinazione, vincoli deontologici).\n\n"
                 . "{$antiInventionTldr}\n\n"
                 . "## OUTPUT (JSON array)\n"
                 . "[\n"
                 . "  {\n"
                 . "    \"scheduled_date\": \"YYYY-MM-DD\",\n"
                 . "    \"scheduled_time\": \"HH:MM\",\n"
                 . "    \"platform\": \"...\",\n"
                 . "    \"content_type\": \"post|story|reel\",\n"
                 . "    \"pillar\": \"...\",\n"
                 . "    \"content\": \"TESTO FINALE COMPLETO\",\n"
                 . "    \"hashtags\": [\"...\"],\n"
                 . "    \"visual_suggestion\": \"...\",\n"
                 . "    \"call_to_action\": \"TESTO SPECIFICO\"\n"
                 . "  }\n"
                 . "]\n\n"
                 . "Rispondi SOLO con il JSON array, senza markdown, senza preambolo.";

        return [
            'static_brand'    => $staticBrand,
            'static_strategy' => $staticStrategy,
            'dynamic'         => $dynamic,
        ];
    }
    // ──────────────────────────────────────────────────────────
    //  Helpers privati
    // ──────────────────────────────────────────────────────────

    /**
     * Genera la sezione "REGOLE PER PIATTAFORMA" mostrando solo le piattaforme
     * effettivamente attive nel progetto.
     */
    private function buildPlatformGuidelines(array $platforms): string
    {
        $rules = [];

        if (in_array('instagram', $platforms, true)) {
            $rules[] = <<<'RULE'
### INSTAGRAM
- Testo: 138-150 caratteri per massimo engagement (non oltre 2200)
- Caption: inizia con un hook forte nelle prime 2 righe (visibili senza "altro")
- Hashtag: 5-10 hashtag, mix brand + niche + trending, mai inseriti nel corpo del testo
- Emoji: usale strategicamente per spezzare il testo, max 3-5
- CTA: sempre in ultima riga, separata da una riga vuota
- Reel: prima frase = hook entro 3 secondi, testo brevissimo
- Story: max 2 righe di testo, domanda o sondaggio quando possibile
RULE;
        }

        if (in_array('linkedin', $platforms, true)) {
            $rules[] = <<<'RULE'
### LINKEDIN
- Testo: 1300 caratteri ideali per post organico (max 3000)
- Struttura: hook (1-2 righe) → problema/insight → sviluppo → CTA
- Tono: professionale ma umano, prima persona quando possibile
- Hashtag: max 3-5, solo hashtag di settore rilevanti
- Emoji: usarle con parsimonia, solo come separatori visivi
- No gergo troppo tecnico: il target sono decision maker, non tecnici
- CTA: domanda aperta per stimolare commenti preferita a link esterni
RULE;
        }

        if (in_array('facebook', $platforms, true)) {
            $rules[] = <<<'RULE'
### FACEBOOK
- Testo: 40-80 caratteri per post con immagine, fino a 400 per post solo testo
- Tono: più informale rispetto a LinkedIn, conversazionale
- CTA: diretta e visibile, invita alla condivisione
- Hashtag: max 2-3, non indispensabili su Facebook
- Video/Reel: descrizione breve, i primi 3 secondi sono cruciali
RULE;
        }

        if (in_array('google_business', $platforms, true)) {
            $rules[] = <<<'RULE'
### GOOGLE BUSINESS PROFILE
- Testo: max 1500 caratteri, ideale 150-300
- Tono: locale, concreto, orientato all'azione immediata
- Includi sempre un riferimento geografico o contestuale se pertinente
- CTA: "Chiama ora", "Prenota", "Scopri di più", "Vieni a trovarci"
- Hashtag: NON usare (non supportati su GBP)
- Aggiorna con offerte, eventi, novità concrete — non contenuti generici
RULE;
        }

        if (empty($rules)) {
            return '';
        }

        return "## REGOLE PER PIATTAFORMA\n" . implode("\n\n", $rules) . "\n\n";
    }

    /**
     * Genera esempi di CTA calibrati sugli obiettivi del progetto.
     * Mostra solo gli obiettivi attivi.
     */
    private function buildCtaExamples(array $objectives): string
    {
        $allExamples = [
            'lead_generation' => <<<'CTA'
Esempi per lead_generation:
- "Prenota una call gratuita → link in bio"
- "Scarica la guida gratuita, link nei commenti"
- "Rispondimi in DM con 'INFO' per ricevere i dettagli"
CTA,
            'brand_awareness' => <<<'CTA'
Esempi per brand_awareness:
- "Tagga un collega che deve leggere questo"
- "Salva questo post per rileggerlo con calma"
- "Sei d'accordo? Dimmi la tua nei commenti"
CTA,
            'engagement' => <<<'CTA'
Esempi per engagement:
- "Tu come gestiresti questa situazione?"
- "Cosa ne pensi? ⬇️"
- "Vota nelle stories: A o B?"
CTA,
            'sales' => <<<'CTA'
Esempi per sales:
- "Solo per questa settimana — link in bio"
- "Scrivici per il preventivo personalizzato"
- "Disponibilità limitata — contattaci oggi"
CTA,
            'traffic' => <<<'CTA'
Esempi per traffic:
- "Articolo completo sul blog → link in bio"
- "Tutti i dettagli su [sito] — link nei commenti"
CTA,
        ];

        $lines = [];
        foreach ($objectives as $obj) {
            if (isset($allExamples[$obj])) {
                $lines[] = $allExamples[$obj];
            }
        }

        if (empty($lines)) {
            $lines[] = $allExamples['brand_awareness'];
        }

        return implode("\n\n", $lines);
    }

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
