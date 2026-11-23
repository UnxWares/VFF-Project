#!/usr/bin/env bash
# Mise en production de VFF sur le serveur d'UnxWares : un seul parcours, dans l'ordre de docs/MISE-EN-PRODUCTION.md.
#
#   sudo deploy/bin/mise-en-production.sh --verifier              # ne change rien, dit ce qui manque (code 1 s'il manque)
#   sudo deploy/bin/mise-en-production.sh --appliquer             # pose ce qui peut l'être, s'arrête à la première erreur
#   sudo deploy/bin/mise-en-production.sh --verifier --etape nginx    # une seule étape
#   deploy/bin/mise-en-production.sh --liste                      # les étapes, dans l'ordre
#   Option --hors-ligne : saute les contrôles qui interrogent le site, le DNS ou les sondes (requêtes HTTP et DNS).
#
# À lancer depuis le dépôt cloné sur le serveur (/srv/vff) : les fichiers posés sont ceux de ce dépôt.
# Variables : VFF_DIR (/srv/vff), VFF_DOMAINE (vff-project.org), VFF_DEPLOY_USER (vffdeploy, propriétaire du code),
# SERVEUR_IPV4 et SERVEUR_IPV6 (adresses attendues dans le DNS, facultatives), PHP_VERSION (8.5).
#
# Prudence (incident du 28/10/2026) : aucune suppression (ni rm, ni copie qui efface la destination). Un fichier de
# configuration déjà présent et différent est copié dans /var/backups/vff-config/ avant d'être remplacé. Chaque
# chemin écrit est vérifié contre un préfixe littéral. Idempotent : relancer ne change que ce qui diffère.
# SC2015 : « test && ok || manque » est voulu, ok() renvoie toujours 0. SC2329 : les étapes sont appelées par leur
# nom (etape_<nom>), shellcheck ne le voit pas.
# shellcheck disable=SC2015,SC2329
set -euo pipefail
umask 022

ici="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPOT="$(cd "$ici/../.." && pwd)"
VFF_DIR="${VFF_DIR:-/srv/vff}"
VFF_DOMAINE="${VFF_DOMAINE:-vff-project.org}"
VFF_DEPLOY_USER="${VFF_DEPLOY_USER:-vffdeploy}"
PHP_VERSION="${PHP_VERSION:-8.5}"
ENV_FICHIER="${VFF_DIR:?}/.env"
SAUVEGARDES_CONFIG=/var/backups/vff-config
BASE_URL="https://${VFF_DOMAINE:?}"

ETAPES=(prerequis comptes postgresql donnees valkey application sauvegarde migrations php-fpm services
    planificateur temps-reel certificat nginx fond-de-carte commons suivi entetes dns)

MODE=""
ETAPE_SEULE=""
HORS_LIGNE=0
MANQUES=0

usage() {
    sed -n '2,13p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
    exit "${1:-2}"
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        --verifier) MODE=verifier ;;
        --appliquer) MODE=appliquer ;;
        --etape) ETAPE_SEULE="${2:?--etape demande un nom (voir --liste)}"; shift ;;
        --hors-ligne) HORS_LIGNE=1 ;;
        --liste) printf '%s\n' "${ETAPES[@]}"; exit 0 ;;
        -h|--help) usage 0 ;;
        *) printf 'option inconnue : %s\n' "$1" >&2; usage 2 ;;
    esac
    shift
done
[ -n "$MODE" ] || usage 2
if [ -n "$ETAPE_SEULE" ] && ! printf '%s\n' "${ETAPES[@]}" | grep -qx -- "$ETAPE_SEULE"; then
    printf 'étape inconnue : %s (voir --liste)\n' "$ETAPE_SEULE" >&2
    exit 2
fi

# --- Affichage et arrêt ---------------------------------------------------------------------------------------

titre() { printf '\n== %s\n' "$1"; }
ok() { printf 'OK      %s\n' "$1"; }
info() { printf 'INFO    %s\n' "$1"; }
fait() { printf 'POSE    %s\n' "$1"; }
die() { printf 'ERREUR  %s\n' "$1" >&2; exit 1; }

# Ce qui manque : noté en vérification (le parcours continue), bloquant en application (arrêt immédiat).
# $2 : ce qu'il faut faire, commande exacte de docs/MISE-EN-PRODUCTION.md.
manque() {
    printf 'MANQUE  %s\n' "$1"
    [ -n "${2:-}" ] && printf '        -> %s\n' "$2"
    MANQUES=$((MANQUES + 1))
    if [ "$MODE" = appliquer ]; then
        die "étape arrêtée : corriger puis relancer --appliquer (idempotent)"
    fi
    return 0
}

appliquer() { [ "$MODE" = appliquer ]; }
en_ligne() { [ "$HORS_LIGNE" = 0 ]; }
a() { command -v "$1" >/dev/null 2>&1; }

exiger_root() {
    if [ "$(id -u)" != 0 ]; then
        if appliquer; then die "--appliquer se lance en root (sudo)"; fi
        info "pas root : certains contrôles sont partiels"
        return 1
    fi
    return 0
}

# Lit une valeur de .env sans l'exécuter : dernière affectation, guillemets et commentaire de fin retirés.
env_val() {
    local cle="${1:?}" ligne
    [ -r "$ENV_FICHIER" ] || return 0
    ligne="$(grep -E "^[[:space:]]*${cle}=" "$ENV_FICHIER" | tail -n 1 || true)"
    [ -n "$ligne" ] || return 0
    ligne="${ligne#*=}"
    case "$ligne" in
        \"*) ligne="${ligne#\"}"; ligne="${ligne%%\"*}" ;;
        \'*) ligne="${ligne#\'}"; ligne="${ligne%%\'*}" ;;
        *) ligne="${ligne%%[[:space:]]#*}"; ligne="${ligne%"${ligne##*[![:space:]]}"}" ;;
    esac
    printf '%s' "$ligne"
}

# Contrôle d'une variable de .env : égale à une valeur attendue.
env_egal() {
    local cle="${1:?}" attendu="${2?}" val
    val="$(env_val "$cle")"
    if [ "$val" = "$attendu" ]; then ok ".env $cle=$attendu"; else manque ".env $cle vaut « $val », attendu « $attendu »" "éditer $ENV_FICHIER puis php artisan config:cache"; fi
}

env_rempli() {
    local cle="${1:?}" val
    val="$(env_val "$cle")"
    if [ -n "$val" ] && [ "$val" != null ]; then ok ".env $cle renseignée"; else manque ".env $cle vide" "${2:-éditer $ENV_FICHIER}"; fi
}

# Chemins où le script a le droit d'écrire : préfixes littéraux, jamais un chemin libre.
chemin_permis() {
    case "${1:?}" in
        /etc/systemd/system/vff*|/etc/nginx/conf.d/vff-project.org.conf|/etc/nginx/snippets/vff-*|/opt/vff/backup/*|/etc/php/*/fpm/pool.d/vff.conf) return 0 ;;
        *) return 1 ;;
    esac
}

