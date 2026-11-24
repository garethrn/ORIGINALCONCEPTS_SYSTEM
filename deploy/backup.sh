#!/usr/bin/env bash
# Dump only the oc-support MySQL database.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
DEST="${1:-/var/backups/oc-support}"
mkdir -p "$DEST"
STAMP="$(date +%F-%H%M)"
TARGET="${DEST}/oc-support-${STAMP}.sql.gz"

docker compose -p oc-support -f docker-compose.prod.yml exec -T mysql \
    sh -c 'mysqldump --single-transaction --quick -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
    | gzip > "$TARGET"

echo "Wrote ${TARGET}"
