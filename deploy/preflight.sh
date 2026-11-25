#!/usr/bin/env bash
# Read-only checks before the first deploy. This script does not change the server.
set -u

echo "== Listening TCP ports =="
if command -v ss >/dev/null 2>&1; then
    ss -lnt
else
    netstat -lnt || true
fi

echo
echo "== Ports 80, 443, and 8085 =="
if command -v ss >/dev/null 2>&1; then
    ss -lnt | awk 'NR == 1 || $4 ~ /:80$/ || $4 ~ /:443$/ || $4 ~ /:8085$/'
fi

echo
echo "== Web server =="
if command -v nginx >/dev/null 2>&1; then
    nginx -v
    systemctl is-active nginx 2>/dev/null || true
fi
if command -v apache2 >/dev/null 2>&1; then
    apache2 -v
    systemctl is-active apache2 2>/dev/null || true
elif command -v httpd >/dev/null 2>&1; then
    httpd -v
fi

echo
echo "== Docker =="
docker --version || echo "docker is not installed"
docker compose version || echo "docker compose is not installed"
docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}' || true

echo
echo "Preflight finished. Nothing was changed."
