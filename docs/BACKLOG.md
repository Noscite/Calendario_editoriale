# Kalendarium — Technical Backlog

Generato il **2026-04-26** al termine della sessione di hardening pre-go-live.
Ogni item ha path, contesto e impatto per essere autoesplicativo tra mesi.

---

## 🔴 Fix critici — da fare PRIMA di acquisire clienti paganti

### BKP-001 — Bug cron in install-backup.sh e uninstall-backup.sh
**File:** `scripts/infrastructure/install-backup.sh:65`, `uninstall-backup.sh:28`

`set -euo pipefail` è attivo. La pipeline:
```bash
(crontab -l 2>/dev/null | grep -v "kalendarium-backup"; echo "${CRON_LINE}") | crontab -
```
Se root non ha un crontab esistente, `crontab -l` ritorna exit 1, `grep -v` riceve input vuoto e termina con exit 1, `set -e` fa abortire lo script silenziosamente prima di installare il cron job. Lo script dice "✓ Cron job configurato" ma il job non è mai stato scritto.

**Fix:**
```bash
EXISTING=$(crontab -l 2>/dev/null | grep -v "kalendarium-backup" || true)
{ [[ -n "${EXISTING}" ]] && echo "${EXISTING}"; echo "${CRON_LINE}"; } | crontab -
```
Stesso pattern in `uninstall-backup.sh:28`.

**Impatto:** Il backup automatico non parte su VPS con crontab root vergine (es. dopo fresh install). Il backup manuale funziona lo stesso.

---

### INFRA-001 — Backup off-site NON configurato
**File:** `docs/INFRASTRUCTURE.md` (sezione off-site)

Confermato consapevolmente il 2026-04-26: se il VPS va offline, perdita dati totale. Da configurare non appena ci sono clienti paganti. Opzioni documentate in `INFRASTRUCTURE.md` (Hetzner Storage Box, B2, rsync).

**Impatto:** Rischio esistenziale per il business. Priorità massima post-V1.

---

### SUB-001 — Queue "emails" non corrisponde alla config Horizon
**File:** `app/Mail/Subscription/*.php`, `config/horizon.php:202`

I 4 Mailable usano `$this->onQueue('emails')` (stringa singolare). Horizon è configurato con queue `['pubblicazione', 'generazione', 'default', 'email']` (singolare `email`, non `emails`). Le email di subscription vengono dispatched su una queue che Horizon non sta ascoltando — rimangono nel backlog Redis senza essere processate.

**Fix:** uniformare a `email` oppure aggiungere `emails` alla config Horizon:
```php
// config/horizon.php riga 202
'queue' => ['pubblicazione', 'generazione', 'default', 'email', 'emails'],
```
Oppure cambiare tutti i Mailable a `onQueue('email')`.

**File coinvolti:**
- `app/Mail/Subscription/WelcomeMail.php`
- `app/Mail/Subscription/TrialExpiredMail.php`
- `app/Mail/Subscription/SubscriptionActivatedMail.php`
- `app/Mail/Subscription/SubscriptionExpiringMail.php`
- `config/horizon.php`

---

## 🟡 Post-V1 — Da fare dopo i primi clienti paganti

### SUB-002 — Feature flags non enforced nei controller
**File:** `config/trial.php:9-13`, `app/Domain/Subscription/Models/Subscription.php:141`

`Subscription::canUseFeature(string $feature)` esiste ed è testata, ma **nessun controller la chiama**. Le feature listed in `trial.features_disabled_during_trial` (social_auto_publish, social_account_connect, multiple_calendars_per_month, advanced_export) sono bloccate solo nella documentazione, non nel codice.

```bash
# Verifica: zero risultati = nessun enforcement
grep -rn "canUseFeature" app/ --include="*.php" | grep -v "Subscription\|Test"
```

**Fix:** aggiungere `$subscription->canUseFeature('social_auto_publish') || abort(403)` nei controller rilevanti (SocialController, ExportController, GenerationController).

---

### SUB-003 — Legacy fallback nel middleware EnsureSubscriptionActive da rimuovere
**File:** `app/Http/Middleware/EnsureSubscriptionActive.php:48-58`

Il middleware ha un fallback che, se non esiste un record `subscriptions` per l'org, accetta ugualmente se `organizations.subscription_status` è `active` o `trial`. Introdotto per evitare di rompere i test esistenti (che creano org senza subscription record).

Questo fallback deve sopravvivere finché esistono org senza subscription record. Una volta che il seeder/onboarding crea sempre un record Subscription, il fallback può essere rimosso e il path semplificato.

**Trigger per rimuovere:** quando `SELECT COUNT(*) FROM organizations LEFT JOIN subscriptions ON organizations.id = subscriptions.organization_id WHERE subscriptions.id IS NULL` → 0.

---

### SUB-004 — `organizations.subscription_status` è ormai ridondante
**File:** `database/migrations/` (campo legacy), `app/Domain/Subscription/Services/SubscriptionStateService.php:250`

