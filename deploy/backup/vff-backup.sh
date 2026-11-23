#!/usr/bin/env bash
# Sauvegarde logique quotidienne de la base de production de VFF.
#   1. pg_dump -Fc complet (écrit sous un nom provisoire, renommé seulement si tout est bon)
#   2. contrôles : pg_restore -l (table des matières) puis lecture complète pg_restore -f /dev/null
#   3. empreinte SHA-256 et comptes de lignes des tables clés (lus par vff-restore-test.sh)
#   4. rotation locale : 7 quotidiennes, 4 hebdomadaires (dimanche), 6 mensuelles (le 1er)
#   5. envoi chiffré hors site avec restic, si RESTIC_REPOSITORY est défini
#   6. battement vers le suivi (succès) ou signal d'échec
# Usage : vff-backup.sh /etc/vff/backup.env
# Ne touche jamais à la base : lecture seule. Aucune suppression récursive.
set -euo pipefail

ici="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib.sh
. "$ici/lib.sh"
charger_env "${1:?usage : $0 fichier-env}"

: "${BACKUP_DIR:?BACKUP_DIR manquant}"
: "${PGDATABASE:?PGDATABASE manquant}"
[[ "$BACKUP_DIR" == /* ]] || die "BACKUP_DIR doit être un chemin absolu"
[ "$BACKUP_DIR" != "/" ] || die "BACKUP_DIR ne peut pas être /"
RETENTION_DAILY="${RETENTION_DAILY:-7}"
RETENTION_WEEKLY="${RETENTION_WEEKLY:-4}"
RETENTION_MONTHLY="${RETENTION_MONTHLY:-6}"

provisoire=""
echec() {
    local code=$?
    [ -n "$provisoire" ] && [ -f "$provisoire" ] && rm -f -- "$provisoire"
    log "ECHEC de la sauvegarde (code $code)"
    battement "${HEARTBEAT_URL:-}" "${HEARTBEAT_FAIL_SUFFIX:-}"
    exit "$code"
}
trap echec ERR

umask 077
mkdir -p -- "$BACKUP_DIR/daily" "$BACKUP_DIR/weekly" "$BACKUP_DIR/monthly" "$BACKUP_DIR/tmp"

# Un seul passage à la fois.
exec 9>"$BACKUP_DIR/.verrou"
flock -n 9 || die "une autre sauvegarde est en cours"

horodatage="$(date +%Y%m%d-%H%M%S)"
nom="vff-$horodatage.dump"
provisoire="$BACKUP_DIR/tmp/$nom.partiel"
final="$BACKUP_DIR/daily/$nom"
sauvegarde_valide "$final" || die "nom de sauvegarde inattendu : $final"

# Place disque : au moins 1,2 fois la taille de la base (le dump compressé fait bien moins, marge large).
taille_base="$(pg psql -X -At -d "$PGDATABASE" -c "select pg_database_size(current_database())")"
libre="$(df --output=avail -B1 -- "$BACKUP_DIR" | tail -n 1 | tr -d ' ')"
[ "$libre" -gt $((taille_base * 12 / 10)) ] || die "place insuffisante dans $BACKUP_DIR (libre $libre, base $taille_base)"

log "pg_dump de $PGDATABASE (base de $((taille_base / 1048576)) Mio)"
pg pg_dump --format=custom --compress=zstd:6 --no-password --file="$provisoire" "$PGDATABASE"

log "contrôle 1 : table des matières"
pg pg_restore --list "$provisoire" >/dev/null
log "contrôle 2 : lecture complète"
pg pg_restore --file=/dev/null "$provisoire"

( cd "$BACKUP_DIR/tmp" && sha256sum -- "$nom.partiel" | sed "s/$nom.partiel/$nom/" ) >"$provisoire.sha256"
comptes_cles "$PGDATABASE" >"$provisoire.counts"

mv -- "$provisoire" "$final"
mv -- "$provisoire.sha256" "$final.sha256"
mv -- "$provisoire.counts" "$final.counts"
provisoire=""
log "sauvegarde écrite : $final ($(du -h -- "$final" | cut -f1))"

# Copies hebdomadaire (dimanche) et mensuelle (le 1er) : liens physiques, aucune place en plus.
if [ "$(date +%u)" = "7" ]; then
    for ext in "" .sha256 .counts; do ln -f -- "$final$ext" "$BACKUP_DIR/weekly/$nom$ext"; done
fi
if [ "$(date +%d)" = "01" ]; then
    for ext in "" .sha256 .counts; do ln -f -- "$final$ext" "$BACKUP_DIR/monthly/$nom$ext"; done
fi
garder_les_plus_recentes "$BACKUP_DIR/daily" "$RETENTION_DAILY"
garder_les_plus_recentes "$BACKUP_DIR/weekly" "$RETENTION_WEEKLY"
garder_les_plus_recentes "$BACKUP_DIR/monthly" "$RETENTION_MONTHLY"

# Envoi chiffré côté client : sans le mot de passe du dépôt restic, rien n'est lisible chez l'hébergeur.
if [ -n "${RESTIC_REPOSITORY:-}" ]; then
    : "${RESTIC_PASSWORD_FILE:?RESTIC_PASSWORD_FILE manquant}"
    export RESTIC_REPOSITORY RESTIC_PASSWORD_FILE
    restic cat config >/dev/null 2>&1 || { log "initialisation du dépôt restic"; restic init; }
    restic backup --tag vff-db --host vff-prod -- "$final" "$final.sha256" "$final.counts"
    restic forget --tag vff-db --host vff-prod --keep-daily "$RETENTION_DAILY" \
        --keep-weekly "$RETENTION_WEEKLY" --keep-monthly "$RETENTION_MONTHLY" --prune
    restic check --read-data-subset=5%
else
    log "RESTIC_REPOSITORY vide : sauvegarde locale seulement (pas de copie hors site)"
fi

trap - ERR
battement "${HEARTBEAT_URL:-}" ""
log "terminé"