# Pose un fichier du dépôt (ou un fichier déjà préparé) : rien si identique ; sinon copie de l'ancien dans
# /var/backups/vff-config/, puis install. Jamais de suppression.
poser() {
    local src="${1:?}" dest="${2:?}" mode="${3:?}" proprio="${4:-root}"
    chemin_permis "$dest" || die "chemin refusé (hors des préfixes permis) : $dest"
    [ -f "$src" ] || die "source absente du dépôt : $src"
    if [ -f "$dest" ] && cmp -s -- "$src" "$dest"; then
        ok "$dest à jour"
        return 0
    fi
    if ! appliquer; then
        if [ -f "$dest" ]; then manque "$dest diffère du dépôt" "deploy/bin/mise-en-production.sh --appliquer --etape $ETAPE_EN_COURS"
        else manque "$dest absent" "deploy/bin/mise-en-production.sh --appliquer --etape $ETAPE_EN_COURS"; fi
        return 0
    fi
    install -d -m 0700 -o root -g root -- "$SAUVEGARDES_CONFIG"
    if [ -f "$dest" ]; then
        cp -p -- "$dest" "$SAUVEGARDES_CONFIG/$(basename -- "$dest").$(date +%Y%m%d-%H%M%S)"
        info "ancien $dest copié dans $SAUVEGARDES_CONFIG/"
    fi
    install -D -m "$mode" -o "$proprio" -g root -- "$src" "$dest"
    fait "$dest"
}

unite_active() {
    if systemctl is-active --quiet "$1" 2>/dev/null; then ok "unité $1 active"; return 0; fi
    if appliquer; then
        systemctl enable --now "$1"
        systemctl is-active --quiet "$1" || die "$1 ne démarre pas : journalctl -u $1 -n 50"
        fait "unité $1 activée et démarrée"
    else
        manque "unité $1 inactive" "sudo systemctl enable --now $1"
    fi
}

utilisateur_existe() { id -u "$1" >/dev/null 2>&1; }
dans_groupe() { id -nG "$1" 2>/dev/null | tr ' ' '\n' | grep -qx "$2"; }

# Commandes de l'application sous le compte vff (celui des services), dans le dossier de l'application.
artisan_vff() { runuser -u vff -- env -C "$VFF_DIR" php artisan "$@" --no-interaction; }
# Construction (composer, pnpm) sous le compte propriétaire du code.
# HOME du compte (runuser garde celui de root) : caches de composer et de pnpm.
comme_deploy() {
    local maison
    maison="$(getent passwd "$VFF_DEPLOY_USER" | cut -d: -f6)"
    runuser -u "$VFF_DEPLOY_USER" -- env -C "$VFF_DIR" HOME="${maison:?}" "$@"
}

http_code() { curl -s -o /dev/null -m 15 -w '%{http_code}' "$@" || true; }

ETAPE_EN_COURS=""

# --- 1. Prérequis : logiciels du serveur (jamais installés par ce script) -------------------------------------
etape_prerequis() {
    local c manquants=()
    for c in git curl openssl flock runuser install cmp php composer node pnpm psql pg_dump pg_restore \
        valkey-cli nginx certbot restic dig ss systemctl systemd-analyze pdftotext; do
        a "$c" || manquants+=("$c")
    done
    if [ "${#manquants[@]}" -eq 0 ]; then ok "outils présents"; else
        manque "outils absents : ${manquants[*]}" "voir docs/MISE-EN-PRODUCTION.md, étape 1 (apt install …)"
    fi
    if a php; then
        local v
        v="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
        if [ "$v" = "$PHP_VERSION" ]; then ok "PHP $v"; else manque "PHP $v, attendu $PHP_VERSION" "paquets php$PHP_VERSION-*"; fi
        local e absents=()
        for e in pdo_pgsql pgsql intl zip bcmath pcntl gd mbstring xml curl redis 'Zend OPcache'; do
            php -m | grep -qix -- "$e" || absents+=("$e")
        done
        if [ "${#absents[@]}" -eq 0 ]; then ok "extensions PHP présentes"; else
            manque "extensions PHP absentes : ${absents[*]}" "apt install php$PHP_VERSION-{pgsql,intl,zip,bcmath,gd,mbstring,xml,curl,redis,opcache}"
        fi
    fi
    if a node; then
        local n
        n="$(node -p 'process.versions.node.split(".")[0]')"
        if [ "$n" -ge 26 ]; then ok "Node $n"; else manque "Node $n, package.json demande >= 26" "installer Node 26"; fi
    fi
    if a pg_dump; then
        local p
        p="$(pg_dump --version | grep -oE '[0-9]+' | head -n 1)"
        if [ "$p" -ge 18 ]; then ok "pg_dump $p"; else manque "pg_dump $p, il faut >= 18 (version du serveur)" "postgresql-client-18"; fi
    fi
    if systemctl cat "php$PHP_VERSION-fpm.service" >/dev/null 2>&1; then ok "php$PHP_VERSION-fpm installé"; else
        manque "php$PHP_VERSION-fpm absent" "apt install php$PHP_VERSION-fpm"
    fi
}

# --- 2. Comptes système ---------------------------------------------------------------------------------------
etape_comptes() {
    exiger_root || true
    local u
    for u in vff vffbackup; do
        if utilisateur_existe "$u"; then ok "compte $u"; elif appliquer; then
            if [ "$u" = vff ]; then useradd --system --home-dir "$VFF_DIR" --no-create-home --shell /usr/sbin/nologin vff
            else useradd --system --home-dir /var/backups/vff --no-create-home --shell /usr/sbin/nologin vffbackup; fi
            fait "compte système $u"
        else
            manque "compte $u absent" "sudo useradd --system --no-create-home --shell /usr/sbin/nologin $u"
        fi
    done
    if utilisateur_existe "$VFF_DEPLOY_USER"; then ok "compte de déploiement $VFF_DEPLOY_USER"; else
        manque "compte de déploiement $VFF_DEPLOY_USER absent (propriétaire du code, celui qui fait git pull)" \
            "sudo useradd --create-home --shell /bin/bash $VFF_DEPLOY_USER"
    fi
    # vffbackup lit .env pour l'essai de restauration ; nginx (www-data) lit l'archive PMTiles et les vignettes.
    for u in vffbackup www-data "$VFF_DEPLOY_USER"; do
        utilisateur_existe "$u" || continue
        utilisateur_existe vff || continue
        if dans_groupe "$u" vff; then ok "$u dans le groupe vff"; elif appliquer; then
            usermod -aG vff "$u"; fait "$u ajouté au groupe vff"
        else
            manque "$u hors du groupe vff" "sudo usermod -aG vff $u"
        fi
    done
}

