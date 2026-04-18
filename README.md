# Kalendarium

**SaaS multi-tenant per PMI italiane che genera piani editoriali social con AI e pubblica automaticamente sulle principali piattaforme aziendali.**

Progetto proprietario — **Noscite SRLS**, Milano. Codice privato, non open source.

---

## Cosa fa

### Fase 1 — Piano editoriale automatico

Il sistema ricerca i trend di settore, genera contenuti brandizzati (copy + immagini) e costruisce un calendario editoriale pronto da approvare. Pipeline AI:

```
Perplexity (trend research, cache L1 nazionale 14gg / L2 locale 7gg)
    → Claude Sonnet (generazione copy)
    → GPT-4o mini (prompt immagini)
    → gpt-image-1 / DALL·E 3 (immagini)
```

### Fase 2 — Pubblicazione automatica e integrazioni aziendali

Publisher nativi per **LinkedIn**, **Instagram**, **Facebook**, **Google Business Profile**. OAuth completo, refresh token automatico, scheduler che pubblica nella finestra ±2 minuti dallo slot programmato. Raccolta metriche social ogni 6 ore.

Roadmap: integrazione CRM per profilazione utente, Stripe Checkout in produzione, Meta/LinkedIn App Review, video generation (Fal.ai → Kling/Runway/Luma).

### Brand Digital Audit

Sistema di audit pre-vendita che analizza siti di prospect e clienti e genera report PDF brandizzati. Pipeline a tre livelli:

- **L1** — microservizio Playwright headless (Chromium) + `axe-core` (WCAG 2.1) su porta `3099`
- **L2** — Google PageSpeed Insights API, SSL Labs API
- **L3** — Claude Vision (analisi multimodale neuromarketing)

Scoring pesato per settore (es. turismo/food: SEO 40%; psicologia/medicina: GDPR 30%) con vincoli deontologici per i settori regolati. Endpoint anche per prospect senza brand record (`/api/audit/prospect`) e link di condivisione pubblico (`/api/audit/share/{token}`).

---

## Stack tecnologico

| Componente              | Versione                                    |
| ----------------------- | ------------------------------------------- |
| PHP                     | 8.4 (FPM)                                   |
| Laravel                 | 12                                          |
| Filament                | 5.2 (admin panel, mount `/filament-admin`)  |
| Horizon                 | 5.44 (queue dashboard)                      |
| Reverb                  | 1.7 (WebSocket)                             |
| Sanctum                 | 4.3 (API auth)                              |
| stancl/tenancy          | 3.9 (multi-tenant)                          |
| spatie/permission       | 7.1 (RBAC)                                  |
| barryvdh/laravel-dompdf | 3.1 (report PDF audit)                      |
| sentry/sentry-laravel   | 4.21 (error monitoring)                     |
| stripe/stripe-php       | 19.4                                        |
| predis/predis           | 3.4                                         |
| PostgreSQL              | 15+                                         |
| Redis                   | 7+ (cache, queue, session, broadcast)       |
| Node                    | 20 LTS (microservizio Playwright + build)   |
| Frontend                | React 19 + Vite 7 + Tailwind 4 + React Router 7 + TanStack Query + Zustand |
| Test                    | Pest 3.8 / PHPUnit 11.5                     |

---

## Architettura di produzione

Sul VPS tutto vive in due path principali:

```
/var/www/Kalendarium/              # Repo Laravel + frontend React
├── app/Domain/                    # Domain-Driven Design
│   ├── Audit/                     # Brand Digital Audit
│   ├── Brand/ Document/ Generation/ Help/ Notification/
│   ├── Organization/ Post/ Project/ Shared/ Social/
│   ├── Subscription/ User/
├── frontend/                      # Progetto React/Vite standalone
│   ├── src/                       # App.jsx, pages/, components/, services/, store/
│   └── dist/                      # Build statico servito da Nginx (gitignored)
├── public/                        # DocumentRoot Laravel (index.php)
├── resources/views/pdf/           # Template Blade report audit
├── config/                        # services.php, tenancy.php, dompdf.php, horizon.php...
├── database/migrations/           # 28 migrations
└── horizon.service.example        # Template systemd

/var/www/playwright-service/       # Microservizio audit (Node.js standalone)
├── server.js                      # Endpoint /health, /analyze, porta 3099
├── package.json
└── node_modules/
```

