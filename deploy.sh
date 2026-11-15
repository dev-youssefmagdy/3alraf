#!/usr/bin/env bash
# Deploy 3alraf: pull, install, migrate, build, clear caches, reload services.
# Usage: sudo ./deploy.sh [--no-pull] [--no-build] [--mcp] [--whatsapp]
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP="$ROOT/backend"
WEB_USER="www-data"
PHP_FPM="php8.5-fpm"

PULL=1; BUILD=1; MCP=0; WA=0
for arg in "$@"; do
  case "$arg" in
    --no-pull) PULL=0 ;;
    --no-build) BUILD=0 ;;
    --mcp) MCP=1 ;;
    --whatsapp) WA=1 ;;
    *) echo "Unknown option: $arg"; exit 1 ;;
  esac
done

step() { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }
artisan() { sudo -u "$WEB_USER" php "$APP/artisan" "$@"; }

[ "$(id -u)" -eq 0 ] || { echo "Run as root (sudo ./deploy.sh)"; exit 1; }

cd "$ROOT"

if [ "$PULL" -eq 1 ]; then
  step "Pulling latest code"
  if [ -n "$(git status --porcelain)" ]; then
    echo "Working tree has uncommitted changes; commit/stash them or use --no-pull."
    git status --short
    exit 1
  fi
  git pull --ff-only
fi

cd "$APP"

step "Entering maintenance mode"
artisan down --retry=15 || true
trap 'step "Leaving maintenance mode"; artisan up || true' EXIT

step "Composer install"
sudo -u "$WEB_USER" env COMPOSER_HOME=/tmp/composer composer install --no-dev --optimize-autoloader --no-interaction

if [ "$BUILD" -eq 1 ]; then
  step "Building frontend assets"
  npm ci --no-audit --no-fund
  npm run build
fi

step "Running migrations"
artisan migrate --force

step "Refreshing caches"
artisan optimize:clear
artisan config:cache
artisan route:cache
artisan view:cache
artisan event:cache

step "Fixing permissions"
chown -R "$WEB_USER":"$WEB_USER" storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

step "Reloading PHP-FPM (opcache has timestamp validation off)"
systemctl reload "$PHP_FPM"

step "Restarting queue, scheduler and reverb"
artisan queue:restart
supervisorctl restart 3alraf-scheduler 3alraf-reverb || true

if [ "$MCP" -eq 1 ]; then
  step "Building and restarting MCP server"
  (cd "$ROOT/mcp-server" && npm ci --no-audit --no-fund && npm run build)
  systemctl restart 3alraf-mcp
fi

if [ "$WA" -eq 1 ]; then
  step "Updating and restarting WhatsApp service"
  (cd "$ROOT/whatsapp-service" && npm ci --omit=dev --no-audit --no-fund)
  supervisorctl restart 3alraf-whatsapp
fi

step "Deploy finished at $(date)"