# --- 3. PostgreSQL ------------------------------------------------------------------------------------------
psql_admin() { runuser -u postgres -- psql -X -At -v ON_ERROR_STOP=1 "$@"; }

etape_postgresql() {
    exiger_root || { info "PostgreSQL non vérifié sans root"; return 0; }
    local unite
    unite="$(systemctl list-units --type=service --all --plain --no-legend 'postgresql*' 2>/dev/null | awk '{print $1}' | grep -E '^postgresql(@18-main)?\.service$' | head -n 1 || true)"
    unite_active "${unite:-postgresql.service}"
    local num
    num="$(psql_admin -c 'show server_version_num')"
    if [ "$num" -ge 180000 ]; then ok "PostgreSQL $num"; else manque "PostgreSQL $num, attendu 18" "apt install postgresql-18 postgresql-18-postgis-3"; fi
    if [ "$(psql_admin -c "select count(*) from pg_available_extensions where name in ('postgis','pg_trgm','unaccent')")" = 3 ]; then
        ok "extensions postgis, pg_trgm, unaccent disponibles"
    else
        manque "PostGIS, pg_trgm ou unaccent indisponible" "apt install postgresql-18-postgis-3 postgresql-contrib"
    fi
    local db_user db_name
    db_user="$(env_val DB_USERNAME)"; db_user="${db_user:-vff}"
    db_name="$(env_val DB_DATABASE)"; db_name="${db_name:-vff}"
    [[ "$db_user" =~ ^[a-z_][a-z0-9_]*$ ]] || die "DB_USERNAME invalide : $db_user"
    [[ "$db_name" =~ ^[a-z_][a-z0-9_]*$ ]] || die "DB_DATABASE invalide : $db_name"
    [[ "$db_name" != *_test ]] || die "DB_DATABASE finit par _test : ce n'est pas la base de production"
    if [ "$(psql_admin -c "select count(*) from pg_roles where rolname = '$db_user'")" = 1 ]; then ok "rôle $db_user"; elif appliquer; then
        psql_admin -c "create role \"$db_user\" login"
        fait "rôle $db_user (sans mot de passe : poser « \\password $db_user » dans psql, puis DB_PASSWORD)"
    else
        manque "rôle $db_user absent" "sudo -u postgres createuser --login $db_user ; sudo -u postgres psql -c '\\password $db_user'"
    fi
    if [ "$(psql_admin -c "select count(*) from pg_database where datname = '$db_name'")" = 1 ]; then ok "base $db_name"; elif appliquer; then
        runuser -u postgres -- createdb --owner="$db_user" --encoding=UTF8 "$db_name"
        fait "base $db_name"
    else
        manque "base $db_name absente" "sudo -u postgres createdb --owner=$db_user --encoding=UTF8 $db_name"
    fi
    local x
    for x in postgis pg_trgm unaccent; do
        if [ "$(psql_admin -d "$db_name" -c "select count(*) from pg_extension where extname = '$x'" 2>/dev/null || echo 0)" = 1 ]; then
            ok "extension $x dans $db_name"
        elif appliquer; then
            psql_admin -d "$db_name" -c "create extension if not exists $x"; fait "extension $x dans $db_name"
        else
            manque "extension $x absente de $db_name" "sudo -u postgres psql -d $db_name -c 'create extension if not exists $x'"
        fi
    done
    # Essai de restauration : vff_backup crée une base neuve depuis template1, qui doit déjà avoir les extensions
    # de la base (PostGIS n'est pas une extension « de confiance », vff_backup ne peut pas la créer).
    local ext
    while IFS= read -r ext; do
        [[ "$ext" =~ ^[a-z_][a-z0-9_]*$ ]] || continue
        if [ "$(psql_admin -d template1 -c "select count(*) from pg_extension where extname = '$ext'")" = 1 ]; then
            ok "extension $ext dans template1"
        elif appliquer; then
            psql_admin -d template1 -c "create extension if not exists \"$ext\" cascade"; fait "extension $ext dans template1"
        else
            manque "extension $ext absente de template1 (essai de restauration)" "sudo -u postgres psql -d template1 -c 'create extension if not exists $ext cascade'"
        fi
    done < <(psql_admin -d "$db_name" -c "select extname from pg_extension where extname <> 'plpgsql' order by extname" 2>/dev/null || true)
    if [ "$(psql_admin -c "select count(*) from pg_roles where rolname = 'vff_backup' and rolcreatedb")" = 1 ]; then ok "rôle vff_backup (CREATEDB)"; elif appliquer; then
        psql_admin -c "do \$\$ begin if not exists (select from pg_roles where rolname = 'vff_backup') then create role vff_backup login createdb; else alter role vff_backup createdb; end if; end \$\$"
        fait "rôle vff_backup (poser son mot de passe : « \\password vff_backup », puis /etc/vff/pgpass)"
    else
        manque "rôle vff_backup absent ou sans CREATEDB" "sudo -u postgres psql -c 'create role vff_backup login createdb'"
    fi
    if [ "$(psql_admin -c "select count(*) from pg_auth_members m join pg_roles r on r.oid = m.roleid join pg_roles u on u.oid = m.member where r.rolname = 'pg_read_all_data' and u.rolname = 'vff_backup'")" = 1 ]; then
        ok "vff_backup a pg_read_all_data"
    elif appliquer; then
        psql_admin -c "grant pg_read_all_data to vff_backup"; fait "pg_read_all_data accordé à vff_backup"
    else
        manque "vff_backup sans pg_read_all_data" "sudo -u postgres psql -c 'grant pg_read_all_data to vff_backup'"
    fi
    ecoute_locale 5432 PostgreSQL
}

# Un port ne doit écouter que sur la boucle locale.
ecoute_locale() {
    local port="${1:?}" nom="${2:?}" lignes
    lignes="$(ss -Hltn "sport = :$port" 2>/dev/null | awk '{print $4}' || true)"
    if [ -z "$lignes" ]; then manque "$nom n'écoute pas sur le port $port" ""; return 0; fi
    if printf '%s\n' "$lignes" | grep -vqE '^(127\.0\.0\.1|\[::1\]):'; then
        manque "$nom écoute hors de la boucle locale sur $port : $(printf '%s ' "$lignes")" "listen_addresses / bind = localhost"
    else
        ok "$nom n'écoute que sur la boucle locale ($port)"
    fi
}

# --- 4. Données : la base de production est chargée à la main (jamais par ce script) -----------------------------
etape_donnees() {
    exiger_root || { info "données non vérifiées sans root"; return 0; }
    local db_name n
    db_name="$(env_val DB_DATABASE)"; db_name="${db_name:-vff}"
    n="$(psql_admin -d "$db_name" -c "select count(*) from information_schema.tables where table_schema = 'public' and table_name = 'lines'" 2>/dev/null || echo 0)"
    if [ "$n" = 1 ] && [ "$(psql_admin -d "$db_name" -c 'select count(*) > 0 from public.lines')" = t ]; then
        ok "base $db_name chargée ($(psql_admin -d "$db_name" -c 'select count(*) from public.lines') lignes)"
    else
        manque "base $db_name vide : charger le dump de référence" "docs/MISE-EN-PRODUCTION.md, étape 4 (pg_restore dans une base neuve)"
    fi
}

