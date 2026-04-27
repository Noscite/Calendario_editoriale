#!/usr/bin/env bash
# kalendarium-backup.sh — PostgreSQL backup per Kalendarium
# Installato in /usr/local/bin/ da install-backup.sh
# Eseguito da cron come root ogni 6 ore.

set -euo pipefail

# ── Configurazione ─────────────────────────────────────────────────────────
DB_NAME="noscite_calendar"
DB_USER="postgres"
BACKUP_DIR="/var/backups/kalendarium"
LOG_FILE="/var/log/kalendarium-backup.log"
FAILURE_FILE="/var/log/kalendarium-backup.failures"
RETENTION_DAYS=14
MIN_SIZE_BYTES=1024    # 1 KB minimo

TIMESTAMP="$(date '+%Y%m%d_%H%M%S')"
BACKUP_FILE="${BACKUP_DIR}/${DB_NAME}_${TIMESTAMP}.sql.gz"

# ── Funzioni di log ────────────────────────────────────────────────────────
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "${LOG_FILE}"
}

log_error() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $*" | tee -a "${LOG_FILE}" >&2
}

# ── Gestione fallimenti consecutivi ────────────────────────────────────────
increment_failures() {
    local count=0
    [[ -f "${FAILURE_FILE}" ]] && count="$(cat "${FAILURE_FILE}")"
    count=$((count + 1))
    echo "${count}" > "${FAILURE_FILE}"
    if [[ "${count}" -ge 3 ]]; then
        log_error "WARNING: ${count} fallimenti consecutivi. Verificare il sistema di backup!"
    fi
}

reset_failures() {
    echo "0" > "${FAILURE_FILE}"
}

# ── Cleanup in caso di errore ──────────────────────────────────────────────
cleanup_on_error() {
    [[ -f "${BACKUP_FILE}" ]] && rm -f "${BACKUP_FILE}"
    log_error "Backup fallito — file parziale rimosso (se presente)."
    increment_failures
    exit 1
}

trap cleanup_on_error ERR

# ── Inizio backup ──────────────────────────────────────────────────────────
log "=== Inizio backup ${DB_NAME} ==="

# Crea directory se non esiste (fallback per sicurezza)
mkdir -p "${BACKUP_DIR}"
chmod 700 "${BACKUP_DIR}"

# ── pg_dump + gzip ────────────────────────────────────────────────────────
log "Esecuzione pg_dump → ${BACKUP_FILE}"
if ! sudo -u "${DB_USER}" pg_dump "${DB_NAME}" | gzip > "${BACKUP_FILE}"; then
    log_error "pg_dump fallito."
    cleanup_on_error
fi

# ── Verifica integrità gzip ────────────────────────────────────────────────
log "Verifica integrità gzip..."
if ! gzip -t "${BACKUP_FILE}"; then
    log_error "Verifica gzip fallita — file corrotto."
    cleanup_on_error
fi

# ── Verifica dimensione minima ─────────────────────────────────────────────
FILE_SIZE="$(stat -c '%s' "${BACKUP_FILE}")"
if [[ "${FILE_SIZE}" -lt "${MIN_SIZE_BYTES}" ]]; then
    log_error "Backup troppo piccolo: ${FILE_SIZE} bytes (minimo ${MIN_SIZE_BYTES}). DB vuoto?"
    cleanup_on_error
fi

log "Backup completato: ${BACKUP_FILE} ($(numfmt --to=iec "${FILE_SIZE}"))"

# ── Pulizia backup vecchi ──────────────────────────────────────────────────
log "Pulizia backup più vecchi di ${RETENTION_DAYS} giorni..."
DELETED="$(find "${BACKUP_DIR}" -name "${DB_NAME}_*.sql.gz" -mtime "+${RETENTION_DAYS}" -print -delete | wc -l)"
if [[ "${DELETED}" -gt 0 ]]; then
    log "Eliminati ${DELETED} backup scaduti."
fi

# ── Conteggio backup attivi ────────────────────────────────────────────────
TOTAL="$(find "${BACKUP_DIR}" -name "${DB_NAME}_*.sql.gz" | wc -l)"
log "Backup attivi in ${BACKUP_DIR}: ${TOTAL}"

# ── Successo ──────────────────────────────────────────────────────────────
reset_failures
log "=== Backup completato con successo ==="
exit 0
