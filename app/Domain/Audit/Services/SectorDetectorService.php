<?php

declare(strict_types=1);

namespace App\Domain\Audit\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Rileva automaticamente il settore di un'azienda dall'URL del sito.
 *
 * Strategia in 2 fasi:
 * 1. Fetch HTTP veloce per estrarre title e meta description
 * 2. Chiamata Claude Sonnet per dedurre settore e vincoli deontologici
 */
final class SectorDetectorService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL   = 'claude-sonnet-4-5';

    /**
     * Settori con vincoli deontologici/legali specifici.
     * Usato sia per la detection che per il prompt dell'audit scorer.
     */
    public const REGULATED_SECTORS = [
        'medico'      => ['Medico', 'Clinica', 'Ospedale', 'Chirurgo', 'Specialista'],
        'psicologia'  => ['Psicologo', 'Psicoterapeuta', 'Psichiatria', 'Counseling'],
        'legale'      => ['Avvocato', 'Studio Legale', 'Notaio', 'Consulente Legale'],
        'farmacia'    => ['Farmacia', 'Parafarmacia'],
        'finanza'     => ['Consulente Finanziario', 'Banca', 'Assicurazioni', 'Investimenti'],
        'alimentare'  => ['Ristorante', 'Bar', 'Pizzeria', 'Catering', 'Food'],
        'educazione'  => ['Scuola', 'Università', 'Formazione', 'Corso', 'Istruzione'],
        'immobiliare' => ['Agenzia Immobiliare', 'Immobiliare', 'Real Estate'],
        'estetica'    => ['Centro Estetico', 'Parrucchiere', 'Spa', 'Benessere'],
        'fitness'     => ['Palestra', 'Personal Trainer', 'Yoga', 'Pilates'],
        'artigianato' => ['Artigiano', 'Officina', 'Idraulico', 'Elettricista', 'Falegname'],
        'tech'        => ['Software', 'Agenzia Digitale', 'Web Agency', 'SaaS', 'App'],
        'commercio'   => ['Negozio', 'E-commerce', 'Vendita', 'Shop'],
        'consulenza'  => ['Consulenza', 'Management', 'Business', 'Strategia'],
        'turismo'     => ['Hotel', 'Albergo', 'B&B', 'Agriturismo', 'Tour Operator'],
        'altro'       => [],
    ];

    /**
     * Vincoli deontologici per settore — usati nel prompt audit scorer.
     */
    public const DEONTOLOGICAL_CONSTRAINTS = [
        'psicologia' => [
            'forbidden' => [
                'Evitare promesse di risultato ("ritrova serenità", "elimina l\'ansia")',
                'Non suggerire testimonianze pubbliche di pazienti (violazione deontologica Ordine Psicologi)',
                'Non suggerire CTA con urgency aggressiva ("prenota subito prima che sia tardi")',
                'Non suggerire garanzie di guarigione o miglioramento',
                'Non suggerire confronti con altri professionisti',
            ],
            'preferred' => [
                'Valorizzare credenziali accademiche (PhD, specializzazioni) nell\'H1 e nel titolo',
                'Social proof attraverso loghi ordini professionali, anni di esperienza, approccio terapeutico',
                'CTA neutra e rispettosa: "Richiedi un primo colloquio conoscitivo"',
                'Enfatizzare riservatezza e setting professionale',
                'Schema markup ProfessionalService con specializzazione',
            ],
        ],
        'medico' => [
            'forbidden' => [
                'Non suggerire testimonianze di pazienti (privacy e deontologia medica)',
                'Non suggerire promesse di guarigione o risultati specifici',
                'Non suggerire prezzi in evidenza per prestazioni sanitarie',
                'Non suggerire comparazioni con altri medici o strutture',
            ],
            'preferred' => [
                'Enfatizzare specializzazione, anni di esperienza, strutture convenzionate',
                'Schema markup Physician/MedicalClinic',
                'CTA: "Prenota una visita" — neutro e professionale',
                'Valorizzare pubblicazioni scientifiche se presenti',
            ],
        ],
        'legale' => [
            'forbidden' => [
                'Non suggerire promesse di vittoria o risultati garantiti (vietato dal codice deontologico forense)',
                'Non suggerire confronti con altri studi legali',
                'Non suggerire prezzi "a partire da" per prestazioni legali (può essere fuorviante)',
                'Non suggerire testimonial di clienti con nomi reali',
            ],
            'preferred' => [
                'Enfatizzare specializzazioni, iscrizione all\'Albo, anni di esperienza',
                'Case history anonimizzate ("abbiamo seguito oltre 200 controversie societarie")',
                'Schema markup LegalService con aree di specializzazione',
                'CTA: "Richiedi una consulenza" — mai "Vinci la tua causa"',
            ],
        ],
        'finanza' => [
            'forbidden' => [
                'Non suggerire promesse di rendimento o guadagno',
                'Non suggerire confronti con performance di mercato',
                'Non usare "guadagna", "rendimento garantito" in CTA o headline',
            ],
            'preferred' => [
                'Enfatizzare iscrizioni Consob/Albo, certificazioni (CFP, CFA)',
                'Approccio basato su pianificazione, non su rendimento',
                'Schema markup FinancialService',
            ],
        ],
        'farmacia' => [
            'forbidden' => [
                'Non suggerire claim salutistici non autorizzati',
                'Non suggerire confronti di prezzo su farmaci con obbligo di ricetta',
            ],
            'preferred' => [
                'Enfatizzare servizi aggiuntivi (misurazione pressione, consegna a domicilio)',
                'Valorizzare farmacisti specializzati e orari di apertura',
            ],
        ],
        'alimentare' => [
            'forbidden' => [
                'Nessun vincolo deontologico specifico — applicare best practice standard',
            ],
            'preferred' => [
                'Foto del piatto/prodotto above-fold è il fattore conversione principale',
                'Prenotazione/ordine online deve essere la CTA primaria',
                'Orari e indirizzo devono essere nel primo viewport',
                'Menu accessibile in massimo 2 click',
            ],
        ],
    ];

    public function detect(string $url): array
    {
        $apiKey = config('services.anthropic.api_key', env('ANTHROPIC_API_KEY', ''));

        if (! $apiKey) {
            return $this->fallbackDetection($url);
        }

        // Fetch rapido per title e meta
        $pageMeta = $this->fetchMeta($url);

        Log::info("[SECTOR-DETECTOR] Analyzing: {$url}");
        Log::info("[SECTOR-DETECTOR] Meta: " . json_encode($pageMeta));

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ])
                ->timeout(15)
                ->post(self::API_URL, [
                    'model'      => self::MODEL,
                    'max_tokens' => 300,
                    'system'     => 'Sei un esperto di analisi aziendale italiana. Rispondi SOLO con JSON valido, senza markdown.',
                    'messages'   => [[
                        'role'    => 'user',
                        'content' => $this->buildPrompt($url, $pageMeta),
                    ]],
                ]);

            if (! $response->successful()) {
                Log::warning('[SECTOR-DETECTOR] API error: ' . $response->status());
                return $this->fallbackDetection($url);
            }

            $content = $response->json('content.0.text', '');
            $content = preg_replace('/^```(?:json)?\s*/m', '', $content);
            $content = preg_replace('/```\s*$/m', '', $content);
            $parsed  = json_decode(trim($content), true);

            if (! $parsed || empty($parsed['sector'])) {
                return $this->fallbackDetection($url);
            }

            Log::info("[SECTOR-DETECTOR] Detected: {$parsed['sector']} (confidence: {$parsed['confidence']})");

            return [
                'sector'              => $parsed['sector'],
                'sector_key'          => $parsed['sector_key'] ?? 'altro',
                'confidence'          => $parsed['confidence'] ?? 'medium',
                'description'         => $parsed['description'] ?? '',
                'is_regulated'        => $parsed['is_regulated'] ?? false,
                'deontological_notes' => $parsed['deontological_notes'] ?? null,
                'detected_from'       => $pageMeta,
                'error'               => null,
            ];

        } catch (\Throwable $e) {
            Log::warning("[SECTOR-DETECTOR] Exception: {$e->getMessage()}");
            return $this->fallbackDetection($url);
        }
    }

    private function buildPrompt(string $url, array $meta): string
    {
        $title = $meta['title'] ?? '';
        $desc  = $meta['description'] ?? '';
        $host  = parse_url($url, PHP_URL_HOST) ?? $url;

        $sectorKeys = implode(', ', array_keys(self::REGULATED_SECTORS));

        return <<<PROMPT
Analizza questo sito web italiano e determina il settore dell'azienda.

URL: {$url}
Dominio: {$host}
Title: {$title}
Meta description: {$desc}

Settori possibili (chiavi): {$sectorKeys}

Rispondi SOLO con questo JSON:
{
  "sector": "Psicologia e Psicoterapia",
  "sector_key": "psicologia",
  "confidence": "high",
  "description": "Studio professionale di psicologia e psicoterapia individuale",
  "is_regulated": true,
  "deontological_notes": "Ordine degli Psicologi - vincoli su testimonial e promesse terapeutiche"
}

Regole:
- sector: nome leggibile del settore specifico (es. "Ristorante Italiano", "Studio Legale", "Agenzia Web")
- sector_key: una delle chiavi fornite sopra, quella più vicina
- confidence: "high" se hai molti indizi, "medium" se pochi, "low" se incerto
- is_regulated: true se il settore ha vincoli deontologici/legali italiani
- deontological_notes: breve nota sui vincoli principali, null se non regolamentato
PROMPT;
    }

    private function fetchMeta(string $url): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; KalendariumBot/1.0)',
            ])->timeout(8)->get($url);

            if (! $response->successful()) {
                return ['title' => '', 'description' => ''];
            }

            $html = $response->body();
            $title = '';
            $description = '';

            if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m)) {
                $title = trim(html_entity_decode(strip_tags($m[1])));
            }

            if (preg_match('/name=["\']description["\']\s[^>]+content=["\']([^"\']+)["\'/si', $html, $m) ||
                preg_match('/content=["\']([^"\']+)["\']\s[^>]*name=["\']description["\']/si', $html, $m)) {
                $description = trim(html_entity_decode($m[1]));
            }

            return [
                'title'       => mb_substr($title, 0, 200),
                'description' => mb_substr($description, 0, 300),
            ];
        } catch (\Throwable) {
            return ['title' => '', 'description' => ''];
        }
    }

    /**
     * Mappa un testo di settore libero alla chiave canonica.
     * Usato per mappare il campo Brand::sector (testo libero) a sector_key.
     */
    public function mapToKey(string $sectorText): string
    {
        $lower = strtolower($sectorText);

        $mappings = [
            'psicolog'    => 'psicologia',
            'psicoterape' => 'psicologia',
            'counseling'  => 'psicologia',
            'medic'       => 'medico',
            'clinic'      => 'medico',
            'chirurg'     => 'medico',
            'farmaci'     => 'farmacia',
            'avvocat'     => 'legale',
            'legale'      => 'legale',
            'studio legale' => 'legale',
            'notai'       => 'legale',
            'finanz'      => 'finanza',
            'assicuraz'   => 'finanza',
            'banca'       => 'finanza',
            'ristoran'    => 'alimentare',
            'pizzeria'    => 'alimentare',
            'catering'    => 'alimentare',
            'food'        => 'alimentare',
            'palestra'    => 'fitness',
            'trainer'     => 'fitness',
            'yoga'        => 'fitness',
            'pilates'     => 'fitness',
            'estetica'    => 'estetica',
            'spa'         => 'estetica',
            'benessere'   => 'estetica',
            'immobil'     => 'immobiliare',
            'hotel'       => 'turismo',
            'albergo'     => 'turismo',
            'turismo'     => 'turismo',
            'hospitality' => 'turismo',
            'agriturismo' => 'turismo',
            'ecommerce'   => 'commercio',
            'e-commerce'  => 'commercio',
            'negozio'     => 'commercio',
            'vendita'     => 'commercio',
            'shop'        => 'commercio',
            'software'    => 'tech',
            'saas'        => 'tech',
            'tecnolog'    => 'tech',
            'agenzia web' => 'tech',
            'web agency'  => 'tech',
            'digital'     => 'tech',
            'consulenz'   => 'consulenza',
            'management'  => 'consulenza',
            'formazione'  => 'educazione',
            'istruzione'  => 'educazione',
            'scuola'      => 'educazione',
            'università'  => 'educazione',
            'nonprofit'   => 'nonprofit',
            'no profit'   => 'nonprofit',
            'associazione' => 'nonprofit',
        ];

        foreach ($mappings as $keyword => $key) {
            if (str_contains($lower, $keyword)) {
                return $key;
            }
        }

        return 'altro';
    }

    private function fallbackDetection(string $url): array
    {
        return [
            'sector'              => 'Altro',
            'sector_key'          => 'altro',
            'confidence'          => 'low',
            'description'         => 'Settore non determinato automaticamente',
            'is_regulated'        => false,
            'deontological_notes' => null,
            'detected_from'       => [],
            'error'               => 'Rilevamento automatico non disponibile',
        ];
    }
}