Un solo dominio pubblico (`kalendarium.it`) serve sia il frontend statico sia le API PHP-FPM. Nessun sottodominio `api.*`.

---

## Requisiti VPS nuovo

- **OS**: Ubuntu 24.04 LTS (22.04 ok)
- **Risorse**: 4 vCPU, 8 GB RAM, 50 GB SSD (Chromium di Playwright mangia RAM)
- **Rete**: IPv4 pubblico, DNS A record per `kalendarium.it` e `www.kalendarium.it`
- **Utente deploy**: `ubuntu` con sudo (tutto quello sotto è eseguito come `ubuntu`, tranne dove indicato `sudo`)

---

## Installazione passo-passo

### 1. Pacchetti di sistema

```bash
sudo apt update && sudo apt upgrade -y

# Repository PHP 8.4
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update

sudo apt install -y \
    nginx \
    postgresql postgresql-contrib \
    redis-server \
    php8.4-fpm php8.4-cli php8.4-pgsql php8.4-redis \
    php8.4-mbstring php8.4-xml php8.4-curl php8.4-bcmath \
    php8.4-intl php8.4-zip php8.4-gd php8.4-dom \
    git unzip curl supervisor certbot python3-certbot-nginx \
    build-essential

# Composer 2
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node.js 20 LTS
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### 2. PostgreSQL

```bash
sudo -u postgres psql <<SQL
CREATE USER noscite WITH PASSWORD 'CHANGE_ME_STRONG_PASSWORD';
CREATE DATABASE noscite_calendar OWNER noscite;
GRANT ALL PRIVILEGES ON DATABASE noscite_calendar TO noscite;
SQL
```

Verifica: `psql -h 127.0.0.1 -U noscite -d noscite_calendar -c '\conninfo'`.

### 3. Redis

Default Ubuntu su `127.0.0.1:6379` va bene. Rendilo persistente:

```bash
sudo sed -i 's/^# *supervised .*/supervised systemd/' /etc/redis/redis.conf
sudo systemctl enable --now redis-server
redis-cli ping   # -> PONG
```

### 4. Clone del repo + dipendenze PHP

Il repo è privato (`Noscitedevteam/Calendario_editoriale`). Serve una deploy key SSH o un Personal Access Token GitHub.

```bash
sudo mkdir -p /var/www
sudo chown ubuntu:ubuntu /var/www
cd /var/www
git clone -b production git@github.com:Noscitedevteam/Calendario_editoriale.git Kalendarium
cd Kalendarium
composer install --no-dev --optimize-autoloader
```

### 5. Configurazione `.env`

```bash
cp .env.example .env
php artisan key:generate
nano .env
```

Variabili obbligatorie da compilare (`.env.example` le elenca tutte):

- `DB_PASSWORD` — la password scelta al punto 2
- `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, `PERPLEXITY_API_KEY` — chiavi di sistema per il tenant Noscite. I tenant clienti hanno chiavi proprie cifrate in `brand_api_keys`
- `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`
- `FACEBOOK_APP_ID/SECRET`, `INSTAGRAM_APP_ID/SECRET`, `LINKEDIN_CLIENT_ID/SECRET`, `GOOGLE_CLIENT_ID/SECRET`
- `AZURE_CLIENT_ID/SECRET/TENANT_ID` se si usa SSO Microsoft
- `MAIL_USERNAME`, `MAIL_PASSWORD` (Brevo SMTP)
- `SENTRY_LARAVEL_DSN`
- `GOOGLE_PAGESPEED_API_KEY` (opzionale — aumenta il rate limit PageSpeed)

Variabili del microservizio audit, già con default corretto:

```env
PLAYWRIGHT_SERVICE_URL=http://127.0.0.1:3099
PLAYWRIGHT_TIMEOUT=45
PLAYWRIGHT_ENABLED=true
PAGESPEED_ENABLED=true
SSLLABS_ENABLED=true
```

### 6. Storage, cache, migrazioni

