#!/usr/bin/env bash
# One-time LOCAL setup that needs root (agents have no sudo). Idempotent. Run:  sudo /var/www/3alraf/n8n/setup-local-sudo.sh
#  1. enables proxy proxy_http proxy_wstunnel headers rewrite
#  2. installs backend/deploy/apache/000-n8n.3alraf.loc.conf (sorts before 3alraf.loc.conf, whose *.3alraf.loc alias would win)
#  3. adds "127.0.0.1 n8n.3alraf.loc" to /etc/hosts
#  4. configtest + reload Apache
#  5. (optional, pass --linger) keeps the user's systemd --user n8n service running without a login session
set -euo pipefail
[[ $EUID -eq 0 ]] || { echo "run with sudo" >&2; exit 1; }
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SRC="$DIR/../backend/deploy/apache/000-n8n.3alraf.loc.conf"
DST=/etc/apache2/sites-available/000-n8n.3alraf.loc.conf
[[ -f "$SRC" ]] || { echo "missing $SRC" >&2; exit 1; }

a2enmod -q proxy proxy_http proxy_wstunnel headers rewrite
install -m 0644 -o root -g root "$SRC" "$DST"
a2ensite -q 000-n8n.3alraf.loc
grep -qE '^[[:space:]]*127\.0\.0\.1[[:space:]].*\bn8n\.3alraf\.loc\b' /etc/hosts || echo '127.0.0.1 n8n.3alraf.loc' >> /etc/hosts
apache2ctl configtest
systemctl reload apache2
if [[ "${1:-}" == "--linger" && -n "${SUDO_USER:-}" ]]; then loginctl enable-linger "$SUDO_USER"; echo "linger enabled for $SUDO_USER"; fi
echo "OK: http://n8n.3alraf.loc -> 127.0.0.1:5678"
curl -s -o /dev/null -w "n8n.3alraf.loc/healthz -> HTTP %{http_code}\n" http://n8n.3alraf.loc/healthz || true
