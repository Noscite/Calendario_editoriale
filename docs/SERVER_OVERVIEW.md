# Server Overview — VPS Noscite

Documentazione completa di tutti i siti, servizi e procedure operative sul VPS di produzione.
Ultimo aggiornamento: **2026-04-26**

---

## 🖥️ Specifiche VPS

| Campo            | Valore                              |
|------------------|-------------------------------------|
| Provider         | OVH                                 |
| OS               | Ubuntu 24.04 LTS                    |
| IP pubblico      | **91.134.242.201**                  |
| CPU              | (shared VPS)                        |
| RAM              | 7.6 GB                              |
| Disco            | 72 GB — usati ~15 GB (21%)          |
| User operativo   | `ubuntu`                            |
| Web user         | `www-data`                          |

### Stack comune

| Componente  | Versione       |
|-------------|----------------|
| PHP         | 8.4.5          |
| Node.js     | 22.22.2        |
| Python      | 3.13.3         |
| PostgreSQL  | 16             |
| Redis       | (latest apt)   |
| Nginx       | (latest apt)   |
| Composer    | 2.9.5          |
| npm         | 10.9.7         |

---

## 🌐 Siti in produzione

| Dominio                  | Progetto        | Path                        | Stack                    |
|--------------------------|-----------------|-----------------------------|--------------------------|
| `kalendarium.it`         | Kalendarium     | `/var/www/Kalendarium/`     | Laravel 12 + React SPA   |
| `kanon.noscite.it`       | KÁNON           | `/var/www/KANON/`           | FastAPI Python + React   |
| `pnl.noscite.it`         | PredictivePnL   | `/var/www/predictivepnl/`   | Next.js 16 + TypeScript  |
| *(interno)*              | Playwright Svc  | `/var/www/playwright-service/` | Node.js + Express     |

---

## 📦 Progetto 1 — Kalendarium (`kalendarium.it`)

### Descrizione
SaaS italiano per PMI: genera calendari editoriali AI-powered per i social media.
Il frontend è una React SPA; il backend è Laravel 12 che espone API REST JSON.

### Architettura

```
kalendarium.it
├── Frontend   React SPA  → /var/www/Kalendarium/frontend/dist/  (Nginx static)
├── /api/*     Laravel    → PHP-FPM unix socket (php8.4-fpm.sock)
├── /filament-admin/*  Filament admin panel → PHP-FPM
└── /livewire/*        Livewire (usato da Filament) → PHP-FPM
```

### Database
- **PostgreSQL**: `noscite_calendar` — owner `noscite` — ~12 MB
- **Redis**: cache, sessioni, code queue

### Funzionalità implementate

#### Autenticazione & Utenti
- Login/logout con Laravel Sanctum (token API)
- Registrazione via invito (token email)
- Profilo utente, cambio password
- SSO Microsoft Azure AD (`/api/auth/azure/`)
- Ruoli: `superuser`, `owner`, `admin`, `editor`, `viewer`

#### Subscription & Trial
- Trial 14 giorni, 1 calendario gratuito, 30.000 token AI
- State machine: `trial → pending_payment → active → expired / cancelled`
- Attivazione manuale via Filament admin (bonifico bancario)
- Middleware `subscription.active`: blocca l'API con 402 se non attiva
- Email transazionali: benvenuto, trial scaduto, abbonamento attivato, scadenza imminente
- Cron `subscriptions:update-states` ogni notte alle 02:00

#### Brand Management
- CRUD brand per organizzazione
- Documenti brand (upload PDF/DOCX, elaborazione background con AI)
- Brand API Keys criptate per accesso Public API
- Audit digitale brand (SEO, performance, accessibility, GEO score)

#### Generazione AI Calendario
- Generazione buyer personas via Claude AI
- Generazione calendario editoriale mensile (post per Instagram, LinkedIn, Facebook, Google)
- Rigenerazione singoli post
- Generazione immagini AI per post
- Export Excel e PDF

#### Social Media
- Connessione account: Facebook Pages, Instagram Business, LinkedIn, Google Business
- Pubblicazione automatica schedulata (cron ogni minuto)
- Social stats (metriche engagement ogni 6 ore)
- Carousel generation

