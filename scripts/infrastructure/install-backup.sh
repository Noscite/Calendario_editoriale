#!/usr/bin/env bash
# install-backup.sh — Installa il sistema di backup Kalendarium
# Eseguire con: sudo bash /var/www/Kalendarium/scripts/infrastructure/install-backup.sh

set -euo pipefail

# ── Colori output ──────────────────────────────────────────────────────────
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BOLD='\033[1m'
NC='\033[0m'

ok()   { echo -e "${GREEN}✓${NC} $*"; }
err()  { echo -e "${RED}✗ ERRORE:${NC} $*" >&2; }
warn() { echo -e "${YELLOW}⚠${NC} $*"; }
info() { echo -e "${BOLD}→${NC} $*"; }

# ── Verifica root ──────────────────────────────────────────────────────────
if [[ "${EUID}" -ne 0 ]]; then
    err "Questo script deve essere eseguito come root."
    err "Usa: sudo bash $0"
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║   Kalendarium Backup System — Installer      ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════╝${NC}"
echo ""

# ── 1. Directory backup ────────────────────────────────────────────────────
info "Creazione directory /var/backups/kalendarium/ ..."
mkdir -p /var/backups/kalendarium
chown root:root /var/backups/kalendarium
chmod 700 /var/backups/kalendarium
ok "Directory /var/backups/kalendarium/ creata (permessi 700, owner root)"

# ── 2. Script backup ───────────────────────────────────────────────────────
info "Installazione kalendarium-backup.sh ..."
cp "${SCRIPT_DIR}/kalendarium-backup.sh" /usr/local/bin/kalendarium-backup.sh
chown root:root /usr/local/bin/kalendarium-backup.sh
chmod 755 /usr/local/bin/kalendarium-backup.sh
ok "Installato: /usr/local/bin/kalendarium-backup.sh"

# ── 3. Script restore test ─────────────────────────────────────────────────
info "Installazione kalendarium-restore-test.sh ..."
cp "${SCRIPT_DIR}/kalendarium-restore-test.sh" /usr/local/bin/kalendarium-restore-test.sh
chown root:root /usr/local/bin/kalendarium-restore-test.sh
chmod 755 /usr/local/bin/kalendarium-restore-test.sh
ok "Installato: /usr/local/bin/kalendarium-restore-test.sh"

# ── 4. Logrotate ───────────────────────────────────────────────────────────
info "Installazione config logrotate ..."
cp "${SCRIPT_DIR}/kalendarium-backup.logrotate" /etc/logrotate.d/kalendarium-backup
chown root:root /etc/logrotate.d/kalendarium-backup
chmod 644 /etc/logrotate.d/kalendarium-backup
ok "Installato: /etc/logrotate.d/kalendarium-backup"

# ── 5. Cron job (idempotente) ──────────────────────────────────────────────
info "Configurazione cron job (ogni 6 ore) ..."
CRON_LINE="0 */6 * * * /usr/local/bin/kalendarium-backup.sh >> /var/log/kalendarium-backup.log 2>&1"
EXISTING=$(crontab -l 2>/dev/null | grep -v "kalendarium-backup" || true)
printf '%s\n%s\n' "${EXISTING}" "${CRON_LINE}" | grep -v '^$' | crontab -
ok "Cron job configurato: 0 */6 * * *"
info "Prossime esecuzioni: 00:00, 06:00, 12:00, 18:00 (ogni giorno)"

# ── 6. Backup di test ──────────────────────────────────────────────────────
echo ""
info "Esecuzione backup di test ..."
if /usr/local/bin/kalendarium-backup.sh; then
    ok "Backup di test completato con successo."
else
    err "Backup di test fallito. Controllare /var/log/kalendarium-backup.log"
    exit 1
fi

# ── 7. Test ripristino ─────────────────────────────────────────────────────
echo ""
info "Esecuzione test di ripristino ..."
if /usr/local/bin/kalendarium-restore-test.sh; then
    ok "Test di ripristino completato con successo."
else
    err "Test di ripristino fallito. Controllare /var/log/kalendarium-backup.log"
    exit 1
fi

# ── Riepilogo finale ───────────────────────────────────────────────────────
echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║          Installazione completata!           ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${GREEN}File installati:${NC}"
echo "  /usr/local/bin/kalendarium-backup.sh"
echo "  /usr/local/bin/kalendarium-restore-test.sh"
echo "  /etc/logrotate.d/kalendarium-backup"
echo ""
echo -e "${GREEN}Directory backup:${NC}"
echo "  /var/backups/kalendarium/"
echo ""
echo -e "${GREEN}Log:${NC}"
echo "  /var/log/kalendarium-backup.log"
echo ""
echo -e "${GREEN}Schedule:${NC}"
echo "  Cron: 0 */6 * * * (00:00, 06:00, 12:00, 18:00)"
echo "  Retention: 14 giorni"
echo ""
echo -e "${GREEN}Comandi utili:${NC}"
echo "  Backup manuale    : sudo /usr/local/bin/kalendarium-backup.sh"
echo "  Test ripristino   : sudo /usr/local/bin/kalendarium-restore-test.sh"
echo "  Vedi log          : tail -50 /var/log/kalendarium-backup.log"
echo "  Lista backup      : ls -lh /var/backups/kalendarium/"
echo "  Crontab root      : crontab -l"
echo ""
warn "ATTENZIONE: backup SOLO locali. Se il VPS va offline, i dati sono persi."
warn "Configurare backup off-site (es. Hetzner Storage Box, B2) appena possibile."
echo ""
