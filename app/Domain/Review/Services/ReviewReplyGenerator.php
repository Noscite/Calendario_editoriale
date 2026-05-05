<?php

declare(strict_types=1);

namespace App\Domain\Review\Services;

use App\Domain\Brand\Models\Brand;
use App\Domain\Generation\Services\AnthropicApiClient;
use App\Domain\Review\Contracts\KnowledgeRetrieverInterface;
use App\Domain\Review\Enums\ReplyTone;
use App\Domain\Review\Models\Review;

/**
 * Genera la bozza di risposta a una review.
 *
 * Pipeline:
 *  - Recupera contesto KB pertinente via cosine similarity (top-3 chunk)
 *  - Costruisce system prompt con: voce brand, tono richiesto, strategia
 *    di marketing (M2 scoring → recovery/advocacy/upsell/testimonial/none),
 *    blocco deontologico se settore regolato (psicologia/sanità/legale/finanza)
 *  - Chiama Claude Sonnet con system prompt PLAIN TEXT (niente istruzioni JSON,
 *    AnthropicApiClient::call() bypassa il DEFAULT_SYSTEM_PROMPT JSON quando
 *    riceve un systemPrompt esplicito)
 *  - Ritorna body + metadati per persisterli su review_replies
 */
class ReviewReplyGenerator
{
    public const MODEL       = 'claude-sonnet-4-20250514';
    private const MAX_TOKENS = 1500;

    /** @var array<int, string> */
    private const REGULATED_SECTORS = [
        'psicologia', 'psicoterapia', 'psichiatria',
        'medicina', 'sanità', 'odontoiatria', 'fisioterapia',
        'legale', 'avvocato', 'notaio',
        'finanziario', 'consulenza finanziaria',
    ];

    public function __construct(
        private readonly AnthropicApiClient $apiClient,
        private readonly KnowledgeRetrieverInterface $retriever,
    ) {
    }

    /**
     * @return array{body:string,tone_used:string,marketing_strategy:string,kb_chunks_used:array<int,int>,generated_by_model:string,input_tokens:?int,output_tokens:?int}
     */
    public function generate(
        Review $review,
        ReplyTone $tone = ReplyTone::BrandDefault,
        ?string $marketingStrategyOverride = null,
    ): array {
        $brand  = $review->brand;
        $client = $this->apiClient->withBrand($brand);

        $query  = $this->buildKbQuery($review);
        $chunks = $this->retriever->retrieve($brand, $query, 3);

        $strategy = $marketingStrategyOverride
            ?? $review->marketing_opportunity?->value
            ?? 'none';

        $systemPrompt = $this->buildSystemPrompt($brand, $tone, $strategy, $chunks);
        $userPrompt   = $this->buildUserPrompt($review);

        $response = $client->call(
            prompt: $userPrompt,
            maxTokens: self::MAX_TOKENS,
            model: self::MODEL,
            systemPrompt: $systemPrompt,
        );

        return [
            'body'                => $this->extractText($response),
            'tone_used'           => $tone->value,
            'marketing_strategy'  => $strategy,
            'kb_chunks_used'      => array_values(array_map(static fn (array $c): int => (int) $c['chunk_id'], $chunks)),
            'generated_by_model'  => self::MODEL,
            'input_tokens'        => isset($response['usage']['input_tokens']) ? (int) $response['usage']['input_tokens'] : null,
            'output_tokens'       => isset($response['usage']['output_tokens']) ? (int) $response['usage']['output_tokens'] : null,
        ];
    }

    private function buildKbQuery(Review $review): string
    {
        $topics = is_array($review->topics) ? implode(' ', $review->topics) : '';
        return trim(($review->comment ?? '') . ' ' . $topics);
    }