# --- 5. Valkey ------------------------------------------------------------------------------------------------
etape_valkey() {
    exiger_root || true
    unite_active valkey-server.service
    local conf=/etc/valkey/valkey.conf
    if [ -r "$conf" ]; then
        grep -qE '^bind[[:space:]]+127\.0\.0\.1' "$conf" && ok "Valkey : bind 127.0.0.1" || manque "Valkey : bind absent" "bind 127.0.0.1 -::1 dans $conf"
        grep -qE '^requirepass[[:space:]]+[^[:space:]]{16,}' "$conf" && ok "Valkey : mot de passe" || manque "Valkey : requirepass absent ou court" "requirepass <openssl rand -hex 32> dans $conf, même valeur dans REDIS_PASSWORD"
        grep -qE '^maxmemory[[:space:]]+[0-9]' "$conf" && ok "Valkey : maxmemory" || manque "Valkey : maxmemory absent" "maxmemory 1gb dans $conf"
        grep -qE '^maxmemory-policy[[:space:]]+noeviction' "$conf" && ok "Valkey : noeviction (la file n'est jamais évincée)" || manque "Valkey : maxmemory-policy autre que noeviction" "maxmemory-policy noeviction dans $conf"
    else
        manque "$conf illisible ou absent" "apt install valkey-server"
    fi
    ecoute_locale 6379 Valkey
    local mdp port
    mdp="$(env_val REDIS_PASSWORD)"
    port="$(env_val REDIS_PORT)"; port="${port:-6379}"
    if [ -n "$mdp" ] && [ "$mdp" != null ] && REDISCLI_AUTH="$mdp" valkey-cli -h 127.0.0.1 -p "$port" ping 2>/dev/null | grep -qx PONG; then
        ok "Valkey répond avec REDIS_PASSWORD de .env"
    else
        manque "Valkey ne répond pas à REDIS_PASSWORD de .env" "REDIS_PASSWORD = requirepass de $conf"
    fi
}

# --- 6. Application : code, .env, dépendances, construction --------------------------------------------------
etape_application() {
    exiger_root || true
    [ -d "$VFF_DIR" ] || { manque "$VFF_DIR absent" "sudo -u $VFF_DEPLOY_USER git clone https://github.com/UnxWares/VFF-Project.git $VFF_DIR"; return 0; }
    if git -C "$VFF_DIR" rev-parse --git-dir >/dev/null 2>&1; then ok "$VFF_DIR est un dépôt git ($(git -C "$VFF_DIR" rev-parse --short HEAD))"; else manque "$VFF_DIR n'est pas un dépôt git" ""; fi
    local proprio
    proprio="$(stat -c %U "$VFF_DIR")"
    if [ "$proprio" = "$VFF_DEPLOY_USER" ]; then ok "code à $VFF_DEPLOY_USER"; else manque "$VFF_DIR appartient à $proprio, attendu $VFF_DEPLOY_USER (le compte vff ne doit pas pouvoir changer le code)" "sudo chown $VFF_DEPLOY_USER:vff $VFF_DIR"; fi
    if [ ! -f "$ENV_FICHIER" ]; then
        manque ".env absent" "sudo -u $VFF_DEPLOY_USER cp $VFF_DIR/.env.example $ENV_FICHIER, puis le remplir (docs/MISE-EN-PRODUCTION.md, étape 6)"
        return 0
    fi
    local droits
    droits="$(stat -c '%a %G' "$ENV_FICHIER")"
    if [ "$droits" = "640 vff" ]; then ok ".env en 640, groupe vff"; elif appliquer; then
        chgrp vff -- "$ENV_FICHIER"; chmod 640 -- "$ENV_FICHIER"; fait ".env en 640, groupe vff"
    else
        manque ".env en « $droits », attendu « 640 vff »" "sudo chgrp vff $ENV_FICHIER && sudo chmod 640 $ENV_FICHIER"
    fi
    env_egal APP_ENV production
    env_egal APP_DEBUG false
    env_rempli APP_KEY "sudo -u $VFF_DEPLOY_USER php artisan key:generate (une seule fois : changer la clé déconnecte tout le monde)"
    env_egal APP_URL "$BASE_URL"
    env_egal CACHE_STORE redis
    env_egal QUEUE_CONNECTION redis
    env_egal SESSION_DRIVER redis
    env_egal REDIS_CLIENT phpredis
    env_rempli REDIS_PASSWORD "requirepass de Valkey"
    env_egal CSP_ENFORCE true
    case "$(env_val LOG_STACK)" in *json-errors*) ok ".env LOG_STACK avec json-errors" ;; *) manque ".env LOG_STACK sans json-errors" "LOG_STACK=daily,json-errors" ;; esac
    case "$(env_val LOG_LEVEL)" in debug|"") manque ".env LOG_LEVEL debug ou vide" "LOG_LEVEL=warning" ;; *) ok ".env LOG_LEVEL $(env_val LOG_LEVEL)" ;; esac
    case "$(env_val MAIL_MAILER)" in log|array|"") manque ".env MAIL_MAILER $(env_val MAIL_MAILER) : aucun courriel ne part" "MAIL_MAILER=smtp et MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD de la messagerie d'UnxWares" ;; *) ok ".env MAIL_MAILER $(env_val MAIL_MAILER)" ;; esac
    case "$(env_val MAIL_FROM_ADDRESS)" in *@"$VFF_DOMAINE") ok ".env MAIL_FROM_ADDRESS $(env_val MAIL_FROM_ADDRESS)" ;; *) manque ".env MAIL_FROM_ADDRESS hors de $VFF_DOMAINE" "MAIL_FROM_ADDRESS=noreply@$VFF_DOMAINE" ;; esac
    if appliquer; then
        utilisateur_existe "$VFF_DEPLOY_USER" || die "compte $VFF_DEPLOY_USER absent (étape comptes)"
        comme_deploy composer install --no-dev --optimize-autoloader --no-interaction
        comme_deploy pnpm install --frozen-lockfile
        comme_deploy pnpm run build
        fait "dépendances et construction du front"
        # storage et bootstrap/cache : seuls dossiers que l'application écrit (ReadWritePaths des unités).
        chown -R vff:vff -- "$VFF_DIR/storage" "$VFF_DIR/bootstrap/cache"
        chmod -R g+rwX -- "$VFF_DIR/storage" "$VFF_DIR/bootstrap/cache"
        find "$VFF_DIR/storage" "$VFF_DIR/bootstrap/cache" -xdev -type d -exec chmod g+s {} +
        fait "storage et bootstrap/cache à vff (groupe en lecture pour nginx)"
        if [ ! -L "$VFF_DIR/public/storage" ]; then comme_deploy php artisan storage:link --no-interaction; fait "lien public/storage"; fi
    fi
    [ -f "$VFF_DIR/vendor/autoload.php" ] && ok "vendor installé" || manque "vendor absent" "sudo -u $VFF_DEPLOY_USER composer install --no-dev --optimize-autoloader"
    [ -f "$VFF_DIR/public/build/manifest.json" ] && ok "front construit (public/build)" || manque "front non construit" "sudo -u $VFF_DEPLOY_USER pnpm install --frozen-lockfile && sudo -u $VFF_DEPLOY_USER pnpm run build"
    [ -L "$VFF_DIR/public/storage" ] && ok "lien public/storage" || manque "lien public/storage absent" "sudo -u $VFF_DEPLOY_USER php artisan storage:link"
    [ "$(stat -c %U "$VFF_DIR/storage" 2>/dev/null)" = vff ] && ok "storage à vff" || manque "storage n'appartient pas à vff" "sudo chown -R vff:vff $VFF_DIR/storage $VFF_DIR/bootstrap/cache"
}