```bash
cd /var/www/Kalendarium
php artisan storage:link
sudo chown -R ubuntu:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

php artisan migrate --force
php artisan db:seed --force   # piani subscription, ruoli Spatie, help articles
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 7. Build del frontend React

Il frontend è un progetto Vite **standalone** in `/frontend/`. Il `vite.config.js` alla root del repo è legacy (l'app `resources/js` contiene solo `import './bootstrap'` e `public/build` non viene più generato) — ignoralo.

```bash
cd /var/www/Kalendarium/frontend
npm ci
npm run build        # output in frontend/dist/
```

Nginx (sezione 9) servirà `frontend/dist/index.html` come SPA fallback.

### 8. Microservizio Playwright (Node.js, porta 3099)

Il microservizio **non è nel repo** Laravel — vive in una cartella separata. Se stai migrando da un VPS esistente, clonalo da lì; se parti da zero, recuperalo dal backup interno.

```bash
sudo mkdir -p /var/www/playwright-service
sudo chown ubuntu:ubuntu /var/www/playwright-service
cd /var/www/playwright-service
# ... piazza server.js + package.json ...
npm ci

# Chromium + dipendenze di sistema per l'utente ubuntu
npx playwright install chromium --with-deps
```

Test manuale:

```bash
node server.js &
curl http://127.0.0.1:3099/health
# atteso: {"status":"ok","service":"playwright-audit","version":"1.0.0"}
kill %1
```

### 9. Nginx

Crea `/etc/nginx/sites-available/kalendarium`:

```nginx
server {
    listen 80;
    server_name kalendarium.it www.kalendarium.it;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name www.kalendarium.it;
    # ssl_certificate / ssl_certificate_key aggiunti da certbot
    return 301 https://kalendarium.it$request_uri;
}

server {
    listen 443 ssl;
    server_name kalendarium.it;
    client_max_body_size 25M;
    # ssl_certificate / ssl_certificate_key aggiunti da certbot

    # Asset Laravel (css, js, fonts, build Vite legacy, storage pubblico)
    location ~* ^/(css|js|fonts|images|storage|build)/ {
        root /var/www/Kalendarium/public;
        try_files $uri =404;
        expires 1y;
    }

    # API Laravel
    location /api {
        client_max_body_size 25M;
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/Kalendarium/public/index.php;
        fastcgi_param REQUEST_URI $request_uri;
        fastcgi_param DOCUMENT_ROOT /var/www/Kalendarium/public;
        include fastcgi_params;
    }

    # Admin panel Filament
    location /filament-admin {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/Kalendarium/public/index.php;
        fastcgi_param REQUEST_URI $request_uri;
        fastcgi_param DOCUMENT_ROOT /var/www/Kalendarium/public;
        include fastcgi_params;
    }

    # Livewire (richiesto da Filament)
    location /livewire {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/Kalendarium/public/index.php;
        fastcgi_param REQUEST_URI $request_uri;
        fastcgi_param DOCUMENT_ROOT /var/www/Kalendarium/public;
        include fastcgi_params;
    }

    # SPA React — fallback su index.html per client-side routing
    location / {
        root /var/www/Kalendarium/frontend/dist;
        try_files $uri $uri/ /index.html;
    }
}
```

Attiva e rilascia il certificato:

```bash
sudo ln -s /etc/nginx/sites-available/kalendarium /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d kalendarium.it -d www.kalendarium.it
```

### 10. Systemd units

Sul VPS girano **tre** unit dedicate a Kalendarium.

#### 10a. Horizon (queue dashboard + worker principali)

Il repo fornisce `horizon.service.example`. Copialo e adatta l'utente se necessario:

```bash
sudo cp /var/www/Kalendarium/horizon.service.example /etc/systemd/system/horizon.service
# Il template di default usa User=www-data e WorkingDirectory=/var/www/kalendarium.
# Se il tuo setup gira come ubuntu o ha path diverso, modifica di conseguenza.
sudo nano /etc/systemd/system/horizon.service
```

#### 10b. Laravel Queue Worker dedicato agli audit

Gli audit hanno una queue separata per non saturare la coda principale. Crea `/etc/systemd/system/laravel-worker.service`:

```ini
[Unit]
Description=Laravel Queue Worker (audits)
After=network.target redis-server.service

[Service]
User=ubuntu
WorkingDirectory=/var/www/Kalendarium
ExecStart=/usr/bin/php artisan queue:work redis --queue=audits --sleep=3 --tries=3 --timeout=300
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

#### 10c. Microservizio Playwright

Crea `/etc/systemd/system/playwright-audit.service`:

