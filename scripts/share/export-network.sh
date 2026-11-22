#!/usr/bin/env bash
# Régénère scripts/share/network.json (réseau en service simplifié) depuis la base de développement, en
# lecture seule, puis l'image de partage : scripts/share/build-share-image.mjs.
#   scripts/share/export-network.sh
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ENV_FILE="$ROOT/.env"
DB_PASSWORD="$(grep -E '^DB_PASSWORD=' "$ENV_FILE" | cut -d= -f2-)"
export PGPASSWORD="$DB_PASSWORD"
podman exec -i -e PGPASSWORD vff-db psql -h 127.0.0.1 -U vff -d vff -At -v ON_ERROR_STOP=1 \
    < "$ROOT/scripts/share/network.sql" > "$ROOT/scripts/share/network.json"
echo "scripts/share/network.json : $(wc -c < "$ROOT/scripts/share/network.json") octets"
