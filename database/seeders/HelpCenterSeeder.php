<?php

namespace Database\Seeders;

use App\Domain\Help\Models\HelpArticle;
use App\Domain\Help\Models\HelpCategory;
use Illuminate\Database\Seeder;

class HelpCenterSeeder extends Seeder
{
    public function run(): void
    {
        $categories = $this->getCategories();

        foreach ($categories as $catData) {
            $articles = $catData['articles'];
            unset($catData['articles']);

            $category = HelpCategory::firstOrCreate(
                ['slug' => $catData['slug']],
                $catData
            );

            foreach ($articles as $articleData) {
                $articleData['help_category_id'] = $category->id;
                $slug = $articleData['slug'];
                unset($articleData['slug']);
                HelpArticle::updateOrCreate(
                    ['slug' => $slug],
                    array_merge(['slug' => $slug], $articleData)
                );
            }
        }
    }

    private function getCategories(): array
    {
        return [
            $this->primiPassi(),
            $this->calendario(),
            $this->pubblicazione(),
            $this->immagini(),
            $this->insights(),
            $this->account(),
        ];
    }

    private function primiPassi(): array
    {
        return [
            'slug' => 'primi-passi',
            'title' => 'Primi passi',
            'description' => 'Guida rapida per iniziare a usare Noscite Calendar',
            'icon' => 'Rocket',
            'sort_order' => 1,
            'articles' => [
                [
                    'slug' => 'creare-primo-brand',
                    'title' => 'Come creare il tuo primo Brand',
                    'is_featured' => true,
                    'sort_order' => 1,
                    'excerpt' => 'Tutto parte dal Brand. In pochi minuti configuri il profilo della tua azienda e sei pronto a generare il tuo primo calendario.',
                    'content' => <<<'MD'
# Come creare il tuo primo Brand

Il Brand è il profilo della tua azienda su Noscite Calendar. Ogni calendario editoriale che generi è collegato a un Brand, quindi è la prima cosa da creare.

## Come si fa

1. Vai su **I miei Brand** nella barra laterale sinistra
2. Clicca il pulsante **+ Nuovo Brand** in alto a destra
3. Compila i campi del profilo

## I campi del profilo

**Nome azienda** *(obbligatorio)*
Il nome che verrà usato dall'AI per personalizzare i contenuti.

**Settore** *(obbligatorio)*
Seleziona il settore più vicino alla tua attività. Questo aiuta l'AI a usare il linguaggio giusto per il tuo mercato.

**Descrizione**
Scrivi 2-3 frasi su cosa fa la tua azienda, a chi si rivolge e cosa la distingue dalla concorrenza. Più sei preciso, migliori saranno i contenuti.

**Tono di voce**
Come parla il tuo brand? Formale, amichevole, tecnico, ironico? Puoi anche scrivere esempi ("usiamo il tu", "evitiamo tecnicismi").

**Target di riferimento**
Descrivi brevemente il tuo cliente ideale: età, professione, interessi, problemi che vuole risolvere.

**Sito web**
Se hai un sito, inseriscilo: l'AI lo analizza automaticamente per capire meglio il tuo brand prima di generare i contenuti.

## Dopo aver creato il Brand

Sarai reindirizzato alla pagina del Brand dove puoi:
- Collegare i tuoi profili social
- Caricare documenti aziendali
- Creare il tuo primo Piano Editoriale

> 💡 **Consiglio:** dedica qualche minuto in più alla descrizione e al tono di voce. L'AI genera contenuti molto migliori quando capisce davvero come comunica il tuo brand.
MD,
                ],
                [
                    'slug' => 'creare-piano-editoriale',
                    'title' => 'Creare un Piano Editoriale',
                    'is_featured' => true,
                    'sort_order' => 2,
                    'excerpt' => 'Il Piano Editoriale è il contenitore del tuo calendario. Segui il wizard in pochi passi e l\'AI penserà al resto.',
                    'content' => <<<'MD'
# Creare un Piano Editoriale

Il Piano Editoriale definisce il periodo, le piattaforme e gli obiettivi del tuo calendario. Una volta configurato, l'AI genera automaticamente tutti i post.

## Come si fa

Dalla pagina del tuo Brand, clicca **+ Nuovo Piano Editoriale**. Si apre un wizard in 7 passi.

## I 7 passi del wizard

**Passo 1 — Date e piattaforme**
Scegli il periodo (es. 3 mesi) e le piattaforme: Instagram, Facebook, LinkedIn, Google Business.

**Passo 2 — Frequenza**
Quanti post a settimana per piattaforma? Puoi lasciare decidere all'AI oppure impostare tu i numeri.

**Passo 3 — Temi e obiettivi**
Indica i temi principali (es. "prodotti", "team", "consigli pratici") e l'obiettivo del periodo (brand awareness, lead generation, engagement).

**Passo 4 — Brief creativo**
Campagne speciali, eventi o promozioni in programma? Scrivile qui.

**Passo 5 — Documenti**
Carica documenti aziendali opzionali (cataloghi, comunicati stampa). L'AI li usa per arricchire i contenuti.

**Passo 6 — Buyer Personas**
Clicca **Genera Personas** e l'AI crea i profili del tuo target. Puoi modificarli o aggiungerli manualmente.

**Passo 7 — Preview del Calendario**
Prima di generare, vedi un riepilogo completo: post per piattaforma, distribuzione dei contenuti, personas attive, eventuali avvisi e una stima del tempo di generazione. Clicca **Genera Calendario** per confermare o **Modifica parametri** per tornare indietro.

## Avviare la generazione

Al termine del Passo 7 clicca **Genera Calendario**. L'AI impiega 1-3 minuti. Puoi chiudere la pagina: riceverai una notifica quando è pronto.

> 💡 **Consiglio:** più informazioni dai nel brief e nei documenti, più i contenuti saranno pertinenti e pronti senza modifiche.
MD,
                ],
                [
                    'slug' => 'collegare-profili-social',
                    'title' => 'Collegare i profili Social',
                    'is_featured' => true,
                    'sort_order' => 3,
                    'excerpt' => 'Collega Instagram, Facebook, LinkedIn e Google Business per pubblicare direttamente dalla piattaforma, senza copiare e incollare.',
                    'content' => <<<'MD'
# Collegare i profili Social

Per pubblicare automaticamente devi collegare i tuoi account social al Brand.

## Come si fa

1. Vai sulla pagina del Brand
2. Clicca **Connessioni Social**
3. Clicca **Connetti** accanto alla piattaforma
4. Segui il flusso di autorizzazione

## Piattaforma per piattaforma

### Instagram (profilo Business)
Richiede un profilo Business collegato a una Pagina Facebook.
- Clicca **Connetti Instagram** → accedi con Facebook
- Seleziona la Pagina Facebook collegata al tuo Business
- Conferma i permessi richiesti

### Facebook (Pagina aziendale)
- Clicca **Connetti Facebook** → accedi con il tuo account
- Seleziona la Pagina tra quelle che amministri
- Conferma i permessi

### LinkedIn (Pagina aziendale)
- Clicca **Connetti LinkedIn** → accedi
- Seleziona la Pagina di cui sei amministratore

### Google Business
- Clicca **Connetti Google Business** → accedi con Google
- Seleziona la scheda Google My Business

## La connessione non funziona?

- Assicurati di essere **amministratore** della pagina
- I token scadono periodicamente: disconnetti e riconnetti
- Per Instagram verifica che il profilo sia impostato come **Business**

> ⚠️ Non collegare profili personali. Le API social richiedono pagine aziendali per la pubblicazione automatica.
MD,
                ],
                [
                    'slug' => 'intervista-ai-brand',
                    'title' => 'Intervista AI per la profilazione del Brand',
                    'is_featured' => true,
                    'sort_order' => 3,
                    'excerpt' => 'L\'AI fa domande sul tuo brand e costruisce automaticamente il profilo. Scegli tra intervista vocale o chat testuale.',
                    'content' => <<<'MD'
# Intervista AI per la profilazione del Brand

L'Intervista AI è il modo più veloce per compilare il profilo del tuo brand. Invece di riempire campi manualmente, rispondi a domande e l'AI estrae automaticamente nome, settore, tono di voce, target e valori.

## Come avviare l'intervista

**Dal Dashboard:**
Clicca il pulsante **AI Assistant** nella barra in alto. Se hai un solo brand, l'intervista si apre direttamente. Se ne hai più d'uno, scegli il brand dal selettore che appare.

**Dalla pagina del Brand:**
Cerca il link "Avvia intervista AI" o "Compila con AI".

## Scegliere la modalità

Quando si apre la schermata di selezione, hai due opzioni:

**Modalità Voce** — parla direttamente con l'AI tramite microfono. L'AI registra e trascrive le tue risposte in tempo reale. Richiede permesso al microfono. Se la connessione vocale non è disponibile, passa automaticamente alla chat.

**Modalità Chat** — rispondi alle domande per iscritto. Stessa qualità del risultato, nessun requisito tecnico.

## Come funziona la chat

L'AI ti fa una serie di domande sul tuo brand, una alla volta:
- Nome dell'azienda
- Settore di attività
- Sito web
- Tono di voce
- Target di riferimento
- Valori del brand

Una barra di avanzamento mostra quante domande restano.

## Confermare e salvare

Al termine dell'intervista appare la schermata **Conferma dati**. Qui puoi:
- Verificare tutti i campi estratti dall'AI
- Modificare qualsiasi informazione prima di salvare
- Aggiungere o rimuovere i valori del brand (appaiono come tag)

Clicca **Salva Profilo** per salvare. Verrai reindirizzato alla creazione del primo Piano Editoriale.

> 💡 **Consiglio:** se qualcosa non è stato captato correttamente nella modalità voce, correggilo nella schermata di conferma prima di salvare.
MD,
                ],
                [
                    'slug' => 'buyer-personas',
                    'title' => 'Capire le Buyer Personas AI',
                    'sort_order' => 4,
                    'excerpt' => 'Le Buyer Personas sono i profili del tuo cliente ideale. L\'AI le genera automaticamente e le usa per scrivere contenuti più mirati.',
                    'content' => <<<'MD'
# Capire le Buyer Personas AI

Le Buyer Personas descrivono il tuo cliente tipo. L'AI le usa per scrivere contenuti che parlano davvero al tuo pubblico.

## Come vengono generate

Nel wizard, clicca **Genera Personas**. L'AI analizza descrizione, settore, target, sito web e documenti del Brand.

In pochi secondi crea 2-3 personas con nome, età, professione, comportamento digitale e orari migliori per raggiungerle.

## Cosa trovi in ogni Persona

- **Profilo demografico**: età, ruolo, area geografica
- **Comportamento digitale**: quali piattaforme usa e quando
- **Pain points**: i problemi che il tuo brand può risolvere
- **Interessi**: gli argomenti che catturano la sua attenzione
- **Orari migliori**: quando è attivo sui social

## Puoi modificarle

- Modifica qualsiasi campo cliccando sulla persona
- Elimina una persona che non ti convince
- Aggiungi una persona manualmente con **+ Aggiungi Persona**
- Rigenera tutto con **Rigenera Personas**

## A cosa servono concretamente

L'AI usa le personas per decidere tono, argomenti, orari di pubblicazione e tipo di contenuto per ogni piattaforma.
MD,
                ],
            ],
        ];
    }

