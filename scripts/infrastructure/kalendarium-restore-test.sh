#!/usr/bin/env bash
# kalendarium-restore-test.sh — Verifica ripristino backup Kalendarium
# Installato in /usr/local/bin/ da install-backup.sh
# NON tocca mai il database di produzione.

set -euo pipefail

# ── Configurazione ─────────────────────────────────────────────────────────
DB_PROD="noscite_calendar"
DB_TEST="noscite_calendar_restore_test"
DB_USER="postgres"
BACKUP_DIR="/var/backups/kalendarium"
LOG_FILE="/var/log/kalendarium-backup.log"

# ── Funzioni di log ────────────────────────────────────────────────────────
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [restore-test] $*" | tee -a "${LOG_FILE}"
}

log_error() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [restore-test] ERROR: $*" | tee -a "${LOG_FILE}" >&2
}

# ── Cleanup garantito ─────────────────────────────────────────────────────
drop_test_db() {
    log "Rimozione database temporaneo ${DB_TEST}..."
    sudo -u "${DB_USER}" psql -c "DROP DATABASE IF EXISTS ${DB_TEST};" postgres 2>/dev/null || true
}

trap drop_test_db EXIT

# ── Trova ultimo backup ────────────────────────────────────────────────────
log "=== Inizio test di ripristino ==="
log "Ricerca ultimo backup in ${BACKUP_DIR}..."

LATEST="$(find "${BACKUP_DIR}" -name "${DB_PROD}_*.sql.gz" -printf '%T@ %p\n' 2>/dev/null \
    | sort -n | tail -1 | awk '{print $2}')"

if [[ -z "${LATEST}" ]]; then
    log_error "Nessun backup trovato in ${BACKUP_DIR}. Eseguire prima kalendarium-backup.sh."
    exit 1
fi

log "Ultimo backup: ${LATEST}"
log "Dimensione: $(du -sh "${LATEST}" | cut -f1)"

# ── Crea DB temporaneo ─────────────────────────────────────────────────────
log "Creazione database temporaneo ${DB_TEST}..."
sudo -u "${DB_USER}" psql -c "DROP DATABASE IF EXISTS ${DB_TEST};" postgres
sudo -u "${DB_USER}" psql -c "CREATE DATABASE ${DB_TEST};" postgres

# ── Ripristino ────────────────────────────────────────────────────────────
log "Ripristino backup in ${DB_TEST}..."
if ! gunzip -c "${LATEST}" | sudo -u "${DB_USER}" psql "${DB_TEST}" > /dev/null 2>&1; then
    log_error "Ripristino fallito."
    exit 1
fi

log "Ripristino completato. Verifica conteggi tabelle..."

# ── Verifica conteggi ─────────────────────────────────────────────────────
run_query() {
    sudo -u "${DB_USER}" psql -tA -c "$1" "${DB_TEST}" 2>/dev/null | tr -d '[:space:]'
}

ORG_COUNT="$(run_query "SELECT COUNT(*) FROM organizations;")"
USER_COUNT="$(run_query "SELECT COUNT(*) FROM users;")"
SUB_COUNT="$(run_query "SELECT COUNT(*) FROM subscriptions;" 2>/dev/null || echo 'N/A')"

log "─────────────────────────────────────"
log "  organizations : ${ORG_COUNT}"
log "  users         : ${USER_COUNT}"
log "  subscriptions : ${SUB_COUNT}"
log "─────────────────────────────────────"

# ── Valida conteggi ───────────────────────────────────────────────────────
if [[ "${ORG_COUNT}" -lt 1 ]] 2>/dev/null; then
    log_error "Nessuna organizzazione nel backup — dati sospetti."
    exit 1
fi

if [[ "${USER_COUNT}" -lt 1 ]] 2>/dev/null; then
    log_error "Nessun utente nel backup — dati sospetti."
    exit 1
fi

# ── Successo ──────────────────────────────────────────────────────────────
log "=== Test di ripristino completato con successo ==="
log "Database temporaneo verrà rimosso."
exit 0
