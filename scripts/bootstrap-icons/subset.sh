#!/usr/bin/env bash
# Sous-ensemble de la police bootstrap-icons : seulement les icônes que le site utilise (131 Ko pour toute la
# police, quelques Ko pour la centaine d'icônes du site). Rien n'est installé sur la machine :
#   1. recensement et feuille réduite (resources/css/bootstrap-icons-subset.css) par Node, dans le conteneur
#      de bin/pnpm ;
#   2. police réduite (resources/fonts/bootstrap-icons-subset.woff2) par pyftsubset (fontTools), dans un
#      conteneur Python jetable.
# À relancer après l'ajout d'une icône dans le code ou une mise à jour du paquet bootstrap-icons : le test
# tests/js/bootstrap-icons-subset.test.js échoue tant qu'une icône utilisée manque à la police réduite.
#
# Usage : scripts/bootstrap-icons/subset.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ENGINE="${CONTAINER_ENGINE:-$(command -v podman || command -v docker)}"
PYTHON_IMAGE="${VFF_PYTHON_IMAGE:-docker.io/library/python:3.13-alpine}"
FONTTOOLS="fonttools==4.60.1"
BROTLI="brotli==1.1.0"

if [[ "$ENGINE" == *podman ]]; then
    USER_OPTS=(--userns=keep-id --security-opt label=disable)
else
    USER_OPTS=(--user "$(id -u):$(id -g)")
fi

cd "$ROOT"
UNICODES="$(bin/pnpm exec node scripts/bootstrap-icons/subset-css.mjs /app | tr -d '\r' | tail -n 1)"
if [[ ! "$UNICODES" =~ ^U\+[0-9A-F]+(,U\+[0-9A-F]+)*$ ]]; then
    echo "subset.sh : liste de points de code inattendue : $UNICODES" >&2
    exit 1
fi
# Feuille générée mise à la forme du dépôt (bin/pnpm lint la contrôle comme les autres).
bin/pnpm exec prettier --log-level warn --write resources/css/bootstrap-icons-subset.css

"$ENGINE" run --rm "${USER_OPTS[@]}" -v "$ROOT":/app -w /app -e HOME=/tmp "$PYTHON_IMAGE" sh -c "
    pip install --quiet --disable-pip-version-check --no-warn-script-location --user $FONTTOOLS $BROTLI &&
    python -m fontTools.subset node_modules/bootstrap-icons/font/fonts/bootstrap-icons.woff2 \
        --unicodes='$UNICODES' --flavor=woff2 --layout-features='' --no-hinting --name-IDs='*' \
        --output-file=resources/fonts/bootstrap-icons-subset.woff2"

echo "Police réduite : $(stat -c %s resources/fonts/bootstrap-icons-subset.woff2) octets, $(tr ',' '\n' <<<"$UNICODES" | wc -l) icônes."