#### Prospect Audit (White-label)
- Audit digitale su URL qualsiasi senza autenticazione
- Link pubblico condivisibile con token
- Analisi SEO, performance (Playwright + Chromium), accessibility (axe-core)
- Download PDF report

#### Notifiche
- Sistema notifiche in-app
- Conteggio unread, mark read/all

#### Admin Panel (Filament)
- URL: `https://kalendarium.it/filament-admin`
- Gestione organizzazioni, utenti, inviti
- Gestione subscription: attiva pagamento, estendi trial, cancella, marca pending
- Piani e usage log
- Brand audit admin view

#### Public API v1
- Autenticazione via `X-API-Key` con scopes (read / write / publish)
- Gestione brand, projects, posts via API
- Generazione calendario via API

### Servizi systemd

| Servizio                      | Descrizione                                      |
|-------------------------------|--------------------------------------------------|
| `kalendarium-horizon.service` | Laravel Horizon — gestisce tutte le code Redis   |
| `kalendarium-worker.service`  | Worker supplementare — code `generazione,default`|
| `laravel-worker.service`      | Worker per code `audits,default`                 |
| `playwright-audit.service`    | Microservice Playwright su porta `3099`          |

```bash
# Comandi utili
sudo systemctl status kalendarium-horizon
sudo systemctl restart kalendarium-horizon
sudo journalctl -u kalendarium-horizon -n 100 --no-pager

# Horizon dashboard web
https://kalendarium.it/filament-admin/horizon  # (se configurato)
```

### Cron (root)
```
* * * * *   cd /var/www/Kalendarium && php artisan schedule:run
0 */6 * * * /usr/local/bin/kalendarium-backup.sh
```

### Scheduler jobs (da `routes/console.php`)
| Job                             | Frequenza     |
|---------------------------------|---------------|
| `PublishScheduledPostsJob`      | ogni minuto   |
| `CollectSocialMetricsJob`       | ogni 6 ore    |
| `trial:send-reminders`          | 09:00 daily   |
| `subscriptions:update-states`   | 02:00 daily   |

### File di configurazione chiave
```
/var/www/Kalendarium/.env                          # variabili d'ambiente
/var/www/Kalendarium/config/trial.php              # budget trial
/var/www/Kalendarium/config/billing.php            # dati fatturazione bonifico
/var/www/Kalendarium/config/horizon.php            # code Horizon
/etc/nginx/sites-enabled/kalendarium               # Nginx config
/etc/systemd/system/kalendarium-horizon.service
/etc/systemd/system/kalendarium-worker.service
/etc/systemd/system/laravel-worker.service
```

### Deployment (aggiornamento codice)
```bash
cd /var/www/Kalendarium
git pull origin production
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
cd frontend && npm ci && npm run build && cd ..
sudo systemctl restart php8.4-fpm
sudo systemctl restart kalendarium-horizon
```

---

## 📦 Progetto 2 — KÁNON (`kanon.noscite.it`)

### Descrizione
SaaS B2B per studi commercialisti. "Decision Control Layer": gestione aziende clienti,
decisioni fiscali/societarie, intake documentale AI, KA.RA. AI assistant, scadenze, circolari.

### Architettura
```
kanon.noscite.it
├── Frontend   React SPA  → /var/www/KANON/frontend/dist/  (Nginx static)
└── /api/v1/*  FastAPI    → Nginx proxy → uvicorn su 127.0.0.1:8000
```

### Database
- **PostgreSQL**: `kanon` — owner `kanon` — ~10 MB
- **Storage**: `/var/www/KANON/uploads/` — file caricati dai clienti

### Funzionalità implementate

#### Auth & Utenti
- JWT authentication (access + refresh token)
- Gestione staff dello studio

#### Aziende Clienti
- CRUD aziende (`/api/v1/companies`)
- Dashboard per azienda (`/api/v1/dashboard`)

#### Intake Documentale AI
- Upload documenti aziendali
- Estrazione automatica dati con Claude AI
- Pipeline intake (`/api/v1/intake`)

#### Decisioni
- Registro decisioni societarie/fiscali (`/api/v1/decisions`)
- Evidence allegata alle decisioni (`/api/v1/evidences`)
- Export decisioni (`/api/v1/export`)

