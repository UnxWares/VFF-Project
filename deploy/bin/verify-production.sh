#!/usr/bin/env bash
# Vérification après déploiement des processus de production (docs/DEPLOIEMENT-SERVICES.md).
# Lecture seule : ne modifie rien. À lancer sur le serveur.
#   HEALTH_TOKEN=... REVERB_APP_KEY=... deploy/bin/verify-production.sh [https://vff-project.org]
set -uo pipefail

base="${1:-https://vff-project.org}"
: "${HEALTH_TOKEN:?HEALTH_TOKEN est requis (jeton de /up/details)}"
: "${REVERB_APP_KEY:?REVERB_APP_KEY est requis}"
app_dir="${VFF_DIR:-/srv/vff}"
fail=0

ok() { printf 'OK   %s\n' "$1"; }
ko() { printf 'ECHEC %s\n' "$1"; fail=1; }

# 1. Unités systemd
for unit in vff-reverb.service vff-queue.service vff-schedule.timer; do
    if systemctl is-active --quiet "$unit"; then ok "unité $unit active"; else ko "unité $unit inactive"; fi
done

# 2. Santé de l'application (/up/details) : base, Valkey, file, disque, planificateur
body="$(curl -fsS --max-time 15 -H "Authorization: Bearer ${HEALTH_TOKEN}" -H 'Accept: application/json' "${base}/up/details")" \
    && ok "/up/details répond" || { ko "/up/details injoignable ou refusé"; body=''; }

if [ -n "$body" ]; then
    for check in database valkey queue scheduler; do
        status="$(printf '%s' "$body" | php -r '$d = json_decode(stream_get_contents(STDIN), true); echo $d["checks"][$argv[1]]["status"] ?? "absent";' "$check")"
        case "$status" in
            ok) ok "contrôle $check : ok" ;;
            *) ko "contrôle $check : $status" ;;
        esac
    done
fi

# 3. WebSocket : la poignée de main doit répondre 101 par nginx
code="$(curl --http1.1 -s -o /dev/null -m 5 -w '%{http_code}' \
    -H 'Connection: Upgrade' -H 'Upgrade: websocket' -H 'Sec-WebSocket-Version: 13' \
    -H 'Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==' -H "Origin: ${base}" \
    "${base}/app/${REVERB_APP_KEY}")"
# curl sort en erreur (timeout) une fois la connexion ouverte : le code HTTP reste lisible.
if [ "$code" = "101" ]; then ok "WebSocket /app/ : 101"; else ko "WebSocket /app/ : ${code} (attendu 101)"; fi

# 4. L'API HTTP de Reverb ne doit PAS être publique
pub="$(curl -s -o /dev/null -m 5 -w '%{http_code}' "${base}/apps/x/events")"
case "$pub" in
    404|403|401|405) ok "API HTTP de Reverb non exposée (${pub})" ;;
    *) ko "/apps/ répond ${pub} : l'API de Reverb ne doit pas être publique" ;;
esac

# 5. File : aucun travail échoué récent, travailleur vivant
if [ -d "$app_dir" ]; then
    failed="$(cd "$app_dir" && php artisan queue:monitor redis:default --max=1000 --no-interaction 2>&1 | head -n 3)"
    printf 'INFO file : %s\n' "$failed"
fi
if journalctl -u vff-queue.service --since '-10min' --no-pager -q 2>/dev/null | grep -q -i 'fatal'; then
    ko "erreur fatale récente dans le journal de vff-queue"
else
    ok "journal de vff-queue sans erreur fatale (10 min)"
fi

# 6. Planificateur : dernier passage récent
last="$(systemctl show vff-schedule.service -p ExecMainStartTimestampMonotonic --value 2>/dev/null)"
[ -n "$last" ] && [ "$last" != "0" ] && ok "vff-schedule.service a déjà tourné" || ko "vff-schedule.service n'a jamais tourné"

if [ "$fail" -eq 0 ]; then echo "Tout est en ordre."; else echo "Des contrôles ont échoué."; fi
exit "$fail"
