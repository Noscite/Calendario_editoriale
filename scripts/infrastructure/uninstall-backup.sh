#!/usr/bin/env bash
# uninstall-backup.sh — Rimuove il sistema di backup Kalendarium dal sistema.
# I backup esistenti in /var/backups/kalendarium/ NON vengono rimossi.
# Eseguire con: sudo bash /var/www/Kalendarium/scripts/infrastructure/uninstall-backup.sh

set -euo pipefail

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BOLD='\033[1m'
NC='\033[0m'

ok()   { echo -e "${GREEN}✓${NC} $*"; }
warn() { echo -e "${YELLOW}⚠${NC} $*"; }
info() { echo -e "${BOLD}→${NC} $*"; }

if [[ "${EUID}" -ne 0 ]]; then
    echo "Questo script deve essere eseguito come root. Usa: sudo bash $0" >&2
    exit 1
fi

echo ""
echo -e "${BOLD}Disinstallazione sistema di backup Kalendarium...${NC}"
echo ""

# ── Rimuovi cron job ───────────────────────────────────────────────────────
info "Rimozione cron job..."
REMAINING=$(crontab -l 2>/dev/null | grep -v "kalendarium-backup" || true)
if [[ -n "${REMAINING}" ]]; then
    echo "${REMAINING}" | crontab -
else
    crontab -r 2>/dev/null || true
fi
ok "Cron job rimosso."

# ── Rimuovi script di sistema ──────────────────────────────────────────────
info "Rimozione script da /usr/local/bin/..."
rm -f /usr/local/bin/kalendarium-backup.sh
rm -f /usr/local/bin/kalendarium-restore-test.sh
ok "Script rimossi."

# ── Rimuovi logrotate ──────────────────────────────────────────────────────
info "Rimozione config logrotate..."
rm -f /etc/logrotate.d/kalendarium-backup
ok "Config logrotate rimossa."

# ── Riepilogo ─────────────────────────────────────────────────────────────
echo ""
ok "Backup system uninstalled. Existing backups preserved in /var/backups/kalendarium/"
echo ""
warn "I backup esistenti in /var/backups/kalendarium/ sono stati conservati."
warn "Il log /var/log/kalendarium-backup.log è stato conservato."
echo ""
info "Per rimuovere i backup manualmente: rm -rf /var/backups/kalendarium/"
echo ""