    private function calendario(): array
    {
        return [
            'slug' => 'calendario',
            'title' => 'Calendario e Post',
            'description' => 'Gestire il calendario, modificare i post e pianificare la pubblicazione',
            'icon' => 'Calendar',
            'sort_order' => 2,
            'articles' => [
                [
                    'slug' => 'navigare-calendario',
                    'title' => 'Leggere e navigare il calendario',
                    'sort_order' => 1,
                    'excerpt' => 'Tutti i tuoi post in una vista mensile. Filtra per piattaforma, cambia mese e gestisci ogni contenuto con un click.',
                    'content' => <<<'MD'
# Leggere e navigare il calendario

Il calendario mostra tutti i post in una griglia mensile.

## I colori delle piattaforme

- 🔵 **Blu scuro** — LinkedIn
- 🟣 **Viola/Rosa** — Instagram
- 🔵 **Blu chiaro** — Facebook
- 🟢 **Verde** — Google Business

## Navigare tra i mesi

Usa le frecce **‹ ›** in alto per spostarti nel tempo.

## Filtrare i post

Clicca sull'icona della piattaforma per mostrare/nascondere i post di quella piattaforma.

## Aprire un post

Clicca su qualsiasi post per aprire il pannello di dettaglio con testo completo, immagine, stato e azioni disponibili.

## Gli stati del post

- **Bozza** — generato, non ancora pianificato
- **Pianificato** — ha data e ora di pubblicazione
- **In pubblicazione** — il sistema sta pubblicando
- **Pubblicato** — live sui social
- **Errore** — qualcosa non ha funzionato (clicca per i dettagli)
MD,
                ],
                [
                    'slug' => 'modificare-post',
                    'title' => 'Modificare e personalizzare i post',
                    'sort_order' => 2,
                    'excerpt' => 'Ogni post generato dall\'AI è un punto di partenza. Puoi modificare testo, hashtag, immagine e molto altro.',
                    'content' => <<<'MD'
# Modificare e personalizzare i post

## Aprire il pannello di modifica

Clicca su un post nel calendario. Si apre un pannello laterale.

## Cosa puoi modificare

**Testo del post** — clicca e modifica direttamente.

**Hashtag** — aggiungi, rimuovi o modifica.

**Immagine**
- **Genera immagine** — crea un'immagine AI con DALL-E
- **Carica immagine** — usa una tua foto o grafica
- **Modifica prompt** — cambia la descrizione prima di generare

**Data e ora** — cambia quando vuoi pubblicare.

## Rigenerare un post

Clicca **Rigenera**. Puoi scrivere istruzioni ("rendilo più breve", "aggiungi un tono più scherzoso") oppure lasciare fare all'AI.

## Le modifiche si salvano automaticamente

Non c'è un pulsante Salva esplicito.
MD,
                ],
                [
                    'slug' => 'pianificare-pubblicazione',
                    'title' => 'Pianificare e pubblicare i post',
                    'sort_order' => 3,
                    'excerpt' => 'Imposta data e ora di pubblicazione e lascia che Noscite pubblichi automaticamente sui tuoi social.',
                    'content' => <<<'MD'
# Pianificare e pubblicare i post

## Come pianificare un post

1. Apri il post dal calendario
2. Clicca **Pianifica pubblicazione**
3. Scegli data, ora e piattaforme
4. Clicca **Conferma**

Il post passa allo stato **Pianificato**.

## La pubblicazione automatica

Noscite controlla ogni minuto i post da pubblicare. All'orario impostato il post viene pubblicato automaticamente. Riceverai una notifica e un'email di conferma.

## Pubblicare subito

Clicca **Pubblica ora** per pubblicare immediatamente.

## Annullare una pianificazione

Apri il post pianificato e clicca **Annulla pianificazione**. Il post torna allo stato Bozza.

> ⚠️ Per pubblicare automaticamente devi avere i profili social collegati.
MD,
                ],
                [
                    'slug' => 'preview-calendario',
                    'title' => 'Leggere il Preview del Calendario',
                    'sort_order' => 5,
                    'excerpt' => 'Prima di generare il calendario vedi esattamente quanti post verranno creati, come sono distribuiti e se mancano informazioni importanti.',
                    'content' => <<<'MD'
# Leggere il Preview del Calendario

Il **Passo 7** del wizard mostra un'anteprima completa prima che la generazione parta. Puoi così verificare le impostazioni e correggere eventuali problemi senza sprecare crediti AI.

## Cosa trovi nel preview

### Riepilogo piattaforme
Per ogni piattaforma selezionata vedi:
- Numero totale di post che verranno generati
- Post per settimana
- Tipo di contenuti prevalenti (informativo, promozionale, engagement)
- Orari ottimali suggeriti (calcolati dalle Buyer Personas)

### Distribuzione dei contenuti
Una barra mostra come i post si dividono tra i temi che hai impostato nel piano.

### Temi e brief
I temi principali e il brief creativo che hai inserito vengono mostrati in sintesi, così puoi verificare che tutto sia corretto.

### Buyer Personas attive
Le personas associate al piano. Se non ne hai ancora create, compare un avviso con il link diretto al Passo 6 per aggiungerle.

### Avvisi
Se ci sono configurazioni incomplete o potenzialmente problematiche, appaiono in un riquadro arancione. Esempi comuni:
- Nessuna persona configurata
- Nessun documento caricato (la generazione funziona ma con meno contesto)
- Frequenza molto alta (rischio qualità)

### Stima
Un riquadro verde mostra il numero stimato di post totali e il tempo di generazione previsto (in minuti).

## Cosa puoi fare dal preview

- **Genera Calendario** — avvia la generazione con le impostazioni attuali
- **Modifica parametri** — torna indietro al wizard per cambiare qualcosa

> 💡 **Consiglio:** se compare l'avviso "Nessuna Buyer Persona", crea almeno una persona prima di generare. I contenuti saranno significativamente più mirati.
MD,
                ],
                [
                    'slug' => 'edizioni-piano-editoriale',
                    'title' => 'Lavorare con le Edizioni del Piano Editoriale',
                    'is_featured' => true,
                    'sort_order' => 4,
                    'excerpt' => 'Aggiungi mesi al tuo piano senza ricominciare da zero. Le Edizioni ti permettono di continuare il calendario mantenendo tutta la storia del brand.',
                    'content' => <<<'MD'
# Lavorare con le Edizioni del Piano Editoriale

Quando un Piano Editoriale si avvicina alla scadenza, non devi crearne uno nuovo da zero. Aggiungi una nuova **Edizione** e continua esattamente da dove hai lasciato.

## Cosa sono le Edizioni

Un Piano Editoriale può avere più Edizioni consecutive:

- **Edizione 1** — Gennaio / Marzo
- **Edizione 2** — Aprile / Giugno
- **Edizione 3** — Luglio / Settembre

Ogni Edizione ha i propri post ma condivide le impostazioni del piano e la memoria storica di tutti i contenuti pubblicati in precedenza.

## Come aggiungere una nuova Edizione

### Dalla pagina del Brand
1. Trova il Piano Editoriale
2. Clicca **+ Edizione**
3. Le date vengono pre-calcolate automaticamente
4. Modifica nome, date se necessario
5. Clicca **Crea Edizione**

### Dalla pagina del Piano
Clicca **+ Nuova Edizione** nella barra in alto a destra.

## Le date vengono calcolate in automatico

- **Data inizio**: giorno dopo la fine dell'ultima Edizione
- **Data fine**: calcolata in base alla durata media delle Edizioni precedenti (default: 3 mesi)

Puoi sempre modificarle prima di confermare.

## La generazione contestuale

Quando generi i contenuti di una nuova Edizione, l'AI conosce tutto quello che ha scritto nelle Edizioni precedenti.

Questo significa che i nuovi post:
- **Non ripetono** argomenti già trattati
- **Approfondiscono** con nuovi angoli i temi che hanno funzionato
- **Mantengono** tono e stile coerenti con tutto il piano

## Consigli pratici

- Crea la nuova Edizione **con un mese di anticipo** sulla scadenza
- **Non eliminare le Edizioni vecchie**: l'AI le usa come riferimento
- Se cambia la strategia, aggiorna il brief nella nuova Edizione
MD,
                ],
            ],
        ];
    }