# --- 7. Sauvegarde quotidienne contrôlée (deploy/backup, docs/SAUVEGARDE.md) ---------------------------------
etape_sauvegarde() {
    exiger_root || { info "sauvegarde non vérifiée sans root"; return 0; }
    local f
    for f in lib.sh vff-backup.sh vff-restore-test.sh; do poser "$DEPOT/deploy/backup/$f" "/opt/vff/backup/$f" 0755; done
    if [ -d /etc/vff ]; then ok "/etc/vff"; elif appliquer; then install -d -m 0750 -o root -g vffbackup /etc/vff; fait "/etc/vff"; else manque "/etc/vff absent" "sudo install -d -m 0750 -o root -g vffbackup /etc/vff"; fi
    if [ -d /var/backups/vff ] && [ "$(stat -c '%U %a' /var/backups/vff)" = "vffbackup 700" ]; then ok "/var/backups/vff (vffbackup, 700)"; elif appliquer; then
        install -d -m 0700 -o vffbackup -g vffbackup /var/backups/vff; fait "/var/backups/vff"
    else
        manque "/var/backups/vff absent ou mal réglé" "sudo install -d -m 0700 -o vffbackup -g vffbackup /var/backups/vff"
    fi
    local envf=/etc/vff/backup.env
    if [ ! -f "$envf" ]; then
        if appliquer; then
            install -m 0600 -o vffbackup -g vffbackup -- "$DEPOT/deploy/backup/backup.env.example" "$envf"
            fait "$envf copié depuis l'exemple"
        fi
        manque "$envf à remplir (valeurs fictives de l'exemple)" "sudo -e $envf (docs/SAUVEGARDE.md, section 3)"
        return 0
    fi
    [ "$(stat -c '%U %a' "$envf")" = "vffbackup 600" ] && ok "$envf (vffbackup, 600)" || manque "$envf : propriétaire ou mode" "sudo chown vffbackup:vffbackup $envf && sudo chmod 600 $envf"
    if grep -qE 'CLE_FICTIVE|SECRET_FICTIF|\.invalid' "$envf"; then manque "$envf contient encore des valeurs fictives" "sudo -e $envf"; else ok "$envf sans valeur fictive"; fi
    if grep -qE '^APP_DIR=/srv/vff/current' "$envf"; then manque "$envf : APP_DIR=/srv/vff/current (ancien exemple)" "APP_DIR=$VFF_DIR"; fi
    for f in /etc/vff/pgpass /etc/vff/restic-password; do
        [ -f "$f" ] && [ "$(stat -c '%U %a' "$f")" = "vffbackup 600" ] && ok "$f (vffbackup, 600)" || manque "$f absent ou mal réglé" "sudo install -m 600 -o vffbackup -g vffbackup /dev/null $f, puis le remplir"
    done
    for f in vff-backup.service vff-backup.timer vff-restore-test.service vff-restore-test.timer; do
        poser "$DEPOT/deploy/systemd/$f" "/etc/systemd/system/$f" 0644
    done
    if appliquer; then systemctl daemon-reload; fi
    local dernier
    dernier="$(find /var/backups/vff/daily -maxdepth 1 -type f -name 'vff-*.dump' -mmin -1560 2>/dev/null | sort | tail -n 1 || true)"
    if [ -z "$dernier" ] && appliquer; then
        info "premier passage de la sauvegarde (peut durer : relever durée et taille)"
        systemctl start vff-backup.service || die "sauvegarde en échec : journalctl -u vff-backup.service -n 80"
        dernier="$(find /var/backups/vff/daily -maxdepth 1 -type f -name 'vff-*.dump' -mmin -1560 | sort | tail -n 1 || true)"
    fi
    if [ -n "$dernier" ] && [ -f "$dernier.sha256" ] && [ -f "$dernier.counts" ]; then
        ok "sauvegarde de moins de 26 h contrôlée : $(basename -- "$dernier") ($(du -h -- "$dernier" | cut -f1))"
    else
        manque "aucune sauvegarde contrôlée de moins de 26 h" "sudo systemctl start vff-backup.service ; journalctl -u vff-backup.service"
    fi
    unite_active vff-backup.timer
    unite_active vff-restore-test.timer
}

# --- 8. Migrations (après la sauvegarde : un dump existe avant tout changement de schéma) ---------------------
etape_migrations() {
    exiger_root || { info "migrations non vérifiées sans root"; return 0; }
    [ -f "$VFF_DIR/artisan" ] || { manque "application absente" "étape application"; return 0; }
    if artisan_vff migrate:status --pending=1 >/dev/null 2>&1; then ok "aucune migration en attente"; elif appliquer; then
        artisan_vff migrate --force
        fait "migrations"
    else
        manque "migrations en attente" "sudo -u vff php artisan migrate --force (après la sauvegarde)"
    fi
    if appliquer; then artisan_vff optimize; fait "caches de configuration, routes, vues et évènements (php artisan optimize)"; fi
    [ -f "$VFF_DIR/bootstrap/cache/config.php" ] && ok "configuration en cache" || manque "configuration pas en cache" "sudo -u vff php artisan optimize"
}

# --- 9. PHP-FPM ---------------------------------------------------------------------------------------------
etape_php_fpm() {
    exiger_root || { info "PHP-FPM non vérifié sans root"; return 0; }
    poser "$DEPOT/deploy/php-fpm/vff.conf" "/etc/php/$PHP_VERSION/fpm/pool.d/vff.conf" 0644
    if appliquer; then
        "php-fpm$PHP_VERSION" -t || die "configuration PHP-FPM refusée"
        systemctl reload-or-restart "php$PHP_VERSION-fpm.service"
    fi
    unite_active "php$PHP_VERSION-fpm.service"
    [ -S "/run/php/php$PHP_VERSION-fpm-vff.sock" ] && ok "socket /run/php/php$PHP_VERSION-fpm-vff.sock" || manque "socket PHP-FPM de VFF absent" "sudo systemctl reload php$PHP_VERSION-fpm"
}