La tabella `organizations` ha un campo `subscription_status` che `syncOrganizationStatus()` mantiene sincronizzato con `subscriptions.status`. Questo campo era il meccanismo originale (Python legacy). Ora è duplicato. `CheckSubscriptionLimits` lo legge ancora (riga 46+ del middleware).

**Piano di rimozione:**
1. Aggiornare `CheckSubscriptionLimits` a leggere da `org->subscription->status`
2. Verificare che nessun'altra query usi `subscription_status` direttamente
3. Migration per droppare la colonna
4. Rimuovere `syncOrganizationStatus()` da `SubscriptionStateService`

---

### SUB-005 — `is_system_tenant` non visibile/modificabile da Filament
**File:** `app/Filament/Admin/Resources/OrganizationResource.php:39-124`

Il campo `is_system_tenant` è nella tabella e nel model ma non è esposto nel form Filament dell'OrganizationResource. Può essere modificato solo via tinker o migration. Se serve aggiungere un secondo system tenant (test org, staging), non c'è UI.

**Fix:** aggiungere al form in OrganizationResource:
```php
Forms\Components\Toggle::make('is_system_tenant')
    ->label('System Tenant (bypassa subscription)')
    ->helperText('Solo per org interne Noscite.')
    ->visible(fn () => auth()->user()?->role === 'superuser'),
```

---

### SUB-006 — ViewSubscription Filament page è vuota
**File:** `app/Filament/Admin/Resources/SubscriptionResource/Pages/ViewSubscription.php`

La pagina di dettaglio subscription eredita da `ViewRecord` ma non definisce `infolist()`. Filament mostra una pagina vuota. Le azioni disponibili sono solo quelle della tabella list.

**Fix:** implementare `infolist()` con tutti i campi rilevanti (status, date, payment_reference, token usage, org name, activated_by).

---

### SUB-007 — `extendTrial` non dispatcha eventi
**File:** `app/Domain/Subscription/Services/SubscriptionStateService.php` (metodo extendTrial)

Gli altri metodi dispatching events (`TrialStarted`, `TrialExpired`, `SubscriptionActivated`, ecc.). `extendTrial` non dispatcha nulla. Se in futuro si vuole inviare email "il tuo trial è stato esteso di 7 giorni", non c'è il hook.

**Fix:** creare `TrialExtended` event (stesso pattern dei 7 esistenti in `app/Domain/Subscription/Events/`) e dispatcharlo in `extendTrial`.

---

### SUB-008 — `markPending` action in Filament chiama `expireTrial()` che valida trial_ends_at < now
**File:** `app/Filament/Admin/Resources/SubscriptionResource.php` (action markPending), `app/Domain/Subscription/Services/SubscriptionStateService.php:66`