    private function pubblicazione(): array
    {
        return [
            'slug' => 'pubblicazione',
            'title' => 'Pubblicazione automatica',
            'description' => 'Come funziona la pubblicazione automatica e come risolvere eventuali errori',
            'icon' => 'Send',
            'sort_order' => 3,
            'articles' => [
                [
                    'slug' => 'pubblicazione-automatica',
                    'title' => 'Come funziona la pubblicazione automatica',
                    'sort_order' => 1,
                    'excerpt' => 'Imposti l\'orario, Noscite pubblica. Ecco esattamente cosa succede dall\'impostazione alla pubblicazione live.',
                    'content' => <<<'MD'
# Come funziona la pubblicazione automatica

## Il processo passo per passo

1. **Pianifichi il post** con data, ora e piattaforme
2. All'orario impostato il sistema prepara il contenuto
3. Il post viene inviato alla piattaforma social
4. Ricevi **notifica** ed **email** di conferma
5. Il post appare sul calendario come **Pubblicato** con link diretto al post live

## Gli stati durante la pubblicazione

- **Pianificato** → in coda, aspetta l'orario
- **In pubblicazione** → il sistema sta lavorando (pochi secondi)
- **Pubblicato** → live sui social
- **Errore** → qualcosa non ha funzionato

## Cosa serve perché funzioni

- Il profilo social deve essere **collegato e attivo**
- Instagram richiede un **profilo Business**
- Le immagini devono essere nel **formato corretto**
- Il tuo piano deve includere la pubblicazione automatica

## Posso pubblicare su più piattaforme insieme?

Sì. Seleziona più piattaforme quando pianifichi il post.

## E se non ho internet all'orario della pubblicazione?

Non importa. La pubblicazione avviene sui server di Noscite, non sul tuo dispositivo.
MD,
                ],
                [
                    'slug' => 'errori-pubblicazione',
                    'title' => 'Risolvere errori di pubblicazione',
                    'sort_order' => 2,
                    'excerpt' => 'Il post non è andato? Ecco i 5 errori più comuni e come risolverli in pochi minuti.',
                    'content' => <<<'MD'
# Risolvere errori di pubblicazione

Se un post non viene pubblicato appare sul calendario con un'icona rossa. Clicca sul post per leggere il messaggio di errore specifico.

## I 5 errori più comuni

### 1. Token scaduto / Connessione non valida
**Causa:** I token di autorizzazione scadono ogni 60-90 giorni.

**Soluzione:**
1. Vai su **Social** nella barra laterale
2. Trova la piattaforma con l'avviso
3. Clicca **Riconnetti** e segui il flusso
4. Torna al post con errore → clicca **Riprova pubblicazione**

### 2. Permessi insufficienti
**Causa:** Non sono stati autorizzati tutti i permessi necessari.

**Soluzione:**
1. Disconnetti l'account da **Social**
2. Ricollegalo accettando **tutti i permessi** richiesti
3. Non saltare nessuna schermata di conferma

### 3. Immagine non valida
**Causa:** Formato o dimensioni non supportati dalla piattaforma.

**Soluzione:**
- Carica un'immagine diversa con **Carica immagine**
- Oppure usa **Genera immagine** per crearne una nel formato corretto
- Formati consigliati: JPG o PNG, 1:1 o 4:5 per Instagram, 16:9 per LinkedIn e Facebook

### 4. Testo troppo lungo
**Causa:** Il testo supera il limite della piattaforma.

**Soluzione:** Apri il post e accorcia il testo. Limiti: Instagram 2.200 caratteri, LinkedIn 3.000, Facebook 63.206, Google Business 1.500.

### 5. Account non Business (Instagram)
**Causa:** Instagram permette la pubblicazione automatica solo ai profili Business o Creator.

**Soluzione:**
1. Apri Instagram sul telefono
2. **Impostazioni → Account → Passa a profilo professionale**
3. Scegli **Business** e collega una Pagina Facebook
4. Riconnetti Instagram su Noscite

## Il post non riprende automaticamente dopo l'errore

Dopo aver risolto il problema clicca **Riprova pubblicazione**. Il sistema non ritenta da solo per evitare pubblicazioni duplicate.

## Hai ancora problemi?

Usa la chat di supporto in basso a destra oppure scrivi a **supporto@noscite.it** con l'ID del post (visibile nel pannello).
MD,
                ],
            ],
        ];
    }