```ini
[Unit]
Description=Playwright Audit Microservice — Kalendarium
After=network.target

[Service]
User=ubuntu
WorkingDirectory=/var/www/playwright-service
ExecStart=/usr/bin/node server.js
Environment=NODE_ENV=production
Environment=PORT=3099
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

#### Attivazione

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now horizon laravel-worker playwright-audit
sudo systemctl status horizon laravel-worker playwright-audit
```

### 11. Cron per lo scheduler

Gli scheduler Laravel in `routes/console.php` includono:

- `PublishScheduledPostsJob` — ogni minuto (pubblicazione post nella finestra ±2 min)
- `CollectSocialMetricsJob` — ogni 6 ore
- `trial:send-reminders` — tutti i giorni alle 09:00

Aggiungi la riga al crontab dell'utente `ubuntu`:

```bash
crontab -e
```

```cron
* * * * * cd /var/www/Kalendarium && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

### 12. Reverb (WebSocket) — opzionale

Il `.env` default ha `BROADCAST_CONNECTION=reverb`. Se vuoi il broadcasting real-time in produzione, aggiungi un quarto unit systemd con `php artisan reverb:start --host=127.0.0.1 --port=8080` e in Nginx un `location /app/` + `location /reverb/` con proxy WebSocket (`proxy_set_header Upgrade $http_upgrade`).

---

## Configurazione del sistema Audit

### Google PageSpeed Insights

1. Google Cloud Console → progetto **"calendar"** (o nuovo) → abilita "PageSpeed Insights API"
2. Credenziali → API key → restringi per API PageSpeed
3. Inserisci `GOOGLE_PAGESPEED_API_KEY=...` in `.env`

Senza chiave il servizio funziona comunque ma con rate limit molto basso.

### SSL Labs

Non richiede chiave. Rate limit pubblico: una analisi completa ogni ~2 minuti per host. Il sistema gestisce retry con backoff.

### Playwright e Chromium

Il microservizio usa Chromium installato via `npx playwright install chromium --with-deps`. La cache è in `/home/ubuntu/.cache/ms-playwright`. Se il servizio gira con utente diverso (`www-data`), Chromium va reinstallato per quell'utente oppure puntato con `PLAYWRIGHT_BROWSERS_PATH`.

### Vincoli deontologici per settori regolati

Il sistema rileva automaticamente il settore via Claude (`SectorDetectorService`) e mostra un modal di conferma (`SectorConfirmModal.jsx`). Per settori come psicologia, medicina, legale, finanza, il motore di raccomandazioni filtra suggerimenti non conformi (es. testimonianze pazienti, claim emotivi). Nessuna configurazione aggiuntiva — il comportamento è data-driven.

---

## Configurazione OAuth social

Per ciascuna piattaforma serve creare un'app developer e inserire le credenziali in `.env`:

- **Meta** (Facebook + Instagram) — https://developers.facebook.com/ — redirect URI `https://kalendarium.it/api/auth/facebook/callback` e `/instagram/callback`
- **LinkedIn** (Personal + Business) — https://www.linkedin.com/developers/ — due app separate, scope `w_member_social` / `r_organization_social,w_organization_social`
- **Google Business Profile** — Google Cloud Console, API "My Business Business Information" + "My Business Account Management" — redirect `/api/auth/google/callback`

Meta e LinkedIn richiedono **App Review** per l'accesso production ai permessi di posting: pianificare 1-4 settimane.

---

## Comandi utili

```bash
# Stato generale
sudo systemctl status horizon laravel-worker playwright-audit nginx php8.4-fpm

# Log applicazione
tail -f /var/www/Kalendarium/storage/logs/laravel.log

# Log microservizio Playwright
sudo journalctl -u playwright-audit -f

# Horizon dashboard (accesso ristretto via gate)
# https://kalendarium.it/horizon

# Clear/rebuild cache dopo modifiche a config o routes
cd /var/www/Kalendarium
php artisan config:clear && php artisan config:cache
php artisan route:clear && php artisan route:cache
php artisan view:clear && php artisan view:cache

# Tinker per debug interattivo
php artisan tinker

# Test suite (richiede require-dev)
composer install
php artisan test
```

---

## Deploy di nuovo codice

