<?php

declare(strict_types=1);

namespace App\Domain\Brand\Services;

use App\Domain\Brand\Enums\Sector;

/**
 * Vincoli deontologici per la generazione di post social media in settori
 * professionali regolamentati. Differenti dai constraints audit
 * (SectorDetectorService::DEONTOLOGICAL_CONSTRAINTS) che riguardano analisi
 * SEO di siti web. Questi sono pensati per il content social finale.
 *
 * Riferimenti normativi documentati nel campo `legal_basis` di ogni settore
 * (utile per audit log + giustificazione client).
 */
final class SocialDeontologicalConstraints
{
    /**
     * @return array{
     *   forbidden_phrases: list<string>,
     *   forbidden_themes: list<string>,
     *   required_disclaimers: list<string>,
     *   cta_guidelines: string,
     *   tone_guidance: string,
     *   preferred_themes: list<string>,
     *   legal_basis: string,
     * }|null
     */
    public function getFor(Sector $sector): ?array
    {
        return match ($sector) {
            Sector::Psicologia          => $this->psicologia(),
            Sector::Salute              => $this->salute(),
            Sector::Legale              => $this->legale(),
            Sector::FinanzaIndipendente => $this->finanzaIndipendente(),
            Sector::Finanza             => $this->finanza(),
            default                     => null,
        };
    }

    private function psicologia(): array
    {
        return [
            'forbidden_phrases' => [
                'guarigione garantita', 'risolvi subito', 'elimina l\'ansia',
                'ritrova la serenità', 'libero dalla depressione', 'cura definitiva',
                'sconto', 'prima seduta gratis', 'promozione', 'offerta limitata',
                'non perdere questa opportunità', 'non rimandare',
                'la signora Maria', 'il mio paziente', 'un caso clinico',
                'hai questi sintomi? sei depresso', 'hai questi 5 segnali',
                'risolvere la depressione in 3 mesi', 'metodo infallibile',
            ],
            'forbidden_themes' => [
                'Promesse di risultato o guarigione',
                'Riferimenti a casi clinici reali (anche se anonimi)',
                'Testimonianze di pazienti',
                'Sconti, offerte promozionali, "prima seduta gratis"',
                'Urgency aggressiva ("non rimandare", "agisci subito")',
                'Comparazioni con altri psicologi o approcci',
                'Diagnosi self-help ("se hai X sintomi, sei Y")',
                'Linguaggio sensazionalistico o emotivo aggressivo',
            ],
            'required_disclaimers' => [],
            'cta_guidelines' =>
                'USA CTA neutre e rispettose: "Per un primo colloquio conoscitivo, contattami" / '
              . '"Per maggiori informazioni sui servizi offerti" / "Lascia un messaggio". '
              . 'MAI urgency: niente "subito", "non perdere", "ora o mai più".',
            'tone_guidance' =>
                'Professionale, dignitoso, scientifico. Empatico ma non emotivo. '
              . 'Mai promesse di risultato. Riconosci la complessità della sofferenza '
              . 'psicologica senza banalizzarla né drammatizzarla. Trasmetti serietà '
              . 'e setting professionale.',
            'preferred_themes' => [
                'Contenuti educational generici su tematiche psicologiche (con fonti scientifiche)',
                'Sensibilizzazione su disturbi senza incoraggiare autodiagnosi',
                'Presentazione di credenziali, specializzazioni, approccio terapeutico',
                'Informazioni sui servizi offerti (modalità online/presenza, orari, setting)',
                'Riflessioni su temi di salute mentale collettivi (giornata mondiale della salute mentale, ecc.)',
                'Spiegazioni di concetti tecnici (cos\'è la CBT, EMDR, terapia di coppia) in modo accessibile',
            ],
            'legal_basis' =>
                'Codice Deontologico Ordine Psicologi: artt. 4 (riservatezza), 11 (segreto '
              . 'professionale), 12 (astensione testimonianza), 17 (custodia dati), 40 '
              . '(pubblicità). L. 145/2018 art. 1 c. 525: vietato ogni "elemento attrattivo '
              . 'e suggestivo" (sconti, promozioni, offerte). GDPR per dati pazienti.',
        ];
    }

