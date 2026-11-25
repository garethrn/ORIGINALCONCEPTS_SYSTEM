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

port_files=("$FILE")
if [[ -f docker-compose.override.yml ]]; then
    port_files+=(docker-compose.override.yml)
fi
if grep -Eq '0\.0\.0\.0:' "${port_files[@]}"; then
    echo "Refusing to run: a compose file publishes a port on all interfaces." >&2
    exit 1
fi

# shellcheck source=compose.sh
source "${ROOT}/deploy/compose.sh"

if [[ "${COMPOSE[*]}" != *" -p ${PROJECT} "* ]]; then
    echo "Refusing to run: compose project is not ${PROJECT}." >&2
    exit 1
fi

"${COMPOSE[@]}" build
"${COMPOSE[@]}" up -d
"${COMPOSE[@]}" exec -T app php artisan migrate --force
"${COMPOSE[@]}" exec -T app php artisan optimize
"${COMPOSE[@]}" exec -T app php artisan queue:restart
"${COMPOSE[@]}" ps