#### KA.RA. AI Assistant
- Chat AI contestuale per il commercialista (`/api/v1/kara`)
- Knowledge base aggiornata settimanalmente

#### Gestione Operativa
- PEC management (`/api/v1/pec`)
- Circolari fiscali (`/api/v1/circolari`)
- Scadenze (`/api/v1/scadenze`)
- Eventi calendario (`/api/v1/events`)
- Statistiche studio (`/api/v1/stats`)
- Analisi (`/api/v1/analyze`)

### Servizio systemd
```
/etc/systemd/system/kanon-api.service
User: ubuntu
WorkingDirectory: /var/www/KANON/backend
ExecStart: uvicorn app.main:app --host 127.0.0.1 --port 8000 --workers 2
```

```bash
sudo systemctl status kanon-api
sudo systemctl restart kanon-api
sudo journalctl -u kanon-api -n 100
```

### Cron (root)
```
0 3 * * 0  cd /var/www/KANON/backend && python -m scripts.update_kb >> /var/log/kanon-kb-update.log
```
Aggiornamento knowledge base ogni domenica alle 3:00.

### File di configurazione chiave
```
/var/www/KANON/.env                         # variabili d'ambiente
/etc/nginx/sites-enabled/kanon              # Nginx config
/etc/systemd/system/kanon-api.service
```

### Deployment
```bash
cd /var/www/KANON/backend
git pull
source venv/bin/activate
pip install -r requirements.txt
alembic upgrade head
sudo systemctl restart kanon-api
# Frontend:
cd /var/www/KANON/frontend
npm ci && npm run build
```

---

## 📦 Progetto 3 — PredictivePnL (`pnl.noscite.it`)

### Descrizione
Tool AI per modellazione P&L (Profit & Loss) di startup/PMI. L'utente carica documenti
(business plan, pitch deck), Claude estrae parametri, genera un simulatore interattivo con
sliders e calcola proiezioni finanziarie.

### Architettura
```
pnl.noscite.it
└── Next.js 16 fullstack  → PM2 → porta 3001
    ├── Frontend React/TypeScript con Tailwind CSS
    └── API routes Next.js (/app/api/*)
```

### Database
- **PostgreSQL**: `predictivepnl` — owner `predictivepnl` — ~8.6 MB
- ORM: Prisma 6

### Flusso principale
1. Utente crea un progetto (nome, settore, tipo business model)
2. Carica documenti PDF/DOCX
3. Claude fa research sul settore (`/api/research`)
4. Claude estrae parametri P&L dai documenti (`/api/extract`)
5. Utente valida parametri estratti (`/api/validation`)
6. Claude genera simulatore JSX interattivo (`/api/compute`)
7. Dashboard con sliders: revenue, costs, growth, churn, infrastructure
8. Report scaricabile (`/api/report`)

### Funzionalità (route Next.js)
| Route                             | Funzione                                      |
|-----------------------------------|-----------------------------------------------|
| `/api/projects`                   | CRUD progetti                                 |
| `/api/intake`                     | Upload e parsing documenti                    |
| `/api/research`                   | Research AI sul settore                       |
| `/api/extract`                    | Estrazione parametri da documenti             |
| `/api/validation/[sessionId]`     | Validazione umana parametri                   |
| `/api/compute`                    | Generazione simulatore JSX + calcolo P&L      |
| `/api/simulator/[sessionId]`      | Dati simulatore per rendering                 |
| `/api/refine`                     | Raffinamento parametri                        |
| `/api/report/[sessionId]`         | Generazione report                            |
| `/api/session/[sessionId]`        | Stato sessione di modellazione                |

### Servizio (PM2)
```bash
# Gestito da PM2 come user ubuntu
pm2 list                    # stato
pm2 restart predictivepnl   # restart
pm2 logs predictivepnl      # log live
pm2 save                    # salva config per auto-start
```

Il servizio `pm2-ubuntu.service` gestisce il resurrect di PM2 al boot.

### File di configurazione chiave
```
/var/www/predictivepnl/.env.local          # variabili (DATABASE_URL, ANTHROPIC_API_KEY)
/var/www/predictivepnl/prisma/schema.prisma
/etc/nginx/sites-enabled/predictivepnl
/etc/systemd/system/pm2-ubuntu.service
```

