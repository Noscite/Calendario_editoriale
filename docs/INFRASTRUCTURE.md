# Kalendarium — Infrastructure Guide

## 🖥️ Server

| Campo           | Valore                                  |
|-----------------|-----------------------------------------|
| OS              | Ubuntu 24.04 LTS                        |
| IP pubblico     | 91.134.242.201                          |
| Project root    | `/var/www/Kalendarium/`                 |
| Web user        | `www-data`                              |
| PHP             | 8.4                                     |
| PostgreSQL      | 16 — database `noscite_calendar`        |
| Redis           | localhost:6379                          |

---

## ⚙️ Horizon (Queue Worker)

Horizon gestisce le code Redis (generazione AI, invio email, metriche social).

**Configurazione:** [`/etc/systemd/system/horizon.service`](../horizon.service.example)

```bash
# Status
sudo systemctl status horizon

# Restart (dopo deploy)
sudo systemctl restart horizon

# Stop / Start
sudo systemctl stop horizon
sudo systemctl start horizon

# Log (ultimi 100 righe)
sudo journalctl -u horizon -n 100 --no-pager

# Dashboard web
# http://91.134.242.201/admin/horizon   (accessibile solo da IP autorizzato)
```

**Code configurate:**

| Queue     | Uso                                  |
|-----------|--------------------------------------|
| `default` | Job generici                         |
| `emails`  | Invio email transazionali            |
| `heavy`   | Generazione AI, elaborazione PDF     |

---

## 🕐 Scheduler (Cron)

Il cron di root esegue il Laravel scheduler ogni minuto, che a sua volta dispatcha i job pianificati.

**Crontab root:**
```
* * * * * cd /var/www/Kalendarium && php artisan schedule:run >> /dev/null 2>&1
0 */6 * * * /usr/local/bin/kalendarium-backup.sh >> /var/log/kalendarium-backup.log 2>&1
```

**Job pianificati (da `routes/console.php`):**

| Job                          | Frequenza    | Descrizione                             |
|------------------------------|--------------|-----------------------------------------|
| `PublishScheduledPostsJob`   | Ogni minuto  | Pubblica post schedulati (finestra ±2m) |
| `CollectSocialMetricsJob`    | Ogni 6 ore   | Raccoglie metriche Facebook/LinkedIn    |
| `trial:send-reminders`       | 09:00 daily  | Email reminder trial in scadenza        |
| `subscriptions:update-states`| 02:00 daily  | State machine subscription (trial→expired, etc.) |

```bash
# Visualizza crontab root
sudo crontab -l

# Esegui scheduler manualmente (per debug)
cd /var/www/Kalendarium && php artisan schedule:run --verbose
```

---

## 💾 Backup PostgreSQL

### Configurazione

| Campo         | Valore                                      |
|---------------|---------------------------------------------|
| Database      | `noscite_calendar`                          |
| Schedule      | Ogni 6 ore (00:00, 06:00, 12:00, 18:00)    |
| Storage       | `/var/backups/kalendarium/` (locale, 700)   |
| Formato       | `noscite_calendar_YYYYMMDD_HHMMSS.sql.gz`  |
| Retention     | 14 giorni                                   |
| Off-site      | ⚠️ **NON configurato** — vedi sezione sotto |
| Log           | `/var/log/kalendarium-backup.log`           |
| Failure log   | `/var/log/kalendarium-backup.failures`      |

### Script installati

```bash
/usr/local/bin/kalendarium-backup.sh        # Backup
/usr/local/bin/kalendarium-restore-test.sh  # Test ripristino
/etc/logrotate.d/kalendarium-backup         # Logrotate
```

### Comandi utili

```bash
# Backup manuale immediato
sudo /usr/local/bin/kalendarium-backup.sh

# Test ripristino (non tocca mai il DB di produzione)
sudo /usr/local/bin/kalendarium-restore-test.sh

# Lista backup disponibili
ls -lh /var/backups/kalendarium/

# Ultimi 50 log
tail -50 /var/log/kalendarium-backup.log

# Fallimenti consecutivi correnti
cat /var/log/kalendarium-backup.failures

# Crontab root (verifica schedule)
sudo crontab -l
```

### Installazione / Disinstallazione

```bash
# Installa
sudo bash /var/www/Kalendarium/scripts/infrastructure/install-backup.sh

# Disinstalla (i backup esistenti restano)
sudo bash /var/www/Kalendarium/scripts/infrastructure/uninstall-backup.sh
```

---

## ⚠️ Off-site Backup Status

> **WARNING — RISCHIO PERDITA TOTALE DATI**
>
> Il backup off-site **NON è configurato**.
>
> In caso di failure catastrofica del VPS (guasto hardware, provider failure, accidente),
> **tutti i dati saranno irrecuperabili**.
>
> Stefano ha confermato consapevolmente questo trade-off il **2026-04-26**,
> in attesa di configurare la soluzione off-site.
>
> **Azione richiesta non appena ci sono i primi clienti paganti.**

---

## 🚨 Disaster Recovery

### Caso 1 — VPS ok, DB corrotto o dati cancellati per errore

Il VPS risponde ma il database è in stato inconsistente o i dati sono stati cancellati.

