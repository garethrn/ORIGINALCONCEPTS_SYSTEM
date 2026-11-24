#!/usr/bin/env bash
# Update only the oc-support compose project. It does not touch other containers.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
FILE="docker-compose.prod.yml"
PROJECT="oc-support"

if ! grep -q '^name: oc-support$' "$FILE"; then
    echo "Refusing to run: ${FILE} is not the oc-support project." >&2
    exit 1
fi

if grep -Eq '0\.0\.0\.0:' "$FILE"; then
    echo "Refusing to run: ${FILE} publishes a port on all interfaces." >&2
    exit 1
fi

COMPOSE=(docker compose -p "$PROJECT" -f "$FILE")

"${COMPOSE[@]}" build
"${COMPOSE[@]}" up -d
"${COMPOSE[@]}" exec -T app php artisan migrate --force
"${COMPOSE[@]}" exec -T app php artisan optimize
"${COMPOSE[@]}" exec -T app php artisan queue:restart
"${COMPOSE[@]}" ps