    private function immagini(): array
    {
        return [
            'slug' => 'immagini',
            'title' => 'Immagini AI',
            'description' => 'Generare immagini con l\'intelligenza artificiale per i tuoi post',
            'icon' => 'Image',
            'sort_order' => 4,
            'articles' => [
                [
                    'slug' => 'generare-immagini',
                    'title' => 'Generare immagini con l\'AI',
                    'is_featured' => true,
                    'sort_order' => 1,
                    'excerpt' => 'Ogni post può avere un\'immagine creata dall\'AI in pochi secondi. Singola, carosello, verticale o quadrata: scegli tu il formato.',
                    'content' => <<<'MD'
# Generare immagini con l'AI

## Come generare un'immagine

1. Apri un post dal calendario
2. Clicca **Genera immagine**
3. Scegli il formato (quadrato 1:1, verticale 4:5, orizzontale 16:9)
4. L'AI usa il testo del post per creare un'immagine coerente
5. L'immagine appare in 10-20 secondi

## Modificare il prompt

Se l'immagine non ti soddisfa:
1. Clicca **Modifica descrizione immagine**
2. Scrivi una descrizione più specifica (es. "ufficio moderno con luce naturale, stile fotografico, nessun testo")
3. Clicca **Rigenera**

## Il carosello

Per Instagram puoi creare post con più immagini:
1. Clicca **Genera carosello**
2. Scegli il numero di slide (2-5)
3. L'AI crea immagini collegate tematicamente
4. Puoi riordinare le slide o rigenerarne singole

## Formati consigliati per piattaforma

| Piattaforma | Formato |
|---|---|
| Instagram Feed | Quadrato (1:1) o Verticale (4:5) |
| Instagram Stories | Verticale (9:16) |
| Facebook | Orizzontale (16:9) o Quadrato |
| LinkedIn | Orizzontale (1.91:1) |
| Google Business | Orizzontale (16:9) |

> 💡 Genera sempre l'immagine prima di pianificare la pubblicazione.
MD,
                ],
            ],
        ];
    }