    /**
     * @param  array<int, array{chunk_id:int,content:string,similarity:float,document_filename:string}>  $chunks
     */
    private function buildSystemPrompt(Brand $brand, ReplyTone $tone, string $strategy, array $chunks): string
    {
        $brandName = $brand->name;
        $sector    = $brand->sector ?? 'non specificato';
        $voice     = $brand->tone_of_voice ?? 'professionale';

        $voiceExamples = '';
        if (is_array($brand->voice_examples) && $brand->voice_examples !== []) {
            $first = collect($brand->voice_examples)
                ->take(3)
                ->map(fn ($ex) => is_array($ex) ? ($ex['content'] ?? '') : (string) $ex)
                ->filter()
                ->all();
            if ($first !== []) {
                $voiceExamples = "- " . implode("\n- ", $first);
            }
        }

        $toneInstruction     = $this->toneInstruction($tone);
        $strategyInstruction = $this->strategyInstruction($strategy);
        $kbContext           = $this->formatKbChunks($chunks);
        $deontological       = $this->deontologicalBlock($sector);

        return <<<PROMPT
Sei l'assistente che scrive risposte alle recensioni Google Business Profile
per il brand "{$brandName}" (settore: {$sector}).

═══ VINCOLI ASSOLUTI SUI FATTI ═══
È VIETATO inventare qualsiasi dato fattuale. Specificamente:
- Numeri (clienti, anni, ore, prezzi, percentuali, valutazioni medie)
- Percentuali e statistiche
- Nomi di prodotti, corsi, certificazioni, premi
- Date, anni di fondazione, durate
- Caratteristiche specifiche dell'offerta

Puoi menzionare un dato fattuale SOLO SE è letteralmente nei chunk KB
sotto. Se vuoi citare un dato e non lo trovi nei chunk, NON menzionarlo.
È meglio una risposta generica vera che una specifica inventata.

ESEMPI:
- ❌ NO: "70% pratica 30% teoria" (se non è nei chunk verbatim)
- ❌ NO: "abbiamo formato oltre 200 PMI" (se non è nei chunk verbatim)
- ❌ NO: "20 anni di esperienza" (se non è nei chunk verbatim)
- ✓ SI: "PRIMUS, il workshop esperienziale di 4 ore" (se nei chunk dice
  letteralmente PRIMUS e 4 ore)
- ✓ SI: ringraziamento generico senza cifre specifiche

═══ USO POSITIVO DELLA KNOWLEDGE BASE ═══
I VINCOLI sopra ti vietano di inventare. Ma NON ti vietano di usare ciò
che è documentato.

Se i chunk KB sotto contengono NOMI PROPRI di:
- Prodotti, corsi, percorsi formativi (es. "PRIMUS", "AI Ratio")
- Certificazioni, qualifiche (es. "Certified AI Productivity User", "ISO 9001")
- Metodologie, framework proprietari (es. "Metodo X", "Approccio Lean Y")
- Servizi, linee di prodotto (es. "Premium Care", "Linea Gold")

USALI VERBATIM quando pertinenti al commento del reviewer. Questo è uso
corretto della KB, non invenzione.

ESEMPI CORRETTI (assumendo siano nei chunk):
- ✓ "Il workshop PRIMUS è pensato proprio per..."
- ✓ "Siamo felici che la certificazione Certified AI Productivity User..."
- ✓ "Il nostro percorso AI Ratio mira a..."

ESEMPI SBAGLIATI:
- ❌ "il nostro famoso workshop" (vago, anonimo, evita di personalizzare)
- ❌ "il nostro corso esclusivo" (generico, perde valore brand)

REGOLA OPERATIVA: se la review menziona un'area tematica (es. "workshop",
"corso", "certificazione") e nei chunk c'è un nome proprio specifico per
quell'area, USALO. Personalizza. Non rifugiarti nel generico per prudenza.

═══ ALTRE REGOLE NON NEGOZIABILI ═══
- Risposta in italiano corrente.
- Lunghezza: 40-80 parole MAX (Google sweet spot per local SEO).
- Tono di voce di base del brand: {$voice}
- Esempi di voce del brand:
{$voiceExamples}
- Mai negare problemi o giustificarsi pubblicamente per recensioni negative.
- Sempre proporre canale privato per gestire critiche concrete (telefono/email/sito).
- Ringraziare per nome se il reviewer ha un nome non anonimo.
- Includere il nome del brand naturalmente (utile per SEO locale).
- Mai promettere risultati specifici o garanzie non verificabili.

═══ TONO DI QUESTA RISPOSTA ═══
{$toneInstruction}

═══ STRATEGIA DI MARKETING ═══
{$strategyInstruction}

NOTA SULLA STRATEGIA: amplifica positivamente nel TONO e nell'ENTUSIASMO,
non nei FATTI. Mostrare entusiasmo non significa inventare specificità.
{$deontological}

═══ CONTESTO DALLA KNOWLEDGE BASE DEL BRAND ═══
Usa SOLO se i chunk sotto sono effettivamente pertinenti al commento del
reviewer. Se i chunk parlano di argomenti diversi da quelli citati nella
review, NON forzare riferimenti. Meglio rispondere genericamente che
menzionare cose fuori contesto.

{$kbContext}

═══ SELF-CHECK PRIMA DI SCRIVERE ═══
Prima di scrivere la risposta, verifica mentalmente:
1. Ogni numero/cifra/percentuale che sto per scrivere è LETTERALMENTE nei
   chunk KB sopra? (Se no → riscrivi senza)
2. Ogni nome di prodotto/certificazione/premio che sto per scrivere è
   LETTERALMENTE nei chunk KB sopra? (Se no → riscrivi senza)
3. Sto promettendo risultati o garanzie? (Se sì → riscrivi)

OUTPUT: solo il testo della risposta, senza preamboli, senza markdown, senza
firma. Se la recensione è priva di commento testuale (solo stelle), genera
una risposta breve di ringraziamento (per 5★) o di interesse a saperne di più
(per ≤3★) coerente con tono e strategia.
PROMPT;
    }

    private function buildUserPrompt(Review $review): string
    {
        $reviewer  = $review->reviewer_name ?? 'cliente';
        $rating    = $review->rating;
        $comment   = $review->comment ?? '(senza commento)';
        $sentiment = $review->sentiment?->value ?? 'unknown';

        return <<<PROMPT
RECENSIONE A CUI RISPONDERE:

Reviewer: {$reviewer}
Rating: {$rating}/5 stelle
Sentiment: {$sentiment}
Commento: "{$comment}"

Scrivi la risposta seguendo TUTTE le regole sopra.
PROMPT;
    }

    private function toneInstruction(ReplyTone $tone): string
    {
        return match ($tone) {
            ReplyTone::BrandDefault => "Usa il tono di voce di base del brand definito sopra.",
            ReplyTone::Empathetic   => "Mostra ascolto attivo e comprensione. Riconosci i sentimenti del reviewer prima di proporre soluzioni.",
            ReplyTone::Professional => "Tono distaccato e professionale. Niente familiarità eccessiva.",
            ReplyTone::Solution     => "Focus sulla risoluzione concreta del problema. Vai diretto, proponi azioni.",
            ReplyTone::Gratitude    => "Tono caloroso di ringraziamento. Esprimi gratitudine genuina senza eccessi.",
            ReplyTone::Formal       => "Registro formale, lessico curato, niente informalità.",
        };
    }

    private function strategyInstruction(string $strategy): string
    {
        $kbHint = " Se i chunk KB contengono nomi propri pertinenti al commento (prodotti, corsi, certificazioni, metodologie), incorporali per dare specificità genuina alla risposta.";

        return match ($strategy) {
            'recovery'    => "Recensione critica recuperabile. Riconosci il problema citando SOLO dettagli effettivamente presenti nel commento del reviewer (mai inventarne). Non scusarti genericamente. Proponi canale privato concreto. Obiettivo: trasformare in cliente recuperato." . $kbHint,
            'advocacy'    => "Cliente entusiasta, potenziale ambassador. Amplifica positivamente con tono caloroso ma resta sui fatti documentati nei chunk KB. Personalizza richiamando elementi presenti nel commento del reviewer o nei chunk. Invito sottile a continuare la relazione (es. newsletter), senza inventare prodotti o eventi." . $kbHint,
            'upsell'      => "Cliente soddisfatto che potrebbe acquistare di più. Puoi menzionare un servizio correlato SOLO se esplicitamente presente nei chunk KB. In assenza di chunk pertinenti, fai un invito generico al contatto privato senza inventare offerte." . $kbHint,
            'testimonial' => "Recensione perfetta da promuovere. Ringrazia evidenziando il valore percepito espresso DAL REVIEWER nel commento — senza inventare cifre, risultati, statistiche o aneddoti. Considera invito a condividere su altri canali." . $kbHint,
            default       => "Risposta standard educata e professionale.",
        };
    }

    /**
     * @param  array<int, array{chunk_id:int,content:string,similarity:float,document_filename:string}>  $chunks
     */
    private function formatKbChunks(array $chunks): string
    {
        if ($chunks === []) {
            return '(nessun contesto KB rilevante disponibile)';
        }

        $lines = [];
        foreach ($chunks as $i => $chunk) {
            $lines[] = sprintf(
                "[%d] (da %s, sim %.2f) %s",
                $i + 1,
                $chunk['document_filename'],
                $chunk['similarity'],
                mb_substr($chunk['content'], 0, 400),
            );
        }
        return implode("\n\n", $lines);
    }

    private function deontologicalBlock(string $sector): string
    {
        $sectorLower = mb_strtolower($sector);
        foreach (self::REGULATED_SECTORS as $regulated) {
            if (str_contains($sectorLower, $regulated)) {
                return "\n" . $this->deontologicalForSector($regulated);
            }
        }
        return '';
    }

    private function deontologicalForSector(string $sector): string
    {
        return match (true) {
            str_contains($sector, 'psico') => <<<TXT

VINCOLI DEONTOLOGICI (PSICOLOGIA/PSICOTERAPIA):
- NON discutere casi clinici specifici, anche in modo allusivo.
- NON promettere guarigione, miglioramento, esiti.
- NON confermare/negare presa in carico di nessuno.
- Riferimenti a problemi clinici → reindirizza SEMPRE a colloquio privato.
- Tutela del segreto professionale è prioritaria.
TXT,
            str_contains($sector, 'medic') || str_contains($sector, 'sanit') || str_contains($sector, 'odontoiatr') || str_contains($sector, 'fisioterap') => <<<TXT

VINCOLI DEONTOLOGICI (SANITÀ):
- NON dare consigli medici nella risposta.
- NON discutere terapie, diagnosi, prognosi.
- NON garantire risultati clinici.
- Riferimenti a problemi di salute → reindirizza a visita.
TXT,
            str_contains($sector, 'legal') || str_contains($sector, 'avvocat') || str_contains($sector, 'notaio') => <<<TXT

VINCOLI DEONTOLOGICI (LEGALE):
- NON dare pareri legali nella risposta.
- NON anticipare strategie processuali o esiti.
- NON discutere casi specifici, anche in modo allusivo.
- Riferimenti a questioni legali → reindirizza a consulenza privata.
TXT,
            str_contains($sector, 'finanz') => <<<TXT

VINCOLI DEONTOLOGICI (FINANZA):
- NON dare raccomandazioni di investimento.
- NON garantire rendimenti.
- NON discutere portafogli o casi specifici.
- Riferimenti a investimenti → reindirizza a consulenza personalizzata.
TXT,
            default => '',
        };
    }

    /**
     * @param  array<string,mixed>  $response
     */
    private function extractText(array $response): string
    {
        if (isset($response['content'][0]['text']) && is_string($response['content'][0]['text'])) {
            return trim($response['content'][0]['text']);
        }
        return trim((string) ($response['text'] ?? ''));
    }
}
