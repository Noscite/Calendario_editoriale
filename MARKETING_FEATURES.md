# Kalendarium — Il marketing social delle PMI italiane, in autopilota

**Kalendarium** è la piattaforma SaaS che genera il piano editoriale social della tua azienda con l'intelligenza artificiale, lo pubblica in automatico su tutti i canali e ti aiuta a gestire recensioni e reputazione online — senza agenzia, senza social media manager, senza perdere ore ogni settimana.

> Pensata e sviluppata in Italia da **Noscite SRLS** (Milano) per le PMI italiane: contenuti in italiano, trend locali, conformità ai settori regolati.

---

## In una frase

> Dai a Kalendarium il tuo brand. Lui ricerca i trend, scrive i post, crea le immagini, costruisce il calendario e pubblica al momento giusto su LinkedIn, Instagram, Facebook e Google. Tu approvi. Basta.

---

## Perché Kalendarium (i benefici)

- 🕐 **Recuperi tempo** — da ore di lavoro a pochi minuti di approvazione settimanale.
- 🤖 **Non parti mai dal foglio bianco** — l'AI produce copy e immagini brandizzati, pronti da pubblicare.
- 📍 **Sei sempre sul pezzo** — ricerca automatica dei trend di settore, anche a livello locale/territoriale.
- 📅 **Pubblichi in automatico** — programma e dimentica: i post partono da soli all'orario giusto.
- ⭐ **Curi la reputazione** — raccolta e risposta assistita alle recensioni Google.
- 🇮🇹 **Rispetti le regole** — vincoli deontologici automatici per settori regolati (medico, legale, psicologia, finanza).
- 📊 **Sai cosa funziona** — metriche social raccolte in automatico.

---

## Le funzionalità, per aree

### 1. 🧠 Generazione AI del piano editoriale

Il cuore di Kalendarium. Un'unica pipeline di intelligenza artificiale trasforma il tuo brand in un calendario editoriale completo:

- **Ricerca trend automatica** di settore (fonte Perplexity), con cache intelligente su base nazionale e locale per contenuti sempre attuali.
- **Copywriting AI** — testi generati con Claude, coerenti con tono di voce, valori e obiettivi del brand.
- **Immagini generate su misura** — visual creati con i più recenti modelli di image generation, brandizzati e pronti alla pubblicazione.
- **Calendario pronto da approvare** — non un post alla volta, ma un intero piano editoriale strutturato.

### 2. 📢 Campagne editoriali mirate

- Generazione di **campagne** tematiche dedicate (lanci, promozioni, ricorrenze).
- **Estrazione testo da allegati**: carica un documento (brief, listino, PDF) e Kalendarium ne ricava i contenuti per la campagna.
- Controllo automatico dei limiti di piano per una generazione sempre sotto controllo.

### 3. 🚀 Pubblicazione automatica multicanale

Publisher nativi, con OAuth completo e refresh automatico dei token, per:

- **LinkedIn** (profili personali e pagine aziendali)
- **Instagram**
- **Facebook**
- **Google Business Profile**

Con in più:

- **Scheduler intelligente** — ogni post parte in automatico all'orario programmato.
- **Programmazione visuale** del calendario con stati chiari (bozza, programmato, pubblicato).
- **Raccolta metriche** dai social ogni poche ore per monitorare le performance.

### 4. 📍 Contenuti iperlocali (Territorial)

La marcia in più per le attività con radicamento sul territorio:

- Aggancio a **eventi ed iniziative del territorio** (integrazione dati aperti, es. ecosistema E015 / Regione Lombardia).
- **Matching automatico** tra brand, comune di riferimento ed eventi rilevanti.
- Generazione di post legati agli eventi locali, con immagini dedicate — per contenuti che parlano davvero alla community.

### 5. ⭐ Reputation & recensioni

Non solo pubblicazione: Kalendarium presidia la reputazione online.

- **Raccolta automatica delle recensioni Google**.
- **Risposte generate dall'AI**, contestualizzate e coerenti con il brand grazie a una base di conoscenza semantica (retrieval vettoriale).
- **Scoring delle recensioni** e valutazione di idoneità alla risposta automatica.
- Gestione delle quote di risposta per piano di abbonamento.

### 6. 🔍 Brand Digital Audit (strumento pre-vendita)

Un motore di analisi che genera **report PDF brandizzati** sullo stato digitale di un sito — ideale per acquisire e convincere nuovi clienti:

- **Accessibilità** (WCAG 2.1) tramite scansione headless del sito reale renderizzato.
- **Performance & SEO** (Google PageSpeed) e **sicurezza SSL** (SSL Labs).
- **Analisi visiva/neuromarketing** con AI multimodale (Claude Vision).
- **Scoring pesato per settore** (es. più peso alla SEO per turismo/food, alla privacy per sanità).
- Funziona anche su **prospect** senza account e genera **link di condivisione pubblici** dei report.