    private function insights(): array
    {
        return [
            'slug' => 'insights',
            'title' => 'Insights e Statistiche',
            'description' => 'Monitorare le performance dei tuoi contenuti social con dati in tempo reale',
            'icon' => 'BarChart3',
            'sort_order' => 5,
            'articles' => [
                [
                    'slug' => 'leggere-insights',
                    'title' => 'Leggere gli Insights dei tuoi social',
                    'is_featured' => true,
                    'sort_order' => 1,
                    'excerpt' => 'Impressioni, copertura, engagement e follower per ogni piattaforma. Tutto in un\'unica pagina, aggiornabile con un click.',
                    'content' => <<<'MD'
# Leggere gli Insights dei tuoi social

La pagina **Insights** raccoglie le statistiche di performance di tutti i tuoi profili social collegati, in un'unica vista.

## Come accedere

Clicca su **Insights** nella barra laterale sinistra.

## Selezionare il brand

Se hai più brand, usa il selettore in alto a destra per scegliere quale brand analizzare.

## Scegliere il periodo

Puoi visualizzare i dati degli ultimi **7**, **30** o **90 giorni** usando il selettore accanto al brand.

## Le card riepilogative

In alto trovi 4 card con i dati aggregati di tutte le piattaforme:

- **Impressioni Totali** — quante volte i tuoi contenuti sono stati visualizzati
- **Copertura Totale** — quante persone uniche hanno visto i tuoi contenuti
- **Engagement Rate** — percentuale di interazione rispetto alla copertura
- **Post Pubblicati** — quanti post sono stati pubblicati rispetto al totale generato

## Performance per piattaforma

Sotto le card trovi il dettaglio per ogni piattaforma collegata:

- **Impressioni** e **Copertura** della piattaforma
- **Engagement** totale
- **Follower** attuali
- Dettaglio interazioni: **Like**, **Commenti**, **Condivisioni**

## Aggiornare i dati

Clicca il pulsante **Aggiorna** in alto a destra. Il sistema raccoglie i dati più recenti direttamente dalle API delle piattaforme social. L'aggiornamento impiega pochi secondi.

## Se vedi "Nessuna connessione social attiva"

Significa che il brand selezionato non ha ancora profili social collegati. Vai su **Social** nella barra laterale e collega almeno un account.

> 💡 **Consiglio:** controlla gli Insights settimanalmente per capire quali tipi di contenuto funzionano meglio e adatta il brief del prossimo Piano Editoriale di conseguenza.
MD,
                ],
            ],
        ];
    }