### Deployment
```bash
cd /var/www/predictivepnl
git pull
npm ci
npx prisma migrate deploy
npm run build
pm2 restart predictivepnl
```

---

## 📦 Servizio interno — Playwright Audit (`porta 3099`)

### Descrizione
Microservice Node.js usato esclusivamente da Kalendarium per l'audit digitale brand.
Non è esposto pubblicamente da Nginx; Kalendarium lo chiama internamente su `127.0.0.1:3099`.

### Funzione
- Riceve `POST /analyze` con URL
- Lancia Chromium headless via Playwright
- Restituisce: screenshot desktop/mobile, report accessibilità (axe-core), network timing, meta tag, contrast analysis

### Servizio systemd
```
/etc/systemd/system/playwright-audit.service
User: www-data
Port: 3099
MemoryMax: 1G   (Chromium è memory-hungry)
CPUQuota: 80%
```

```bash
sudo systemctl status playwright-audit
sudo systemctl restart playwright-audit
sudo journalctl -u playwright-audit -n 50
```

---

## 🗄️ Database PostgreSQL — riepilogo

| Database              | Owner         | Usato da        | Dimensione |
|-----------------------|---------------|-----------------|------------|
| `noscite_calendar`    | `noscite`     | Kalendarium     | ~12 MB     |
| `noscite_calendar_test` | `noscite`   | Test suite      | ~11 MB     |
| `kanon`               | `kanon`       | KÁNON           | ~10 MB     |
| `predictivepnl`       | `predictivepnl` | PredictivePnL | ~8.6 MB    |

### Backup
- Solo `noscite_calendar` ha backup automatico ogni 6 ore → `/var/backups/kalendarium/`
- **`kanon` e `predictivepnl` NON hanno backup automatici**

---

## 🔒 SSL / TLS

Tutti i certificati sono gestiti da **Certbot + Let's Encrypt** con rinnovo automatico.

| Dominio               | Scadenza        |
|-----------------------|-----------------|
| `kalendarium.it`      | 2026-07-07      |
| `www.kalendarium.it`  | 2026-07-08      |
| `kanon.noscite.it`    | 2026-06-17      |
| `pnl.noscite.it`      | 2026-06-13      |

```bash
# Verifica rinnovo automatico
sudo certbot renew --dry-run

# Rinnovo manuale (se necessario)
sudo certbot renew
sudo systemctl reload nginx
```

---

## 📋 Crontab root — riepilogo completo

```cron
# Laravel scheduler (Kalendarium)
* * * * * cd /var/www/Kalendarium && php artisan schedule:run >> /dev/null 2>&1

# Backup PostgreSQL Kalendarium (ogni 6 ore)
0 */6 * * * /usr/local/bin/kalendarium-backup.sh >> /var/log/kalendarium-backup.log 2>&1

# KÁNON — aggiornamento KB settimanale (domenica 3:00)
0 3 * * 0 cd /var/www/KANON/backend && /var/www/KANON/backend/venv/bin/python -m scripts.update_kb >> /var/log/kanon-kb-update.log 2>&1
```

---

## 🚀 Migrazione a nuovo VPS

Questa sezione copre il trasferimento completo dell'intero VPS su una nuova macchina
mantenendo zero downtime o downtime minimo.

### Prima di iniziare

**Prerequisiti nuovo VPS:**
- Ubuntu 24.04 LTS
- Stesso provider o almeno 7.6 GB RAM, 72 GB storage
- Accesso SSH con chiave

**Tempo stimato:** 2-4 ore (dipende dalla banda tra VPS)

**Finestra suggerita:** notte tra domenica e lunedì (traffic minimo)

---

### FASE 1 — Preparazione nuovo VPS

```bash
# SSH sul nuovo VPS
ssh ubuntu@<NUOVO_IP>

# Aggiornamento sistema
sudo apt update && sudo apt upgrade -y

# Installazione dipendenze
sudo apt install -y \
  nginx \
  postgresql-16 \
  redis-server \
  php8.4-fpm php8.4-cli php8.4-pgsql php8.4-redis \
  php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip \
  php8.4-intl php8.4-gd php8.4-bcmath \
  nodejs npm \
  python3 python3-pip python3-venv \
  git curl certbot python3-certbot-nginx \
  shellcheck

# Installa Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Installa PM2 globale
sudo npm install -g pm2

# Crea utente web (se non esiste)
sudo usermod -aG www-data ubuntu
```