`expireTrial()` lancia `BusinessException('TRIAL_NOT_EXPIRED')` se `trial_ends_at` è nel futuro. L'action "Marca pending" è visibile solo se `status === 'trial'`, ma non controlla se il trial è effettivamente scaduto. Se l'admin clicca su un trial ancora attivo, otterrà un errore 422 non gestito nel contesto Filament (nessun try-catch nell'action).

**Fix:** wrappare l'action in try-catch e mostrare `Notification::make()->danger()`, oppure aggiungere un metodo `forceExpireTrial()` senza la validazione temporale.

---

### INFRA-002 — shellcheck non installato sul VPS
**File:** `scripts/infrastructure/` (tutti gli .sh)

Il VPS non ha shellcheck. Gli script bash sono stati validati solo con `bash -n` (syntax check). Shellcheck farebbe rilevare pattern problematici (SC2086, SC2048, ecc.) che `bash -n` non vede.

```bash
sudo apt install shellcheck
shellcheck /var/www/Kalendarium/scripts/infrastructure/*.sh
```

---

### TEST-001 — Test helper `createAuthenticatedUser` importa Subscription senza usarla
**File:** `tests/Pest.php:41`

`use App\Domain\Subscription\Models\Subscription;` è importato dopo un refactor che ha poi scelto il fallback legacy nel middleware. L'import rimane ma non viene usato nell'helper. Pulizia cosmetica ma segnale di design temporaneo.

---

### TEST-002 — Nessun OrganizationFactory né UserFactory
**File:** `database/factories/` (assenti)

Non esistono `OrganizationFactory` e `UserFactory`. I test usano `Organization::create()` e `User::create()` direttamente via `createAuthenticatedUser()` in Pest.php. Questo significa che qualunque futuro test che tenti `Organization::factory()` o `User::factory()` esploderà con "factory not found". La SubscriptionFactory crea le org inline per ovviare.

**Fix:** creare i factory mancanti, o accettare il pattern e documentarlo.

---

## 🔵 V2 / Futuro

### V2-001 — Stripe non usato (bonifico manuale)
**File:** `routes/api.php:51`, `app/Http/Controllers/Api/StripeWebhookController.php`

Stripe SDK è installato, c'è un webhook endpoint, ma il flusso di pagamento è interamente manuale (admin attiva via Filament dopo aver ricevuto bonifico). Con 20+ clienti questo non scala. Stripe Checkout o Stripe Payment Links sarebbero il passo naturale.

**Impatto attuale:** zero, il flusso manuale funziona per i primi clienti. Rivalutare a 15-20 clienti attivi.

---

### V2-002 — `BusinessException` richiede pattern manuale nei controller
**File:** `app/Exceptions/BusinessException.php`, controller vari

Il pattern corretto è: il controller chiama `report($originalException)` poi `throw new BusinessException(...)`. Se uno sviluppatore dimentica il `report()`, l'eccezione originale non arriva mai a Sentry. Non c'è meccanismo automatico di enforcement.

**Fix:** creare un metodo statico factory che incapsula il pattern:
```php
BusinessException::wrap($original, 'PUBLIC_MESSAGE', 'ERROR_CODE', 422);
// internamente: report($original); throw new self(...);
```

---

### V2-003 — `/subscription/inactive` è una pagina standalone senza navbar
**File:** `resources/views/subscription/inactive.blade.php`

La pagina è HTML puro senza il layout React del frontend. Un utente che arriva da una chiamata API redirettata vede una pagina con stile diverso dall'app. Idealmente il frontend React dovrebbe intercettare il 402 e mostrare una modal o una route dedicata.

---

### V2-004 — Trial reminder command separato da state machine command
**File:** `routes/console.php:24`, `app/Console/Commands/` (TrialSendRemindersCommand — da verificare se esiste)

Il cron ha `trial:send-reminders` alle 09:00 e `subscriptions:update-states` alle 02:00. Se `update-states` sposta trial→pending_payment alle 02:00, alle 09:00 `send-reminders` troverebbe status già `pending_payment` e non invierebbe il reminder. L'ordine e la logica di questi due command andrebbero rivalidati per casi edge (trial che scade esattamente nella notte).

---

### V2-005 — Nessun rate limit sulle email di subscription
**File:** `app/Listeners/Subscription/SendTrialExpiredEmail.php` e altri listener

Se `subscriptions:update-states` viene eseguito più volte (bug, retry, esecuzione manuale), le email vengono reinviate. Non c'è deduplicazione (es. "già inviato email di trial expired oggi per questa org"). Per il volume attuale (primi 20 clienti) è irrilevante. Con cron corretto e event dispatching idempotente non si presenta.

---

### V2-006 — Filament SubscriptionResource non ha paginazione configurata
**File:** `app/Filament/Admin/Resources/SubscriptionResource.php`

La tabella usa i default Filament (15 record per pagina). Con molte org non è un problema, ma mancano filtri di ricerca rapida per organization name nella table header search. La colonna `organization.name` ha `searchable()` ma non c'è `$table->searchable()` globale.

---

### FUTURE-001 — Rimuovere colonne Stripe da organizations una volta confermato il no-Stripe
**File:** `database/migrations/` (colonne `stripe_customer_id`, `stripe_subscription_id` in organizations)
**File:** `app/Filament/Admin/Resources/OrganizationResource.php:101-112`

Se si decide definitivamente di non usare Stripe, queste colonne sono dead weight. Se si decide di usarlo, vanno riempite. Per ora rimangono.

---

### FUTURE-002 — `numfmt` in backup script assume coreutils GNU
**File:** `scripts/infrastructure/kalendarium-backup.sh:56`

```bash
log "Backup completato: ${BACKUP_FILE} ($(numfmt --to=iec "${FILE_SIZE}"))"
```

`numfmt` è parte di GNU coreutils, presente su Ubuntu 24.04. Su Alpine, macOS, o container minimali potrebbe non esserci. Se il backup script viene portato su altri ambienti, questa riga va adattata o `numfmt` va rimpiazzato con `du -sh`.

---

## 📋 Decision log — scelte consapevoli da non "fixare"

| ID | Decisione | Data | Motivo |
|----|-----------|------|--------|
| DEC-001 | Backup solo locale, no off-site | 2026-04-26 | Trade-off consapevole, costo off-site rimandato a post-V1 |
| DEC-002 | Pagamenti manuali via bonifico, Stripe non attivato | 2026-04-26 | Complessità sproporzionata per i primi 20 clienti |
| DEC-003 | `subscription_status` su `organizations` mantenuto in sync (ridondante) | 2026-04-26 | Backward compat con `CheckSubscriptionLimits` — rimozione in SUB-004 |
| DEC-004 | `EnsureSubscriptionActive` ha fallback legacy su `subscription_status` | 2026-04-26 | Evita di rompere test esistenti, da rimuovere (SUB-003) |
| DEC-005 | Nessun `OrganizationFactory` / `UserFactory` — test usano `::create()` diretto | 2026-04-26 | Pattern consolidato in Pest.php helper, non val la pena di cambiare ora |