```bash
# 1. Identifica l'ultimo backup buono
ls -lt /var/backups/kalendarium/ | head -10

# 2. Ferma Horizon e lo scheduler per prevenire scritture
sudo systemctl stop horizon
sudo crontab -e  # commenta la riga schedule:run

# 3. (Opzionale) Fai un dump del DB corrotto per analisi forense
sudo -u postgres pg_dump noscite_calendar > /tmp/corrupted_$(date +%Y%m%d_%H%M%S).sql

# 4. Drop e ricrea il database
sudo -u postgres psql -c "DROP DATABASE noscite_calendar;"
sudo -u postgres psql -c "CREATE DATABASE noscite_calendar OWNER noscite;"

# 5. Ripristina dal backup scelto (es. il più recente)
BACKUP="/var/backups/kalendarium/noscite_calendar_YYYYMMDD_HHMMSS.sql.gz"
gunzip -c "${BACKUP}" | sudo -u postgres psql noscite_calendar

# 6. Verifica conteggi
sudo -u postgres psql noscite_calendar -c "SELECT COUNT(*) FROM organizations;"
sudo -u postgres psql noscite_calendar -c "SELECT COUNT(*) FROM users;"

# 7. Riavvia servizi
sudo systemctl start horizon
sudo crontab -e  # de-commenta schedule:run

# 8. Verifica applicazione
curl https://api.kalendarium.it/api/health
```

**Finestra di perdita dati massima:** fino a 6 ore (intervallo backup).

---

### Caso 2 — VPS perso (failure catastrofica)

> ⚠️ **Senza backup off-site, la perdita di dati è totale.**
> I passi seguenti assumono che Stefano abbia copiato manualmente i backup dal VPS
> prima della failure, o che esistano backup off-site.

```bash
# ── Passo 1: Provisioning nuovo VPS ─────────────────────────────────────
# Usa lo stesso provider (OVH/Hetzner/etc.), Ubuntu 24.04, stessa dimensione.
# Configura DNS: api.kalendarium.it → nuovo IP.

# ── Passo 2: Dipendenze base ─────────────────────────────────────────────
sudo apt update && sudo apt install -y nginx php8.4-fpm php8.4-pgsql \
    php8.4-redis php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip \
    php8.4-intl postgresql-16 redis-server composer git

# ── Passo 3: Clone repo ──────────────────────────────────────────────────
sudo git clone <repo-url> /var/www/Kalendarium
cd /var/www/Kalendarium
sudo composer install --no-dev --optimize-autoloader

# ── Passo 4: Configura .env ──────────────────────────────────────────────
sudo cp .env.example .env
sudo nano .env   # inserire DB_PASSWORD, ANTHROPIC_API_KEY, etc.
sudo php artisan key:generate

# ── Passo 5: Database ────────────────────────────────────────────────────
sudo -u postgres psql -c "CREATE USER noscite WITH PASSWORD '...';"
sudo -u postgres psql -c "CREATE DATABASE noscite_calendar OWNER noscite;"

# Se hai il backup:
BACKUP="/path/to/noscite_calendar_YYYYMMDD_HHMMSS.sql.gz"
gunzip -c "${BACKUP}" | sudo -u postgres psql noscite_calendar

# Se NON hai il backup (ricomincia da zero):
sudo php artisan migrate --force
sudo php artisan db:seed --force

# ── Passo 6: Storage e permessi ──────────────────────────────────────────
sudo chown -R www-data:www-data /var/www/Kalendarium/storage
sudo chmod -R 775 /var/www/Kalendarium/storage
sudo php artisan storage:link

# ── Passo 7: Cron e Horizon ──────────────────────────────────────────────
sudo crontab -e
# Aggiungi: * * * * * cd /var/www/Kalendarium && php artisan schedule:run

sudo cp /var/www/Kalendarium/horizon.service.example /etc/systemd/system/horizon.service
sudo systemctl daemon-reload
sudo systemctl enable horizon
sudo systemctl start horizon

# ── Passo 8: Backup system ───────────────────────────────────────────────
sudo bash /var/www/Kalendarium/scripts/infrastructure/install-backup.sh

# ── Passo 9: Verifica ────────────────────────────────────────────────────
curl https://api.kalendarium.it/api/health
```

---

## 🔧 Manutenzione

### Aggiungere backup off-site (quando pronto)

**Opzione A — Hetzner Storage Box (consigliata):**
```bash
# 1. Acquista Storage Box su console.hetzner.com
# 2. Aggiungi in kalendarium-backup.sh, dopo la pulizia locale:
REMOTE="u123456@u123456.your-storagebox.de:/backups/kalendarium/"
rsync -az --remove-source-files "${BACKUP_FILE}" "${REMOTE}"
```

**Opzione B — Backblaze B2:**
```bash
# 1. Installa b2-tools: pip install b2
# 2. Aggiungi in kalendarium-backup.sh:
b2 upload-file my-bucket "${BACKUP_FILE}" "backups/$(basename "${BACKUP_FILE}")"
```

**Opzione C — rsync su server secondario:**
```bash
rsync -az "${BACKUP_DIR}/" backup-server:/var/backups/kalendarium/
```

### Cambiare retention (default: 14 giorni)

In `/usr/local/bin/kalendarium-backup.sh`, modifica:
```bash
RETENTION_DAYS=14   # ← cambia questo valore
```

Reinstalla dopo la modifica:
```bash
sudo cp /var/www/Kalendarium/scripts/infrastructure/kalendarium-backup.sh \
        /usr/local/bin/kalendarium-backup.sh
```

### Cambiare frequenza backup

```bash
sudo crontab -e
# Default: 0 */6 * * *  (ogni 6 ore)
# Ogni 3 ore: 0 */3 * * *
# Ogni ora:   0 * * * *
# Una volta al giorno: 0 2 * * *
```