# --- 10. Travailleur de file et unités de production ----------------------------------------------------------
etape_services() {
    exiger_root || { info "unités non vérifiées sans root"; return 0; }
    local f
    for f in vff-queue.service vff-reverb.service vff-schedule.service vff-schedule.timer vff.target; do
        poser "$DEPOT/deploy/systemd/production/$f" "/etc/systemd/system/$f" 0644
    done
    if appliquer; then
        systemctl daemon-reload
        systemd-analyze verify /etc/systemd/system/vff-queue.service /etc/systemd/system/vff-schedule.service \
            /etc/systemd/system/vff-reverb.service /etc/systemd/system/vff-schedule.timer || die "systemd-analyze refuse une unité"
    fi
    unite_active vff-queue.service
}

# --- 11. Planificateur (recalcul quotidien de l'ère 2030 et entretien) ---------------------------------------
etape_planificateur() {
    exiger_root || { info "planificateur non vérifié sans root"; return 0; }
    if artisan_vff schedule:list >/dev/null 2>&1; then ok "schedule:list répond"; else manque "schedule:list échoue" "sudo -u vff php artisan schedule:list"; fi
    if artisan_vff vff:refresh-current-era --dry-run >/dev/null 2>&1; then ok "vff:refresh-current-era --dry-run passe"; else manque "vff:refresh-current-era --dry-run échoue" "sudo -u vff php artisan vff:refresh-current-era --dry-run"; fi
    if crontab -u vff -l 2>/dev/null | grep -q 'schedule:run'; then manque "un cron lance aussi schedule:run : le minuteur systemd suffit (sinon tout tourne deux fois)" "sudo crontab -u vff -e"; fi
    unite_active vff-schedule.timer
    if [ -f "$VFF_DIR/storage/app/state/current-era-refresh.json" ]; then ok "recalcul de l'ère courante déjà passé"; else info "recalcul de l'ère courante pas encore passé (premier passage à 4 h 30)"; fi
}

# --- 12. Temps réel : Reverb et abonnements GraphQL ----------------------------------------------------------
etape_temps_reel() {
    exiger_root || true
    env_egal REALTIME_ENABLED true
    env_egal BROADCAST_CONNECTION reverb
    env_rempli REVERB_APP_ID "openssl rand -hex 8"
    env_rempli REVERB_APP_KEY "openssl rand -hex 16"
    env_rempli REVERB_APP_SECRET "openssl rand -hex 32"
    env_egal REVERB_SERVER_HOST 127.0.0.1
    env_egal REVERB_HOST 127.0.0.1
    env_egal REVERB_PORT 8080
    env_egal REVERB_SCHEME http
    env_egal REALTIME_CLIENT_HOST "$VFF_DOMAINE"
    local b
    b="$(env_val LIGHTHOUSE_BROADCASTER)"
    if [ -z "$b" ] || [ "$b" = reverb ]; then ok "abonnements GraphQL diffusés par Reverb"; else manque ".env LIGHTHOUSE_BROADCASTER=$b" "LIGHTHOUSE_BROADCASTER=reverb"; fi
    [ "$(id -u)" = 0 ] || return 0
    unite_active vff-reverb.service
    ecoute_locale 8080 Reverb
}

# --- 13. Certificat TLS ---------------------------------------------------------------------------------------
etape_certificat() {
    exiger_root || { info "certificat non vérifié sans root"; return 0; }
    local cert="/etc/letsencrypt/live/$VFF_DOMAINE/fullchain.pem"
    if [ ! -f "$cert" ]; then
        manque "certificat absent" "sudo certbot certonly --webroot -w /var/www/html -d $VFF_DOMAINE -d www.$VFF_DOMAINE (étape 13 : DNS posé d'abord)"
        return 0
    fi
    local san
    san="$(openssl x509 -in "$cert" -noout -ext subjectAltName 2>/dev/null || true)"
    if printf '%s' "$san" | grep -q "DNS:$VFF_DOMAINE" && printf '%s' "$san" | grep -q "DNS:www.$VFF_DOMAINE"; then ok "certificat couvre $VFF_DOMAINE et www"; else manque "certificat sans $VFF_DOMAINE ou www" "certbot certonly --webroot -w /var/www/acme -d $VFF_DOMAINE -d www.$VFF_DOMAINE --expand"; fi
    if openssl x509 -in "$cert" -noout -checkend 1209600 >/dev/null; then ok "certificat valable plus de 14 jours"; else manque "certificat expire dans moins de 14 jours" "sudo certbot renew"; fi
    unite_active certbot.timer
}

# Renouvellement : par le dossier du défi du site VFF (/var/www/acme), puis rechargement de nginx. Contrôlé à la
# fin de l'étape nginx, car le premier certificat s'obtient par le site par défaut (étape 13 du parcours).
renouvellement() {
    local renouv="/etc/letsencrypt/renewal/$VFF_DOMAINE.conf"
    if [ -f "$renouv" ] && grep -q '/var/www/acme' "$renouv"; then ok "renouvellement par /var/www/acme"; else manque "renouvellement hors de /var/www/acme" "sudo certbot reconfigure --cert-name $VFF_DOMAINE --webroot -w /var/www/acme --deploy-hook 'systemctl reload nginx'"; fi
    if [ -f "$renouv" ] && grep -q 'reload nginx' "$renouv"; then ok "nginx rechargé après renouvellement"; else manque "pas de rechargement de nginx après renouvellement" "sudo certbot reconfigure --cert-name $VFF_DOMAINE --webroot -w /var/www/acme --deploy-hook 'systemctl reload nginx'"; fi
}

# --- 14. nginx : site, en-têtes, proxy WebSocket, PMTiles, Commons ---------------------------------------------
etape_nginx() {
    exiger_root || { info "nginx non vérifié sans root"; return 0; }
    local f
    for f in vff-security-headers.conf vff-php.conf vff-tls.conf vff-reverb.conf; do
        poser "$DEPOT/deploy/nginx/snippets/$f" "/etc/nginx/snippets/$f" 0644
    done
    # Le site, avec le proxy WebSocket décommenté quand le temps réel est activé. Fichier préparé dans un dossier
    # temporaire privé, laissé sur place (aucune suppression ; /tmp est vidé au redémarrage).
    local prepare
    prepare="$(mktemp --tmpdir vff-nginx-site.XXXXXX.conf)"
    if [ "$(env_val REALTIME_ENABLED)" = true ]; then
        sed 's|^\([[:space:]]*\)# include /etc/nginx/snippets/vff-reverb.conf;|\1include /etc/nginx/snippets/vff-reverb.conf;|' \
            "$DEPOT/deploy/nginx/vff-project.org.conf" >"$prepare"
    else
        cat -- "$DEPOT/deploy/nginx/vff-project.org.conf" >"$prepare"
    fi
    poser "$prepare" /etc/nginx/conf.d/vff-project.org.conf 0644
    local d
    for d in /etc/nginx/vff-optional /var/www/acme; do
        if [ -d "$d" ]; then ok "$d"; elif appliquer; then install -d -m 0755 "$d"; fait "$d"; else manque "$d absent" "sudo install -d -m 0755 $d"; fi
    done
    if nginx -t >/dev/null 2>&1; then ok "nginx -t"; else
        manque "nginx -t refuse la configuration" "sudo nginx -t (certificat présent ? socket PHP-FPM ?)"
        return 0
    fi
    if appliquer; then systemctl reload-or-restart nginx.service; fait "nginx rechargé"; fi
    unite_active nginx.service
    renouvellement
}