    private function salute(): array
    {
        return [
            'forbidden_phrases' => [
                'guarigione garantita', 'risolve definitivamente', 'cura miracolosa',
                'risultati al 100%', 'sconto', 'offerta', 'risparmio sulle prestazioni',
                'la signora Maria', 'il mio paziente', 'caso clinico reale',
                'prima e dopo', 'before/after', 'testimonianza di un paziente',
            ],
            'forbidden_themes' => [
                'Promesse di guarigione o risultati specifici',
                'Testimonianze di pazienti con nome reale o foto identificabili',
                'Foto "prima e dopo" senza esplicito contesto educational',
                'Sconti o promozioni su prestazioni sanitarie',
                'Comparazioni con altri medici o strutture',
                'Affermazioni sanitarie non supportate da letteratura scientifica',
            ],
            'required_disclaimers' => [
                'Per consigli personalizzati consulta sempre il tuo medico curante (su post educational generali)',
            ],
            'cta_guidelines' =>
                'CTA professionali: "Prenota una visita", "Per informazioni: [contatto]". '
              . 'Mai linguaggio promozionale o di urgenza.',
            'tone_guidance' =>
                'Sobrio, scientifico, basato su evidenze. Empatico ma sempre clinico. '
              . 'Eviti claim sanitari non supportati. Distingui chiaramente fra info '
              . 'generali e consigli medici personalizzati.',
            'preferred_themes' => [
                'Educational su patologie con riferimenti a linee guida ufficiali (SIMG, Ministero Salute, ISS, OMS)',
                'Presentazione di specializzazioni, strutture convenzionate, accreditamenti',
                'Modalità prenotazione e logistica',
                'Pubblicazioni scientifiche del medico (se presenti)',
                'Giornate di sensibilizzazione su patologie',
            ],
            'legal_basis' =>
                'Codice Deontologico Medico (FNOMCeO). Decreto Bersani L. 248/2006 art. 4 + '
              . 'L. 145/2018 art. 1 cc. 525-536 per pubblicità sanitaria: informativa, '
              . 'mai promozionale o suggestiva. Vietate offerte e sconti su prestazioni sanitarie.',
        ];
    }

    private function legale(): array
    {
        return [
            'forbidden_phrases' => [
                'vincita garantita', 'cause vinte al 100%', 'risolveremo il tuo problema',
                'sconto sulla consulenza', 'offerta speciale', 'a partire da €',
                'il mio cliente', 'caso di successo con nome',
                'più bravo della concorrenza', 'lo studio migliore',
            ],
            'forbidden_themes' => [
                'Promesse di vittoria o risultati garantiti (vietato dal codice forense)',
                'Confronti con altri studi legali o avvocati',
                'Prezzi specifici o "a partire da" per prestazioni legali',
                'Testimonial di clienti con nomi reali',
                'Riferimenti a controversie in corso che identifichino le parti',
            ],
            'required_disclaimers' => [],
            'cta_guidelines' =>
                'CTA: "Richiedi una consulenza", "Per informazioni: [contatto]". '
              . 'Mai "Vinci la tua causa" o claim di risultato.',
            'tone_guidance' =>
                'Autorevole, tecnico, sobrio. Trasmetti competenza senza spettacolarizzare. '
              . 'Distingui chiaramente info generale da consulenza personalizzata.',
            'preferred_themes' => [
                'Educational su tematiche giuridiche (con riferimenti normativi)',
                'Aggiornamenti normativi e giurisprudenziali',
                'Specializzazioni dello studio',
                'Case history anonimizzate ("abbiamo seguito oltre N controversie del tipo X")',
                'Iscrizione Albo, anni di esperienza, pubblicazioni',
            ],
            'legal_basis' =>
                'Codice Deontologico Forense (CNF) artt. 17 e 35 (informazione professionale). '
              . 'Decreto Bersani L. 248/2006: pubblicità informativa OK, suggestiva NO. '
              . 'Vietate promesse di risultato e confronti.',
        ];
    }

