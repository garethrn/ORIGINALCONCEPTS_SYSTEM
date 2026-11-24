# Sourced by the deploy scripts. Do not run this file directly.
# Sets COMPOSE to the oc-support project and the same Compose files for
# build, up, migrate, and backup. Adds docker-compose.override.yml only
# when that file is present in the repository root (the current directory).

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    echo "Source this file from a deploy script. Do not run it directly." >&2
    exit 1
fi

COMPOSE=(docker compose -p oc-support -f docker-compose.prod.yml)
if [[ -f docker-compose.override.yml ]]; then
    COMPOSE+=(-f docker-compose.override.yml)
fi