# --- 15. Fond de carte PMTiles servi par nginx ------------------------------------------------------------------
etape_fond_de_carte() {
    local archive dir
    archive="$(env_val VFF_BASEMAP_ARCHIVE)"; archive="${archive:-france-20261120.pmtiles}"
    dir="$(env_val VFF_BASEMAP_DIRECTORY)"; dir="${dir:-$VFF_DIR/storage/app/data/pmtiles}"
    [[ "$archive" =~ ^[a-z0-9-]+\.pmtiles$ ]] || manque "VFF_BASEMAP_ARCHIVE « $archive » hors du motif servi par nginx" "nom en [a-z0-9-]+.pmtiles"
    if [ -f "$dir/$archive" ] && [ "$(stat -c %s "$dir/$archive")" -gt 1000000000 ]; then ok "archive $archive ($(du -h -- "$dir/$archive" | cut -f1))"; else
        manque "archive $dir/$archive absente ou incomplète" "copier l'archive (docs/MISE-EN-PRODUCTION.md, étape 15) ou scripts/basemap-pmtiles.sh"
    fi
    env_egal VFF_BASEMAP_ACCEL_REDIRECT /_basemap/
    if grep -q "alias $dir/;" "$DEPOT/deploy/nginx/vff-project.org.conf"; then ok "alias nginx /_basemap/ = $dir/"; else manque "alias de /_basemap/ différent de $dir/" "adapter deploy/nginx/vff-project.org.conf"; fi
    info "fond libre en ligne sur /maps : VFF_BASEMAP_ONLINE=$(env_val VFF_BASEMAP_ONLINE)"
    if en_ligne; then
        [ "$(http_code -r 0-16383 "$BASE_URL/basemap/$archive")" = 206 ] && ok "plage de l'archive : 206" || manque "GET /basemap/$archive avec Range ne répond pas 206" "journal nginx et storage/logs"
        [ "$(http_code "$BASE_URL/basemap/$archive")" = 416 ] && ok "archive entière refusée : 416" || manque "GET sans Range ne répond pas 416" ""
        [ "$(http_code "$BASE_URL/_basemap/$archive")" = 404 ] && ok "/_basemap/ interne : 404" || manque "/_basemap/ servi directement" "location /_basemap/ doit rester internal"
    fi
}

# --- 16. Relais des vignettes de Commons -------------------------------------------------------------------------
etape_commons() {
    env_egal VFF_COMMONS_PROXY true
    env_egal VFF_COMMONS_PROXY_ACCEL_REDIRECT /_commons/
    local cache
    cache="$(env_val VFF_COMMONS_PROXY_CACHE_PATH)"; cache="${cache:-$VFF_DIR/storage/app/cache/commons}"
    if [ -d "$cache" ]; then ok "cache des vignettes $cache"; elif appliquer; then
        install -d -m 2770 -o vff -g vff -- "$cache"; fait "cache des vignettes $cache"
    else
        manque "cache des vignettes absent" "sudo install -d -m 2770 -o vff -g vff $cache"
    fi
    [ "$(id -u)" = 0 ] || return 0
    if appliquer && [ "$(env_val VFF_COMMONS_PROXY)" = true ]; then
        artisan_vff vff:commons-allowlist --refresh; fait "liste des fichiers cités (vff:commons-allowlist --refresh)"
    fi
    if artisan_vff vff:commons-allowlist >/dev/null 2>&1; then ok "vff:commons-allowlist (état) répond"; else manque "liste des fichiers cités absente" "sudo -u vff php artisan vff:commons-allowlist --refresh"; fi
    if en_ligne; then
        [ "$(http_code "$BASE_URL/_commons/x")" = 404 ] && ok "/_commons/ interne : 404" || manque "/_commons/ servi directement" "location /_commons/ doit rester internal"
        [ "$(http_code "$BASE_URL/media/commons/7/79/CFA-Morlaix.jpg")" = 404 ] && ok "fichier non cité : 404" || manque "le relais sert un fichier non cité" "docs/DEPLOIEMENT-NGINX.md, Relais des vignettes"
    fi
}

# --- 17. Suivi : Kener (disponibilité) et GlitchTip (erreurs) ----------------------------------------------------
etape_suivi() {
    local jeton
    jeton="$(env_val HEALTH_TOKEN)"
    if [ "${#jeton}" -ge 32 ]; then ok ".env HEALTH_TOKEN (${#jeton} caractères)"; else manque ".env HEALTH_TOKEN absent ou court" "HEALTH_TOKEN=\$(openssl rand -hex 32), même valeur dans VFF_HEALTH_TOKEN de Kener"; fi
    env_rempli SENTRY_LARAVEL_DSN "DSN du projet VFF dans GlitchTip (https://<clé>@erreurs.$VFF_DOMAINE/<projet>)"
    env_rempli APP_VERSION "APP_VERSION=\$(git -C $VFF_DIR describe --tags --always)"
    en_ligne || return 0
    [ "$(http_code "$BASE_URL/up")" = 200 ] && ok "/up : 200" || manque "/up ne répond pas 200" "journal nginx, PHP-FPM, storage/logs"
    [ "$(http_code "$BASE_URL/up/details")" = 401 ] && ok "/up/details sans jeton : 401" || manque "/up/details sans jeton ne répond pas 401" "HEALTH_TOKEN puis php artisan config:cache"
    if [ -n "$jeton" ]; then
        local corps
        corps="$(curl -fsS -m 15 -H "Authorization: Bearer $jeton" -H 'Accept: application/json' "$BASE_URL/up/details" 2>/dev/null || true)"
        case "$corps" in
            *'"status":"ok"'*) ok "/up/details : ok" ;;
            *) manque "/up/details pas à ok : $(printf '%s' "$corps" | head -c 300)" "lire les contrôles (database, valkey, queue, disk, era, scheduler)" ;;
        esac
    fi
    local h
    for h in status erreurs; do
        case "$(http_code "https://$h.$VFF_DOMAINE/")" in
            2??|3??) ok "https://$h.$VFF_DOMAINE répond" ;;
            *) manque "https://$h.$VFF_DOMAINE injoignable" "docs/MISE-EN-PRODUCTION.md, étape 17" ;;
        esac
    done
    if [ "$(id -u)" = 0 ] && [ -r /etc/vff/backup.env ]; then
        grep -qE '^HEARTBEAT_URL=https://status\.' /etc/vff/backup.env && ok "battement de la sauvegarde vers Kener" || manque "HEARTBEAT_URL de la sauvegarde hors de Kener" "moniteur push de Kener, adresse dans /etc/vff/backup.env"
    fi
}