```bash
cd /var/www/Kalendarium
git fetch origin
git pull origin production

composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:upgrade

cd frontend && npm ci && npm run build && cd ..

sudo systemctl restart horizon laravel-worker php8.4-fpm
# playwright-audit va riavviato solo se cambia /var/www/playwright-service/
```

---

## Troubleshooting

### Audit restituisce falsi positivi su siti React/SPA

Il DOM statico di un sito SPA non contiene H1, cookie banner e molti elementi fino a che il JS non si esegue. Il `PlaywrightClient` estrae la versione renderizzata e restituisce `$playwrightData`; `SeoGeoAnalyzer` e `GdprAnalyzer` devono usarla come fonte autoritativa quando presente (fallback al fetch statico solo se Playwright è down o disabilitato).

### Porta Playwright mismatch

Verifica che `.env` abbia `PLAYWRIGHT_SERVICE_URL=http://127.0.0.1:3099` e che il servizio sia in ascolto su quella porta:

```bash
sudo ss -tlnp | grep 3099
curl http://127.0.0.1:3099/health
```

Se cambi la porta, aggiorna **sia** `.env` **sia** l'unit systemd (`Environment=PORT=...`).

### Permessi su Chromium / cache Playwright

Se il log dice "Failed to launch browser" o errori di permessi su `.cache/ms-playwright`, l'utente del service non vede i browser installati. Soluzioni:

```bash
# Reinstall per l'utente corretto del service
sudo -u ubuntu npx --prefix /var/www/playwright-service playwright install chromium --with-deps

# Oppure cache condivisa
sudo mkdir -p /var/www/.cache/ms-playwright
sudo chown -R ubuntu:ubuntu /var/www/.cache
# poi nell'unit: Environment=PLAYWRIGHT_BROWSERS_PATH=/var/www/.cache/ms-playwright
```

### API key tenant mancante (`MissingBrandApiKeyException`)

I tenant sui piani Small/Standard/Pro usano le chiavi AI di sistema (Noscite). Il piano Unlimited richiede che il tenant configuri le proprie chiavi via `/settings/api-keys`. Se un tenant Unlimited riceve l'eccezione, non ha ancora inserito le chiavi. Per il tenant di sistema (identificato da `SYSTEM_TENANT_ID`) il fallback è automatico.

### Test suite non verde

Il middleware di rate limiting va disattivato in `TestCase.php` — altrimenti i test paralleli si ostacolano. La suite deve girare verde (173/173) prima di ogni deploy importante.

### Brevo SMTP non invia

`MAIL_USERNAME` è la login SMTP Brevo (non l'email). `MAIL_PASSWORD` è l'SMTP key generata in Brevo → SMTP & API → SMTP → "Your SMTP key".

---

## Backup

Giornaliero minimo (cron su VPS o job esterno):

```bash
# PostgreSQL
pg_dump -h 127.0.0.1 -U noscite noscite_calendar | gzip > /backup/db-$(date +%F).sql.gz

# Storage uploads
tar -czf /backup/storage-$(date +%F).tar.gz /var/www/Kalendarium/storage/app/

# .env (cifrato)
gpg -c --output /backup/env-$(date +%F).env.gpg /var/www/Kalendarium/.env
```

Retention consigliata: 14 giorni locali + sync settimanale su storage esterno (S3/Backblaze).

---

## Monitoring

- **Sentry** — error tracking via `SENTRY_LARAVEL_DSN`. Dashboard su sentry.io per exception tracking e performance.
- **Horizon** — `/horizon` per lo stato della queue (accesso protetto via gate in `HorizonServiceProvider`).
- **Uptime** — check esterno su `https://kalendarium.it/api/health` (endpoint pubblico che ritorna `{"status":"healthy"}`).
- **Log Laravel** — canale `daily`, rotazione automatica in `storage/logs/`.

---

## Credenziali e accessi

Tutte le credenziali (DB password, API keys, OAuth secrets, Brevo, Sentry DSN) vivono **esclusivamente** in `/var/www/Kalendarium/.env`. Non committarle mai. Il file è in `.gitignore` insieme a `.env.backup`, `.env.production`.

Per la rotazione di una chiave: modifica `.env` → `php artisan config:cache` → `sudo systemctl restart horizon laravel-worker php8.4-fpm`.

---

## Licenza

Proprietario — **Noscite SRLS**, Milano. Tutti i diritti riservati.
