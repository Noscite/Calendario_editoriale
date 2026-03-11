<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Help\Models\HelpArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class SupportAgentController extends Controller
{
    // POST /api/support/chat
    public function chat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message'              => 'required|string|max:2000',
            'conversation_history' => 'nullable|array',
            'conversation_history.*.role'    => 'required_with:conversation_history|string|in:user,assistant',
            'conversation_history.*.content' => 'required_with:conversation_history|string',
            'context'              => 'nullable|array',
            'context.current_page' => 'nullable|string',
            'context.current_section' => 'nullable|string',
        ]);

        $user    = $request->user();
        $message = $data['message'];
        $history = $data['conversation_history'] ?? [];
        $context = $data['context'] ?? [];

        // 1. Search relevant articles (max 3)
        $relevantArticles = $this->findRelevantArticles($message);

        // 2. Build articles context for prompt
        $articlesContext = $this->formatArticlesForPrompt($relevantArticles);

        // 3. Build user context
        $userName    = $user->full_name ?? $user->email ?? 'Utente';
        $orgName     = $user->organization?->name ?? 'N/A';
        $planName    = $user->organization?->subscription?->plan?->name ?? 'N/A';
        $currentPage = $context['current_page'] ?? 'N/A';
        $section     = $context['current_section'] ?? 'N/A';

        // 4. Build system prompt
        $systemPrompt = $this->buildSystemPrompt(
            $userName, $orgName, $planName, $currentPage, $section, $articlesContext
        );

        // 5. Build messages array
        $messages = [];
        foreach ($history as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        // 6. Call Anthropic API
        $apiKey = config('services.anthropic.api_key', '');
        if (empty($apiKey)) {
            return response()->json([
                'reply' => 'Il servizio di assistenza AI non è al momento disponibile. Scrivi a supporto@noscite.it per ricevere aiuto.',
                'suggested_articles' => $this->formatSuggestedArticles($relevantArticles),
            ]);
        }

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])
                ->timeout(30)
                ->post('https://api.anthropic.com/v1/messages', [
                    'model'      => 'claude-sonnet-4-20250514',
                    'max_tokens' => 1024,
                    'system'     => $systemPrompt,
                    'messages'   => $messages,
                ]);

            if (! $response->successful()) {
                Log::warning('[SUPPORT] Anthropic API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return response()->json([
                    'reply' => 'Mi dispiace, sto avendo qualche difficoltà tecnica. Riprova tra qualche istante oppure scrivi a supporto@noscite.it.',
                    'suggested_articles' => $this->formatSuggestedArticles($relevantArticles),
                ]);
            }

            $responseData = $response->json();
            $reply = $responseData['content'][0]['text'] ?? 'Mi dispiace, non sono riuscita a elaborare una risposta.';

            return response()->json([
                'reply'              => $reply,
                'suggested_articles' => $this->formatSuggestedArticles($relevantArticles),
            ]);
        } catch (\Throwable $e) {
            Log::error('[SUPPORT] Chat error: ' . $e->getMessage());

            return response()->json([
                'reply' => 'Mi dispiace, si è verificato un errore. Riprova tra qualche istante o scrivi a supporto@noscite.it.',
                'suggested_articles' => $this->formatSuggestedArticles($relevantArticles),
            ]);
        }
    }

    private function findRelevantArticles(string $query): \Illuminate\Support\Collection
    {
        return HelpArticle::where('is_active', true)
            ->where(function ($qb) use ($query) {
                $words = array_filter(explode(' ', $query));
                foreach (array_slice($words, 0, 5) as $word) {
                    if (mb_strlen($word) >= 3) {
                        $qb->orWhere('title', 'ilike', "%{$word}%")
                           ->orWhere('excerpt', 'ilike', "%{$word}%")
                           ->orWhere('content', 'ilike', "%{$word}%");
                    }
                }
            })
            ->with('category')
            ->limit(3)
            ->get();
    }

    private function formatArticlesForPrompt(\Illuminate\Support\Collection $articles): string
    {
        if ($articles->isEmpty()) {
            return 'Nessun articolo specifico trovato per questa domanda.';
        }

        return $articles->map(function ($a) {
            $contentPreview = mb_substr(strip_tags($a->content), 0, 500);
            return "### {$a->title}\nCategoria: {$a->category->title}\n{$contentPreview}";
        })->implode("\n\n---\n\n");
    }

    private function formatSuggestedArticles(\Illuminate\Support\Collection $articles): array
    {
        return $articles->map(fn ($a) => [
            'title'    => $a->title,
            'slug'     => $a->slug,
            'category' => $a->category->title,
        ])->values()->toArray();
    }

    private function buildSystemPrompt(
        string $userName,
        string $orgName,
        string $planName,
        string $currentPage,
        string $section,
        string $articlesContext,
    ): string {
        return <<<PROMPT
Sei Kalen, l'assistente virtuale di Noscite Calendar.

Noscite Calendar è una piattaforma italiana che aiuta le PMI a creare
e pubblicare automaticamente calendari editoriali sui social media,
usando l'intelligenza artificiale.

## CHI SEI

Sei un'assistente esperta, paziente e pratica. Conosci ogni funzionalità
della piattaforma e sai spiegare le cose in modo semplice, senza gergo
tecnico. Parli come una collega competente, non come un manuale.

## TONO E STILE

- Usa sempre il **tu** (mai il lei)
- Sii concisa: risposte brevi e dirette, poi espandi solo se necessario
- Usa il **grassetto** per i nomi dei pulsanti e delle sezioni
- Usa elenchi numerati per i passaggi operativi
- Usa emoji con moderazione (una per risposta al massimo)
- Lingua: sempre italiano

## CONTESTO UTENTE CORRENTE

- Nome: {$userName}
- Organizzazione: {$orgName}
- Piano attivo: {$planName}
- Pagina corrente: {$currentPage}
- Sezione: {$section}

## DOCUMENTAZIONE DISPONIBILE

{$articlesContext}

## COME RISPONDERE

### Se l'utente ha una domanda operativa
Rispondi con i passi esatti da seguire nell'interfaccia.
Cita sempre il nome esatto del pulsante o della sezione in **grassetto**.
Esempio: "Vai su **I miei Brand** → clicca **+ Nuovo Brand**"

### Se l'utente ha un problema tecnico
1. Chiedi conferma del problema se non è chiaro
2. Dai la soluzione più probabile per prima
3. Se non si risolve, suggerisci di scrivere a supporto@noscite.it
   indicando l'ID del post o del brand coinvolto

### Se l'utente è frustrato o arrabbiato
Inizia sempre riconoscendo il disagio senza scuse eccessive:
"Capisco, è fastidioso quando succede. Proviamo a risolverlo subito."
Non essere eccessivamente apologetico. Vai dritto alla soluzione.

### Se l'utente chiede di funzionalità non esistenti
Spiega onestamente che quella funzionalità non è ancora disponibile.
Se è plausibile che venga sviluppata, di' "non è ancora disponibile"
senza promettere nulla. Non inventare mai funzionalità.

### Se l'utente chiede informazioni sull'abbonamento o prezzi
Fornisci le informazioni corrette dai piani (Basic €29, Standard €79,
Pro €199, Enterprise su misura). Per modifiche all'abbonamento
rimanda a **Impostazioni → Abbonamento**.

### Se l'utente chiede un rimborso o ha una disputa commerciale
"Per questioni relative alla fatturazione e ai rimborsi devo
passarti al team commerciale. Scrivici a amministrazione@noscite.it
con il tuo numero di ordine e ti risponderemo entro 1 giorno lavorativo."

### Se la domanda non riguarda Noscite Calendar
"Posso aiutarti solo con le funzionalità di Noscite Calendar.
Per questa domanda ti consiglio di cercare altrove.
Hai altre domande sulla piattaforma?"

### Se non conosci la risposta
"Non ho informazioni precise su questo. Ti consiglio di scrivere a
supporto@noscite.it: il team tecnico risponde entro poche ore."
Non inventare mai risposte.

## CONTESTO PAGINA CORRENTE

Usa il contesto della pagina per dare risposte più precise:
- Se l'utente è su /brand/* → probabilmente ha domande su brand o social
- Se è su /project/* → probabilmente ha domande su post o calendario
- Se è su /settings → probabilmente ha domande su account o abbonamento
- Se è su /help → sta esplorando la documentazione, suggerisci articoli

## SUGGERISCI ARTICOLI PERTINENTI

Alla fine della risposta, se ci sono articoli rilevanti nella
documentazione disponibile, indicali come approfondimento.
Non elencarli se non aggiungono valore alla risposta già data.
PROMPT;
    }
}