# --- 18. En-têtes de sécurité et surface publique ----------------------------------------------------------------
entete_unique() {
    local entetes="${1?}" nom="${2:?}" ou="${3:?}" n
    n="$(printf '%s\n' "$entetes" | grep -ci "^$nom:" || true)"
    if [ "$n" = 1 ]; then ok "$ou : $nom une fois"; else manque "$ou : $nom présent $n fois (attendu 1)" "deploy/nginx/snippets/vff-security-headers.conf, App\\Http\\Middleware\\SecurityHeaders"; fi
}

etape_entetes() {
    en_ligne || { info "en-têtes non vérifiés (--hors-ligne)"; return 0; }
    local page ent h
    for page in / /robots.txt; do
        ent="$(curl -sI -m 15 "$BASE_URL$page" 2>/dev/null | tr -d '\r' || true)"
        for h in Strict-Transport-Security X-Content-Type-Options X-Frame-Options Referrer-Policy Permissions-Policy; do
            entete_unique "$ent" "$h" "$page"
        done
        if printf '%s\n' "$ent" | grep -qi '^server: nginx/'; then manque "$page : version de nginx affichée" "server_tokens off"; fi
    done
    ent="$(curl -sI -m 15 "$BASE_URL/" 2>/dev/null | tr -d '\r' || true)"
    if printf '%s\n' "$ent" | grep -qi '^content-security-policy:'; then ok "CSP bloquante sur /"; else manque "CSP non bloquante (Report-Only ou absente)" "CSP_ENFORCE=true puis php artisan config:cache"; fi
    [ "$(http_code "http://$VFF_DOMAINE/")" = 301 ] && ok "HTTP vers HTTPS : 301" || manque "http:// ne redirige pas en 301" ""
    [ "$(http_code "https://www.$VFF_DOMAINE/")" = 301 ] && ok "www vers le nom nu : 301" || manque "www ne redirige pas en 301" ""
    [ "$(http_code "$BASE_URL/.env")" = 404 ] && ok "/.env : 404" || manque "/.env ne répond pas 404" "location ~ /\\. du site"
    case "$(http_code "$BASE_URL/apps/x/events")" in 401|403|404|405) ok "API HTTP de Reverb non publique" ;; *) manque "/apps/ répond : l'API de Reverb ne doit pas être publique" "seul /app/ passe à Reverb" ;; esac
    local cle jeton
    cle="$(env_val REVERB_APP_KEY)"; jeton="$(env_val HEALTH_TOKEN)"
    if [ "$(id -u)" = 0 ] && [ -n "$cle" ] && [ -n "$jeton" ]; then
        if HEALTH_TOKEN="$jeton" REVERB_APP_KEY="$cle" VFF_DIR="$VFF_DIR" "$DEPOT/deploy/bin/verify-production.sh" "$BASE_URL"; then
            ok "deploy/bin/verify-production.sh"
        else
            manque "deploy/bin/verify-production.sh signale des échecs (ci-dessus)" ""
        fi
    fi
}

# --- 19. DNS (posé par Baptiste chez le bureau d'enregistrement) -------------------------------------------------
dns() { dig +short +time=3 +tries=1 "$@" 2>/dev/null || true; }

etape_dns() {
    en_ligne || { info "DNS non vérifié (--hors-ligne)"; return 0; }
    local nom v
    for nom in "$VFF_DOMAINE" "www.$VFF_DOMAINE" "status.$VFF_DOMAINE" "erreurs.$VFF_DOMAINE"; do
        v="$(dns A "$nom" | tail -n 1)"
        if [ -z "$v" ]; then manque "$nom : aucun A" "enregistrement A vers le serveur"; continue; fi
        if [ -n "${SERVEUR_IPV4:-}" ] && [ "$nom" != "status.$VFF_DOMAINE" ] && [ "$v" != "$SERVEUR_IPV4" ]; then
            manque "$nom : A=$v, attendu $SERVEUR_IPV4" "corriger l'enregistrement A"
        else
            ok "$nom : A=$v"
        fi
        if [ -n "${SERVEUR_IPV6:-}" ] && [ "$nom" != "status.$VFF_DOMAINE" ]; then
            [ "$(dns AAAA "$nom" | tail -n 1)" = "$SERVEUR_IPV6" ] && ok "$nom : AAAA" || manque "$nom : AAAA différent de $SERVEUR_IPV6" ""
        fi
    done
    v="$(dns MX "$VFF_DOMAINE")"
    if [ -n "$v" ]; then ok "MX : $(printf '%s' "$v" | tr '\n' ' ')"; else manque "MX absent : contact@ et privacy@$VFF_DOMAINE ne reçoivent rien" "Baptiste : MX de la messagerie d'UnxWares (étape 19)"; fi
    dns TXT "$VFF_DOMAINE" | grep -q 'v=spf1' && ok "SPF" || manque "SPF absent" "TXT « v=spf1 … -all » (étape 19)"
    dns TXT "_dmarc.$VFF_DOMAINE" | grep -q 'v=DMARC1' && ok "DMARC" || manque "DMARC absent" "TXT _dmarc « v=DMARC1; p=quarantine; … » (étape 19)"
    dns CAA "$VFF_DOMAINE" | grep -q 'letsencrypt.org' && ok "CAA letsencrypt.org" || manque "CAA absent" "CAA 0 issue \"letsencrypt.org\" (étape 19)"
}

# --- Déroulé ------------------------------------------------------------------------------------------------------
printf 'VFF : mise en production, mode %s, dépôt %s, application %s\n' "$MODE" "$DEPOT" "$VFF_DIR"
[ "$HORS_LIGNE" = 1 ] && info "hors ligne : aucune requête HTTP ni DNS"
for etape in "${ETAPES[@]}"; do
    [ -z "$ETAPE_SEULE" ] || [ "$etape" = "$ETAPE_SEULE" ] || continue
    ETAPE_EN_COURS="$etape"
    titre "$etape"
    "etape_${etape//-/_}"
done

printf '\n'
if [ "$MANQUES" -eq 0 ]; then
    printf 'Tout est en place (%s).\n' "${ETAPE_SEULE:-toutes les étapes}"
    exit 0
fi
printf '%s point(s) manquant(s) : voir les lignes MANQUE ci-dessus et docs/MISE-EN-PRODUCTION.md.\n' "$MANQUES"
exit 1