### 7. 🎨 Brand kit & identità

- Profilo brand completo: tono di voce, valori, target, palette, obiettivi.
- **Indicatore di completezza del brand** per massimizzare la qualità dei contenuti generati.
- **Vincoli deontologici automatici**: per settori regolati (psicologia, medicina, legale, finanza) l'AI filtra da sola i suggerimenti non conformi. Rilevamento del settore automatico e confermabile dall'utente.

### 8. 👥 Multi-utente, team e ruoli

- **Organizzazioni multi-tenant**: ogni azienda ha il suo spazio isolato e sicuro.
- **Inviti** per collaboratori e **ruoli/permessi** granulari (RBAC).
- **Registro attività** (activity log) per tracciabilità e controllo.
- Gestione di **chiavi API proprie** per i clienti che vogliono usare i propri account AI.

### 9. 💳 Abbonamenti flessibili

Piani pensati per crescere con te (pagamento mensile o annuale, con **prova gratuita** e reminder):

| Piano | Prezzo/mese | Piani editoriali/mese | Immagini/mese |
| --- | --- | --- | --- |
| **Small** | € 29 | 5 | 15 |
| **Standard** | € 79 | 15 | 40 |
| **Pro** | € 199 | 50 | 120 (+ overage a consumo) |
| **Unlimited** | su misura | illimitati | illimitate (chiavi AI proprie) |

- **Fatturazione e checkout** gestiti con Stripe.
- **Tracciamento dei consumi** trasparente (token, immagini, generazioni) e calcolo costi in tempo reale.

### 10. 🔔 Notifiche & centro assistenza

- Sistema di **notifiche** integrato per approvazioni, pubblicazioni e scadenze.
- **Help center** con articoli di supporto integrati nel prodotto.

### 11. 🔌 Integrazioni AI-native (MCP)

Kalendarium espone i propri dati di brand e campagna tramite **server MCP (Model Context Protocol)**: la piattaforma è pronta a dialogare con assistenti AI e strumenti esterni di nuova generazione.

---

## Sotto il cofano (per chi vuole i dettagli tecnici)

Piattaforma moderna e affidabile, costruita per la scala:

- **Backend**: Laravel 12 su PHP 8.4, PostgreSQL, Redis, code gestite con Horizon.
- **Real-time**: WebSocket (Laravel Reverb) per aggiornamenti live.
- **Frontend**: React 19, Vite, Tailwind, esperienza SPA fluida.
- **AI**: Claude (Anthropic), Perplexity, modelli di image generation OpenAI.
- **Sicurezza & affidabilità**: multi-tenancy isolata, autenticazione API (Sanctum), monitoraggio errori (Sentry), backup automatici.
- **Made in Italy**, hosting europeo, dati trattati nel rispetto delle normative.

---

## Messaggi chiave per la campagna

**Headline candidate**
- *"Il tuo social media manager AI. Lavora 24/7, non chiede ferie."*
- *"Piano editoriale, contenuti e pubblicazione. Tutto automatico. Tu approvi."*
- *"Dai il tuo brand a Kalendarium. Al resto pensa l'AI."*

**Sottotitolo**
- *La piattaforma italiana che genera e pubblica i tuoi contenuti social con l'intelligenza artificiale — e ti aiuta a gestire recensioni e reputazione.*

**Call to action**
- *Prova Kalendarium gratis →*

**Target ideali**
- PMI e professionisti senza tempo/risorse per il marketing continuativo.
- Attività locali (retail, food, turismo, servizi) che vogliono contenuti legati al territorio.
- Studi e settori regolati (medici, legali, psicologi, consulenti finanziari) che hanno bisogno di comunicare **in conformità**.
- Agenzie e consulenti che vogliono industrializzare la produzione di contenuti per più clienti.

**Differenziatori da enfatizzare**
1. Pipeline **end-to-end**: dalla ricerca trend alla pubblicazione, non solo "un tool per scrivere post".
2. **Iperlocale**: contenuti agganciati agli eventi del territorio.
3. **Conformità di settore integrata** (unica sul mercato per i settori regolati italiani).
4. **Reputation management** incluso (recensioni Google + risposte AI).
5. **Brand Audit** come strumento di acquisizione clienti.
6. **Made in Italy**, in italiano, per il mercato italiano.

---

*Documento interno per campagna marketing — Kalendarium, © Noscite SRLS, Milano. Le funzionalità in roadmap (video generation, CRM, App Review Meta/LinkedIn in produzione) non sono incluse tra i claim vendibili finché non rilasciate.*
