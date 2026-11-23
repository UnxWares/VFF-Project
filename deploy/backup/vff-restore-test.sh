#!/usr/bin/env bash
# Essai de restauration : restaure une sauvegarde dans une base NEUVE et TEMPORAIRE, jamais par-dessus la base
# en service, puis vérifie : SHA-256, comptes de lignes des tables clés, invariants (vff:check-invariants).
#   vff-restore-test.sh /etc/vff/backup.env [fichier.dump]     (par défaut : la dernière quotidienne)
#   Variable RESTORE_KEEP=1 : garde la base temporaire pour l'examiner (à supprimer à la main ensuite).
# Pour une vraie restauration après sinistre : voir docs/SAUVEGARDE.md (restaurer dans une base neuve, vérifier,
# puis basculer l'application ; ce script ne bascule rien).
set -euo pipefail

ici="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib.sh
. "$ici/lib.sh"
charger_env "${1:?usage : $0 fichier-env [sauvegarde.dump]}"
: "${BACKUP_DIR:?}" "${PGDATABASE:?}"

dump="${2:-$(derniere_sauvegarde)}"
[ -n "$dump" ] || die "aucune sauvegarde trouvée dans $BACKUP_DIR/daily"
[ -f "$dump" ] || die "sauvegarde introuvable : $dump"
sauvegarde_valide "$dump" || die "chemin hors BACKUP_DIR ou nom inattendu : $dump"

# Nom de la base temporaire : préfixe littéral, jamais la base en service.
temp="vff_restauration_$(date +%Y%m%d%H%M%S)_$$"
[[ "$temp" =~ ^vff_restauration_[0-9]{14}_[0-9]+$ ]] || die "nom de base temporaire invalide"
[ "$temp" != "$PGDATABASE" ] || die "la base temporaire ne peut pas être la base en service"
base_admin="${RESTORE_ADMIN_DB:-postgres}"

cree=0
nettoyer() {
    local code=$?
    if [ "$cree" = "1" ] && [ "${RESTORE_KEEP:-0}" != "1" ]; then
        # Seule la base créée par ce script (préfixe vérifié ci-dessus) peut être supprimée.
        [[ "$temp" == vff_restauration_* ]] && pg dropdb --if-exists "$temp" || true
        log "base temporaire $temp supprimée"
    elif [ "$cree" = "1" ]; then
        log "base temporaire $temp conservée (RESTORE_KEEP=1)"
    fi
    if [ "$code" -ne 0 ]; then
        log "ECHEC de l'essai de restauration (code $code)"
        battement "${RESTORE_HEARTBEAT_URL:-}" "${HEARTBEAT_FAIL_SUFFIX:-}"
    fi
    exit "$code"
}
trap nettoyer EXIT

log "1/5 empreinte de $(basename -- "$dump")"
if [ -f "$dump.sha256" ]; then
    ( cd "$(dirname -- "$dump")" && sha256sum --check --quiet -- "$(basename -- "$dump").sha256" ) || die "empreinte différente : sauvegarde corrompue"
else
    log "pas de fichier .sha256 (empreinte non vérifiée)"
fi

log "2/5 création de la base neuve $temp"
pg createdb --maintenance-db="$base_admin" "$temp"
cree=1

log "3/5 restauration (pg_restore, ${DUMP_JOBS:-2} tâches)"
# Sans --clean : la base est neuve ; sans propriétaires (le rôle d'origine peut ne pas exister ici). Les extensions
# viennent de template1 (créées par postgres, docs/MISE-EN-PRODUCTION.md, étape 3) : vff_backup n'en est pas
# propriétaire, donc « COMMENT ON EXTENSION » échouerait ; --no-comments l'évite (les commentaires ne sont pas
# contrôlés ici).
pg pg_restore --dbname="$temp" --no-owner --no-privileges --no-comments --exit-on-error --jobs="${DUMP_JOBS:-2}" "$dump"

log "4/5 comptes de lignes des tables clés"
if [ -f "$dump.counts" ]; then
    comptes_cles "$temp" >"${BACKUP_DIR}/tmp/$temp.counts"
    echart=0
    while IFS='=' read -r table attendu; do
        obtenu="$(grep "^$table=" "${BACKUP_DIR}/tmp/$temp.counts" | cut -d= -f2)"
        tol=$(( attendu * ${COUNT_TOLERANCE_PCT:-2} / 100 ))
        diff=$(( obtenu > attendu ? obtenu - attendu : attendu - obtenu ))
        if [ "$diff" -le "$tol" ]; then etat="ok"; else etat="ECART"; echart=1; fi
        printf '  %-18s sauvegarde=%-10s restaurée=%-10s %s\n' "$table" "$attendu" "$obtenu" "$etat" >&2
    done <"$dump.counts"
    rm -f -- "${BACKUP_DIR}/tmp/$temp.counts"
    [ "$echart" = "0" ] || die "comptes de lignes différents au-delà de ${COUNT_TOLERANCE_PCT:-2} %"
else
    log "pas de fichier .counts : comptes non comparés"
    comptes_cles "$temp" >&2
fi

log "5/5 invariants de données (vff:check-invariants) sur la base restaurée"
if [ -n "${APP_DIR:-}" ] && [ -f "${APP_DIR}/artisan" ]; then
    set +e
    # DB_* force la base temporaire et le rôle de sauvegarde (jamais la base du .env). En production la configuration
    # est en cache (php artisan optimize) et Laravel y ignorerait ces variables : APP_CONFIG_CACHE désigne un fichier
    # qui n'existe pas, donc .env est relu et les variables posées ici l'emportent. Mot de passe vide : libpq le lit
    # dans PGPASSFILE. Journal sur la sortie d'erreur (storage/ est en lecture seule pour ce service).
    ( cd "$APP_DIR" && APP_CONFIG_CACHE="/tmp/vff-restauration-sans-cache-$$.php" LOG_CHANNEL=stderr \
        DB_CONNECTION=pgsql DB_HOST="${PGHOST:-127.0.0.1}" DB_PORT="${PGPORT:-5432}" DB_DATABASE="$temp" \
        DB_USERNAME="${PGUSER:?}" DB_PASSWORD="" \
        ${ARTISAN_CMD:-php artisan} vff:check-invariants --no-interaction )
    inv=$?
    set -e
    if [ "$inv" -eq 1 ] && [ "${INVARIANTS_STRICT:-0}" != "1" ]; then
        log "des invariants sont violés (code 1) ; signalé sans faire échouer l'essai (INVARIANTS_STRICT=0)"
    elif [ "$inv" -ne 0 ]; then
        die "vff:check-invariants a échoué (code $inv)"
    fi
else
    log "APP_DIR absent : invariants non lancés"
fi

log "essai de restauration RÉUSSI"
trap - EXIT
if [ "${RESTORE_KEEP:-0}" != "1" ]; then pg dropdb --if-exists "$temp"; log "base temporaire $temp supprimée"; else log "base $temp conservée"; fi
battement "${RESTORE_HEARTBEAT_URL:-}" ""