---

### FASE 2 — Trasferimento codice

Eseguire dal **vecchio VPS** verso il nuovo, oppure ri-clonare da Git.

#### Opzione A — rsync diretto VPS→VPS (consigliato)

```bash
# Dal VECCHIO VPS — trasferisce tutto tranne vendor/node_modules
rsync -avz --progress \
  --exclude='vendor/' \
  --exclude='node_modules/' \
  --exclude='frontend/node_modules/' \
  --exclude='.git/' \
  --exclude='storage/logs/*.log' \
  /var/www/Kalendarium/ \
  ubuntu@<NUOVO_IP>:/var/www/Kalendarium/

rsync -avz --progress \
  --exclude='backend/venv/' \
  --exclude='frontend/node_modules/' \
  /var/www/KANON/ \
  ubuntu@<NUOVO_IP>:/var/www/KANON/

rsync -avz --progress \
  --exclude='node_modules/' \
  --exclude='.next/' \
  /var/www/predictivepnl/ \
  ubuntu@<NUOVO_IP>:/var/www/predictivepnl/

rsync -avz --progress \
  --exclude='node_modules/' \
  /var/www/playwright-service/ \
  ubuntu@<NUOVO_IP>:/var/www/playwright-service/
```

#### Opzione B — clone da Git + copia .env

```bash
# Sul nuovo VPS
sudo git clone git@github.com:Noscitedevteam/Calendario_editoriale.git /var/www/Kalendarium
# Copia .env dal vecchio VPS:
scp ubuntu@<VECCHIO_IP>:/var/www/Kalendarium/.env /var/www/Kalendarium/.env
```

---

### FASE 3 — Installazione dipendenze

```bash
# === KALENDARIUM ===
cd /var/www/Kalendarium
sudo -u www-data composer install --no-dev --optimize-autoloader
cd frontend && npm ci && npm run build && cd ..

# === KÁNON ===
cd /var/www/KANON/backend
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
cd /var/www/KANON/frontend
npm ci && npm run build

# === PREDICTIVEPNL ===
cd /var/www/predictivepnl
npm ci
npm run build

# === PLAYWRIGHT SERVICE ===
cd /var/www/playwright-service
npm ci
npx playwright install chromium --with-deps
```

---

### FASE 4 — Database PostgreSQL

#### 4a. Dump dal vecchio VPS

```bash
# Sul VECCHIO VPS — dump di tutti i database
sudo -u postgres pg_dump noscite_calendar | gzip > /tmp/noscite_calendar.sql.gz
sudo -u postgres pg_dump kanon | gzip > /tmp/kanon.sql.gz
sudo -u postgres pg_dump predictivepnl | gzip > /tmp/predictivepnl.sql.gz

# Trasferisci sul nuovo VPS
scp /tmp/*.sql.gz ubuntu@<NUOVO_IP>:/tmp/
```

#### 4b. Restore sul nuovo VPS

```bash
# Sul NUOVO VPS — crea utenti e database

sudo -u postgres psql <<'SQL'
CREATE USER noscite WITH PASSWORD '<password_dal_.env>';
CREATE DATABASE noscite_calendar OWNER noscite;
CREATE USER kanon WITH PASSWORD '<password_dal_kanon_.env>';
CREATE DATABASE kanon OWNER kanon;
GRANT ALL PRIVILEGES ON DATABASE kanon TO kanon;
CREATE USER predictivepnl WITH PASSWORD '<password>';
CREATE DATABASE predictivepnl OWNER predictivepnl;
GRANT ALL PRIVILEGES ON DATABASE predictivepnl TO predictivepnl;
SQL

# Restore
gunzip -c /tmp/noscite_calendar.sql.gz | sudo -u postgres psql noscite_calendar
gunzip -c /tmp/kanon.sql.gz | sudo -u postgres psql kanon
gunzip -c /tmp/predictivepnl.sql.gz | sudo -u postgres psql predictivepnl

# Verifica
sudo -u postgres psql noscite_calendar -c "SELECT COUNT(*) FROM organizations;"
sudo -u postgres psql kanon -c "SELECT COUNT(*) FROM companies;"
```