    private function account(): array
    {
        return [
            'slug' => 'account',
            'title' => 'Account e Abbonamento',
            'description' => 'Gestire il piano, i limiti mensili e gli utenti del team',
            'icon' => 'CreditCard',
            'sort_order' => 6,
            'articles' => [
                [
                    'slug' => 'piani',
                    'title' => 'I piani disponibili',
                    'sort_order' => 1,
                    'excerpt' => 'Basic, Standard, Pro ed Enterprise. Scopri le differenze e scegli quello giusto per le tue esigenze.',
                    'content' => <<<'MD'
# I piani disponibili

## Confronto piani

| | Basic | Standard | Pro | Enterprise |
|---|---|---|---|---|
| **Prezzo mensile** | €29 | €79 | €199 | Su misura |
| **Brand** | 1 | 3 | Illimitati | Illimitati |
| **Utenti** | 1 | 3 | 10 | Illimitati |
| **Calendari/mese** | 3 | 10 | Illimitati | Illimitati |
| **Immagini AI/mese** | 20 | 60 | 200 | Illimitati |
| **Export Excel/PDF** | ❌ | ✅ | ✅ | ✅ |
| **API accesso** | ❌ | ❌ | ✅ | ✅ |
| **Supporto prioritario** | ❌ | ❌ | ✅ | ✅ |

## Quale piano scegliere?

**Basic** — Freelance o piccola attività con un solo brand.

**Standard** — Più brand o piccolo team. Export calendari incluso.

**Pro** — Molti brand, tante immagini AI, accesso API.

**Enterprise** — Agenzia o grande azienda. Contattaci per un preventivo.

## Come cambiare piano

Vai su **Impostazioni → Abbonamento** → **Cambia piano**. Il cambio è immediato.
MD,
                ],
                [
                    'slug' => 'limiti-mensili',
                    'title' => 'Gestire i limiti mensili',
                    'sort_order' => 2,
                    'excerpt' => 'Token AI, immagini e calendari si resettano ogni mese. Ecco come monitorarli e cosa fare quando si esauriscono.',
                    'content' => <<<'MD'
# Gestire i limiti mensili

## Dove vedere i consumi

Nella barra laterale sinistra trovi le barre di utilizzo in tempo reale. Per il dettaglio vai su **Impostazioni → Utilizzo**.

I contatori si resettano il **1° di ogni mese**.

## Se esaurisci i token

- Non puoi generare nuovi calendari o rigenerare post
- I post già generati restano accessibili e pubblicabili
- Puoi caricare immagini manualmente senza limiti

**Soluzione:** fai upgrade al piano superiore. Il limite si aggiorna subito.

## Se esaurisci le immagini AI

Puoi ancora generare testo. Puoi sempre **caricare immagini manualmente**.

## Come ottimizzare i consumi

- Genera il calendario completo in una sola operazione
- Usa immagini tue quando hai materiale fotografico di qualità
- Usa il brief nel wizard per ridurre le rigenerazioni
MD,
                ],
                [
                    'slug' => 'aggiungere-utenti',
                    'title' => 'Aggiungere utenti al team',
                    'sort_order' => 3,
                    'excerpt' => 'Invita colleghi o collaboratori e assegna loro il ruolo giusto: da chi può solo leggere a chi può pubblicare.',
                    'content' => <<<'MD'
# Aggiungere utenti al team

## I ruoli disponibili

**Owner** — Accesso completo inclusa fatturazione.

**Admin** — Tutto tranne la fatturazione. Per il responsabile marketing.

**Editor** — Crea, modifica, genera e pubblica. Non gestisce utenti.

**Viewer** — Solo visualizzazione. Per clienti o stakeholder.

## Come aggiungere un utente

1. Vai su **Impostazioni → Team**
2. Clicca **+ Invita utente**
3. Inserisci l'email e scegli il ruolo
4. Clicca **Invia invito**

L'utente riceve un'email con il link per accettare.

## Limiti per piano

- **Basic**: 1 utente
- **Standard**: fino a 3 utenti
- **Pro**: fino a 10 utenti
- **Enterprise**: illimitati

> 💡 Per agenzie o freelance esterni usa il ruolo **Editor**: accesso operativo senza esporre dati di fatturazione.
MD,
                ],
            ],
        ];
    }
}
