#!/usr/bin/env bash
# Prépare l'archive PMTiles du fond de carte libre de VFF : extrait d'une construction quotidienne de
# Protomaps (données OpenStreetMap, ODbL) la zone décrite par database/data/basemap/<zone>.geojson, jusqu'au
# zoom demandé, dans storage/app/data/pmtiles/<zone>-<AAAAMMJJ>.pmtiles. Rien n'est installé sur la machine :
# l'outil pmtiles tourne dans son image de conteneur (Podman, sinon Docker). Marche à suivre complète :
# docs/FOND-DE-CARTE.md.
#
#   scripts/basemap-pmtiles.sh                      France et abords, zooms 0 à 15, dernière construction
#   scripts/basemap-pmtiles.sh normandie 15         zone d'essai
#   scripts/basemap-pmtiles.sh france 14 20261120   zoom 14, construction du 20/11/2026
#   DRY_RUN=1 scripts/basemap-pmtiles.sh            mesure seulement (taille, nombre de tuiles)
#
# Mesures du 21/11/2026 (construction 20261120, 4 téléchargements parallèles, environ 45 Mo/s) :
# France et abords, zooms 0 à 15 : 1,32 million de tuiles, 6,2 Go, 69 s ; zooms 0 à 14 : 2,9 Go.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENGINE="${CONTAINER_ENGINE:-$(command -v podman || command -v docker)}"
IMAGE="${VFF_PMTILES_IMAGE:-docker.io/protomaps/go-pmtiles:v1.31.2}"
BUILDS="https://build.protomaps.com"
METADATA="https://build-metadata.protomaps.dev/builds.json"

REGION="${1:-france}"
MAXZOOM="${2:-15}"
DATE="${3:-}"

[[ "$REGION" =~ ^[a-z0-9-]{1,40}$ ]] || { echo "Zone invalide : $REGION" >&2; exit 2; }
[[ "$MAXZOOM" =~ ^[0-9]{1,2}$ ]] && (( MAXZOOM <= 15 )) || { echo "Zoom maximal de 0 à 15" >&2; exit 2; }

REGION_DIR="$ROOT/database/data/basemap"
[[ -f "$REGION_DIR/$REGION.geojson" ]] || { echo "Zone inconnue : $REGION_DIR/$REGION.geojson" >&2; exit 2; }

if [[ -z "$DATE" ]]; then
    DATE="$(curl -fsS --max-time 30 "$METADATA" \
        | python3 -I -c 'import json, sys; print(json.load(sys.stdin)[-1]["key"].removesuffix(".pmtiles"))')"
fi
[[ "$DATE" =~ ^[0-9]{8}$ ]] || { echo "Date de construction invalide : $DATE" >&2; exit 2; }

OUT_DIR="$ROOT/storage/app/data/pmtiles"
mkdir -p "$OUT_DIR"
# Données téléchargées, régénérables : jamais sauvegardées.
[[ -f "$OUT_DIR/.nobackup" ]] || touch "$OUT_DIR/.nobackup"
NAME="$REGION-$DATE.pmtiles"

if [[ -e "$OUT_DIR/$NAME" ]]; then
    echo "Existe déjà : $OUT_DIR/$NAME (rien n'est écrasé)" >&2
    exit 1
fi

# Place disque : 10 Gio libres au moins avant d'écrire (la France au zoom 15 pèse 6,2 Go).
if [[ -z "${DRY_RUN:-}" ]]; then
    avail=$(df --output=avail -B1 "$OUT_DIR" | tail -1)
    if (( avail < 10 * 1024 * 1024 * 1024 )); then
        echo "Moins de 10 Gio libres dans $OUT_DIR : abandon" >&2
        exit 3
    fi
fi

ARGS=(extract "$BUILDS/$DATE.pmtiles" "/out/$NAME" "--region=/region/$REGION.geojson"
    "--maxzoom=$MAXZOOM" --download-threads=4)
[[ -n "${DRY_RUN:-}" ]] && ARGS+=(--dry-run)

start=$(date +%s)
"$ENGINE" run --rm \
    -v "$REGION_DIR":/region:ro,Z \
    -v "$OUT_DIR":/out:Z \
    "$IMAGE" "${ARGS[@]}"
echo "Durée : $(( $(date +%s) - start )) s"

if [[ -z "${DRY_RUN:-}" ]]; then
    "$ENGINE" run --rm -v "$OUT_DIR":/out:ro,Z "$IMAGE" verify "/out/$NAME"
    ls -l "$OUT_DIR/$NAME"
    echo "Pour l'employer : VFF_BASEMAP_ARCHIVE=$NAME (fichier .env), puis vider le cache de configuration."
fi