---

### FASE 5 — Configurazione Nginx

```bash
# Copia configurazioni Nginx dal vecchio VPS
scp ubuntu@<VECCHIO_IP>:/etc/nginx/sites-enabled/* /tmp/nginx-configs/
sudo cp /tmp/nginx-configs/* /etc/nginx/sites-enabled/

# Oppure, ricrea manualmente da /etc/nginx/sites-enabled/ (vedi docs)

# Testa configurazione
sudo nginx -t

# Riavvia Nginx (senza SSL per ora)
sudo systemctl restart nginx
```

---

### FASE 6 — Permessi e storage

```bash
# Kalendarium
sudo chown -R www-data:www-data /var/www/Kalendarium/storage
sudo chown -R www-data:www-data /var/www/Kalendarium/bootstrap/cache
sudo chmod -R 775 /var/www/Kalendarium/storage
sudo chmod -R 775 /var/www/Kalendarium/bootstrap/cache
cd /var/www/Kalendarium && php artisan storage:link

# KÁNON uploads
sudo chown -R ubuntu:ubuntu /var/www/KANON/uploads
sudo chmod -R 755 /var/www/KANON/uploads

# Backup directory
sudo mkdir -p /var/backups/kalendarium
sudo chmod 700 /var/backups/kalendarium
sudo chown root:root /var/backups/kalendarium
```

---

### FASE 7 — Servizi systemd

```bash
# Copia i service files dal vecchio VPS
scp ubuntu@<VECCHIO_IP>:/etc/systemd/system/kalendarium-horizon.service /tmp/
scp ubuntu@<VECCHIO_IP>:/etc/systemd/system/kalendarium-worker.service /tmp/
scp ubuntu@<VECCHIO_IP>:/etc/systemd/system/laravel-worker.service /tmp/
scp ubuntu@<VECCHIO_IP>:/etc/systemd/system/kanon-api.service /tmp/
scp ubuntu@<VECCHIO_IP>:/etc/systemd/system/playwright-audit.service /tmp/
scp ubuntu@<VECCHIO_IP>:/etc/systemd/system/pm2-ubuntu.service /tmp/

sudo cp /tmp/*.service /etc/systemd/system/
sudo systemctl daemon-reload

# Abilita e avvia tutti i servizi
sudo systemctl enable --now kalendarium-horizon
sudo systemctl enable --now kalendarium-worker
sudo systemctl enable --now laravel-worker
sudo systemctl enable --now kanon-api
sudo systemctl enable --now playwright-audit
sudo systemctl enable --now pm2-ubuntu

# PM2 per predictivepnl
cd /var/www/predictivepnl
pm2 start npm --name predictivepnl -- start -- --port 3001
pm2 save
```

---

### FASE 8 — Artisan (Kalendarium)

```bash
cd /var/www/Kalendarium

# Rigenera app key (solo se .env è nuovo/vuoto)
# php artisan key:generate   ← NON eseguire se .env è stato copiato dal vecchio VPS

# Run migrations (verifica che siano già allineate con il dump)
php artisan migrate --force

# Cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

---

### FASE 9 — Crontab

```bash
sudo crontab -e
# Incolla:
# * * * * * cd /var/www/Kalendarium && php artisan schedule:run >> /dev/null 2>&1
# 0 */6 * * * /usr/local/bin/kalendarium-backup.sh >> /var/log/kalendarium-backup.log 2>&1
# 0 3 * * 0 cd /var/www/KANON/backend && /var/www/KANON/backend/venv/bin/python -m scripts.update_kb >> /var/log/kanon-kb-update.log 2>&1

# Installa il sistema di backup
sudo bash /var/www/Kalendarium/scripts/infrastructure/install-backup.sh
```

---

### FASE 10 — SSL con Certbot

> ⚠️ Questa fase va eseguita DOPO aver aggiornato i DNS (vedi FASE 11).

```bash
# Ottieni nuovi certificati
sudo certbot --nginx -d kalendarium.it -d www.kalendarium.it
sudo certbot --nginx -d kanon.noscite.it
sudo certbot --nginx -d pnl.noscite.it