    private function finanzaIndipendente(): array
    {
        return [
            'forbidden_phrases' => [
                'rendimento garantito', 'guadagno sicuro', 'investimento sicuro',
                'profitto certo', 'guadagna il', 'guadagna €', '+10%', '+20%',
                'doppia il tuo capitale', 'rendimento del', 'rendite mensili',
                'bonus iniziale', 'sconto sulla consulenza', 'prima consulenza gratis',
                'non perdere questa opportunità', 'occasione irripetibile',
                'comprare oggi', 'vendere subito', 'compra azioni', 'vendi titoli',
                'il mio cliente ha guadagnato', 'guadagni di Mario',
            ],
            'forbidden_themes' => [
                'Promesse di rendimento o garanzie di guadagno',
                'Affermazioni di "investimento sicuro" o "senza rischio"',
                'Raccomandazioni operative su strumenti specifici (titoli, fondi, crypto) al pubblico',
                'Comparazioni di rendimento con concorrenti',
                'Bonus, sconti, offerte sulla consulenza',
                'Urgency aggressiva ("solo per oggi", "occasione irripetibile")',
                'Testimonianze di clienti con cifre di guadagno',
                'Pump di asset specifici',
                'Linguaggio "miracoloso" o sensazionalistico',
            ],
            'required_disclaimers' => [
                'Includi disclaimer "I rendimenti passati non garantiscono rendimenti futuri" '
              . 'quando il post tratta di rendimenti, performance storiche, o strategie di investimento',
                'Includi "Contenuto a scopo educativo, non costituisce consulenza personalizzata" '
              . 'quando il post tratta di strumenti finanziari specifici o strategie operative',
            ],
            'cta_guidelines' =>
                'CTA professionali e informative. Esempi OK: "Per una pianificazione '
              . 'finanziaria personalizzata, contattami" / "Scopri il mio approccio: '
              . '[link]" / "Iscriviti alla mia newsletter di educazione finanziaria". '
              . 'MAI: "guadagna", "non perdere", "ora o mai più", "compra subito".',
            'tone_guidance' =>
                'Sobrio, tecnico ma accessibile. Educational. Trasparente sui costi del '
              . 'servizio. Mai aggressivo o sensazionalistico. Mostra metodologia, non '
              . 'risultati promessi. Distingui chiaramente educazione finanziaria da '
              . 'consulenza personalizzata.',
            'preferred_themes' => [
                'Educazione finanziaria di base (cos\'è un ETF, fondo comune, obbligazione)',
                'Spiegazione di concetti macroeconomici (inflazione, tassi, recessione)',
                'Metodologia di pianificazione finanziaria (asset allocation, orizzonte temporale)',
                'Differenza tra consulenza indipendente e collegata',
                'Spiegazione MiFID II, profilatura cliente, regole di adeguatezza',
                'Avvertimenti su rischi finanziari (volatilità, leva, illiquidità)',
                'Iscrizione Albo OCF, certificazioni (CFP, CFA, EFP)',
                'Trasparenza sui costi del servizio di consulenza (fee-only)',
            ],
            'legal_basis' =>
                'Reg. Consob 20307/2018 art. 36: comunicazioni "corrette, chiare e non '
              . 'fuorvianti". TUF art. 18-bis/ter (Albo OCF). MiFID II dir. 2014/65/UE '
              . '(investor protection). Comunicazione Consob DCL/DEM/3033709 del '
              . '22.05.2003 contro enfasi sui rendimenti. Vietate offerte al pubblico '
              . 'di prodotti finanziari senza prospetto.',
        ];
    }

    private function finanza(): array
    {
        return [
            'forbidden_phrases' => [
                'rendimento garantito', 'guadagno sicuro', 'investimento sicuro',
                'profitto certo', 'doppia il tuo capitale',
                'bonus iniziale', 'sconto sulla consulenza',
                'comprare oggi', 'vendere subito',
            ],
            'forbidden_themes' => [
                'Promesse di rendimento o garanzie di guadagno',
                'Affermazioni di "investimento sicuro" o "senza rischio" su prodotti rischiosi',
                'Comparazioni di performance ingannevoli',
                'Urgency aggressiva',
            ],
            'required_disclaimers' => [
                'Includi disclaimer "I rendimenti passati non garantiscono rendimenti futuri" '
              . 'su qualunque riferimento a performance',
                'Per prodotti complessi, ricorda l\'esistenza del KID/PRIIPs e profilatura cliente',
            ],
            'cta_guidelines' =>
                'CTA: "Scopri di più sui prodotti", "Per una consulenza: [contatto]". '
              . 'Mai promesse di guadagno.',
            'tone_guidance' =>
                'Istituzionale, sobrio, trasparente. Enfasi su tutela del cliente e adeguatezza prodotti.',
            'preferred_themes' => [
                'Educazione finanziaria generale',
                'Spiegazione di prodotti e servizi della banca/assicurazione',
                'Comunicazione su regole di tutela cliente (MiFID, IDD, KID)',
            ],
            'legal_basis' =>
                'Reg. Consob 20307/2018 art. 36. MiFID II (intermediari). IDD per assicurazioni. '
              . 'Banca d\'Italia per banche.',
        ];
    }
}
