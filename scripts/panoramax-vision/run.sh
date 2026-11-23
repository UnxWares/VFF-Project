#!/usr/bin/env bash
# Note « la gare est visible » des vignettes Panoramax (score.py), dans le conteneur localhost/vff-rail:1
# (torch déjà présent), processeur seulement par défaut : 4 cœurs, 1,5 Gio de mémoire.
#   scripts/panoramax-vision/run.sh            note les vignettes de storage/app/data/panoramax/vision/thumbs
#   scripts/panoramax-vision/run.sh install    installe open_clip, ftfy et regex (une fois, environ 7 Mo)
# Cache borné hors dépôt (.nobackup) : ~/.local/share/vff/panoramax-vision (bibliothèques 7 Mo, poids 600 Mo).
# Le GPU (VISION_GPU=1) n'est utilisé que si aucun autre modèle ne l'occupe (verrou de rail-extraction).
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$HERE/../.." && pwd)"
IMAGE="${VFF_RAIL_IMAGE:-localhost/vff-rail:1}"
CACHE="${VISION_CACHE:-$HOME/.local/share/vff/panoramax-vision}"
DATA="$ROOT/storage/app/data/panoramax/vision"
mkdir -p "$CACHE/pylib" "$CACHE/hf" "$DATA/thumbs"
touch "$CACHE/.nobackup" "$DATA/.nobackup"

if [ "${1:-}" = "install" ]; then
    exec podman run --rm --name vff-panoramax-vision-pip --cpus 2 --memory 1g --security-opt label=disable \
        -v "$CACHE":/cache "$IMAGE" pip install --no-cache-dir --target /cache/pylib --no-deps \
        open_clip_torch==3.2.0 ftfy==6.3.1 regex==2026.11.19
fi

GPU=()
ENVS=(-e VISION_PYLIB=/cache/pylib -e HF_HOME=/cache/hf -e HOME=/tmp -e "VISION_THREADS=${VISION_THREADS:-4}")
if [ "${VISION_GPU:-0}" = "1" ]; then
    if [ -f "$ROOT/storage/app/ml/rail-extraction/.lancement-autorise" ] \
        && podman ps --format '{{.Image}}' | grep -q 'vff-rail'; then
        echo "run.sh : le GPU sert à un autre modèle, passe sur processeur." >&2
    else
        GPU=(--device nvidia.com/gpu=all)
        ENVS+=(-e VISION_DEVICE=cuda)
    fi
fi
# Poids déjà là : aucune requête au dépôt de modèles.
[ -d "$CACHE/hf/hub/models--laion--CLIP-ViT-B-16-DataComp.XL-s13B-b90K" ] && ENVS+=(-e HF_HUB_OFFLINE=1)

exec podman run --rm --name vff-panoramax-vision "${GPU[@]}" --security-opt label=disable \
    --cpus "${VISION_CPUS:-4}" --memory "${VISION_MEMORY:-1536m}" --memory-swap "${VISION_MEMORY:-1536m}" \
    "${ENVS[@]}" -v "$HERE":/app:ro -v "$CACHE":/cache -v "$DATA":/data -w /data \
    "$IMAGE" python -I /app/score.py /data/thumbs /data/scores.json
