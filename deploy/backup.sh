#!/usr/bin/env bash
# Dump only the oc-support MySQL database.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
# shellcheck source=compose.sh
source "${ROOT}/deploy/compose.sh"

DEST="${1:-/var/backups/oc-support}"
mkdir -p "$DEST"
STAMP="$(date +%F-%H%M)"
TARGET="${DEST}/oc-support-${STAMP}.sql.gz"

# MYSQL_PASSWORD is already in the mysql container. MYSQL_PWD is set only for
# this mysqldump process so the password is not placed on its command line.
# --no-tablespaces avoids the PROCESS privilege the app user does not have.
"${COMPOSE[@]}" exec -T mysql \
    sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysqldump --single-transaction --quick --no-tablespaces -u"$MYSQL_USER" "$MYSQL_DATABASE"' \
    | gzip > "$TARGET"

echo "Wrote ${TARGET}"