# Verifica rinnovo automatico
sudo certbot renew --dry-run
```

---

### FASE 11 — Cambio DNS e cutover

**Ordine consigliato per minimizzare il downtime:**

1. **Abbassa il TTL** dei record DNS a 300 secondi almeno 24 ore prima
2. Esegui le fasi 1-9 sul nuovo VPS
3. **Verifica funzionamento** sul nuovo VPS usando il file `/etc/hosts` locale:
   ```
   <NUOVO_IP>  kalendarium.it www.kalendarium.it
   <NUOVO_IP>  kanon.noscite.it pnl.noscite.it
   ```
4. **Aggiorna i record A** del DNS:
   - `kalendarium.it` → `<NUOVO_IP>`
   - `www.kalendarium.it` → `<NUOVO_IP>`
   - `kanon.noscite.it` → `<NUOVO_IP>`
   - `pnl.noscite.it` → `<NUOVO_IP>`
5. Attendi propagazione DNS (max 5-10 minuti con TTL 300)
6. Ottieni SSL con Certbot (FASE 10)
7. **Test finale:**
   ```bash
   curl -I https://kalendarium.it/api/health
   curl -I https://kanon.noscite.it/health
   curl -I https://pnl.noscite.it/
   ```
8. Tieni il vecchio VPS attivo per 48 ore prima di spegnerlo

---

### Checklist migrazione — quick reference

```
[ ] Nuovo VPS provisioned, SSH ok
[ ] apt packages installati (nginx, postgres, redis, php, node, python, git)
[ ] Composer, PM2 installati
[ ] Codice rsync/git clonato (Kalendarium, KANON, predictivepnl, playwright-service)
[ ] .env copiati su tutti i progetti
[ ] composer install, npm ci, npm run build (tutti i progetti)
[ ] PostgreSQL: utenti e database creati
[ ] pg_dump dal vecchio VPS → restore sul nuovo
[ ] php artisan migrate --force (Kalendarium)
[ ] Alembic migrate (KANON)
[ ] Prisma migrate deploy (predictivepnl)
[ ] Permessi storage e uploads
[ ] Nginx configs copiati e testati (nginx -t)
[ ] Service files systemd copiati, daemon-reload, enabled, started
[ ] PM2 configurato per predictivepnl, pm2 save
[ ] Crontab root configurato
[ ] install-backup.sh eseguito
[ ] Backup di test eseguito con successo
[ ] Playwright: chromium installato (npx playwright install chromium --with-deps)
[ ] Test manuale su nuovo IP via /etc/hosts locali
[ ] DNS TTL abbassato a 300s (24h prima)
[ ] DNS records aggiornati al nuovo IP
[ ] Certbot SSL su tutti i domini
[ ] curl health check tutti i siti
[ ] Vecchio VPS tenuto acceso 48h poi spento
```

---

### Troubleshooting post-migrazione

**Errore 502 Bad Gateway:**
```bash
sudo systemctl status php8.4-fpm     # PHP-FPM down?
sudo systemctl status kanon-api      # FastAPI down?
pm2 list                             # PM2/Node down?
sudo nginx -t && sudo systemctl reload nginx
```

**Laravel: errori permessi storage:**
```bash
sudo chown -R www-data:www-data /var/www/Kalendarium/storage /var/www/Kalendarium/bootstrap/cache
sudo chmod -R 775 /var/www/Kalendarium/storage
```

**PostgreSQL: authentication failed:**
```bash
sudo -u postgres psql -c "\du"           # verifica utenti
sudo -u postgres psql noscite_calendar   # testa connessione diretta
# Controlla pg_hba.conf se necessario
cat /etc/postgresql/16/main/pg_hba.conf
```

**Horizon non processa job:**
```bash
sudo systemctl restart kalendarium-horizon
# Verifica Redis
redis-cli ping
redis-cli info keyspace
```

**Certbot fallisce:**
```bash
# Verifica che il DNS sia già propagato
dig +short kalendarium.it
# Deve restituire il NUOVO IP prima di lanciare certbot
```
