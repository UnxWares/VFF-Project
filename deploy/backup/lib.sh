#!/usr/bin/env bash
# shellcheck disable=SC2010
# Fonctions communes de la sauvegarde de production de VFF (à sourcer, jamais à lancer).
# Garde-fous : aucune suppression récursive, aucun joker ; une suppression ne vise qu'un fichier
# dont le nom correspond au motif littéral vff-AAAAMMJJ-HHMMSS.dump dans un dossier sous BACKUP_DIR.

log() { printf '%s %s\n' "$(date -Is)" "$*" >&2; }
die() { log "ERREUR : $*"; exit 1; }

# Charge le fichier d'environnement (clés S3, mot de passe restic, connexion) : refuse un fichier lisible par d'autres.
charger_env() {
    local f="${1:?fichier-env manquant}"
    [ -f "$f" ] || die "fichier d'environnement introuvable : $f"
    [ "$(stat -c '%a' "$f")" = "600" ] || [ "$(stat -c '%a' "$f")" = "400" ] || die "$f doit être en mode 600 ou 400"
    set -a
    # shellcheck disable=SC1090
    . "$f"
    set +a
}

# Commande PostgreSQL : PG_BIN_DIR (facultatif) force un dossier de binaires de version >= serveur.
pg() { "${PG_BIN_DIR:+$PG_BIN_DIR/}${1:?}" "${@:2}"; }

# Vérifie qu'un chemin est sous BACKUP_DIR et qu'il s'agit d'une sauvegarde de VFF (motif littéral strict).
sauvegarde_valide() {
    local p="${1:?}" base
    base="$(basename -- "$p")"
    [[ "$p" == "${BACKUP_DIR:?}/"* ]] || return 1
    [[ "$p" != *".."* ]] || return 1
    [[ "$base" =~ ^vff-[0-9]{8}-[0-9]{6}\.dump$ ]]
}

# Supprime UN fichier de sauvegarde (et ses compagnons .sha256 / .counts) après contrôle du motif. Jamais récursif.
supprimer_sauvegarde() {
    local p="${1:?}"
    sauvegarde_valide "$p" || die "refus de supprimer un chemin hors motif : $p"
    [ -f "$p" ] && [ ! -L "$p" ] || return 0
    rm -f -- "$p" "$p.sha256" "$p.counts"
    log "supprimé (rotation) : $(basename -- "$p")"
}

# Garde les N plus récentes sauvegardes d'un dossier, supprime les autres (une par une).
garder_les_plus_recentes() {
    local dir="${1:?}" n="${2:?}" f i=0
    [[ "$dir" == "${BACKUP_DIR:?}/"* ]] || die "dossier hors BACKUP_DIR : $dir"
    [ -d "$dir" ] || return 0
    while IFS= read -r f; do
        i=$((i + 1))
        [ "$i" -le "$n" ] && continue
        supprimer_sauvegarde "$dir/$f"
    done < <(ls -1 -- "$dir" | grep -E '^vff-[0-9]{8}-[0-9]{6}\.dump$' | sort -r)
}

# Dernière sauvegarde du dossier daily (chemin complet), vide si aucune.
derniere_sauvegarde() {
    local dir="${BACKUP_DIR:?}/daily" f
    f="$(ls -1 -- "$dir" 2>/dev/null | grep -E '^vff-[0-9]{8}-[0-9]{6}\.dump$' | sort | tail -n 1 || true)"
    [ -n "$f" ] && printf '%s/%s\n' "$dir" "$f"
    return 0
}

# Battement : GET (ou POST) sur une adresse de suivi ; ne fait jamais échouer le script appelant.
battement() {
    local url="${1:-}" suffixe="${2:-}"
    [ -n "$url" ] || return 0
    curl -fsS -m 15 --retry 2 -o /dev/null "${url}${suffixe}" 2>/dev/null || log "battement non reçu par le suivi (${suffixe:-succès})"
}

# Comptes de lignes des tables clés (une ligne « table=nombre » par table), sur la base $1.
comptes_cles() {
    local db="${1:?}" t
    for t in ${COUNT_TABLES:-lines stations eras line_sections line_segments line_events media users}; do
        [[ "$t" =~ ^[a-z_][a-z0-9_]*$ ]] || die "nom de table invalide : $t"
        printf '%s=%s\n' "$t" "$(pg psql -X -At -d "$db" -c "select count(*) from public.\"$t\"")"
    done
}
