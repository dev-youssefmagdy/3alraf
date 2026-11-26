#!/usr/bin/env bash
# Deploy 3alraf: pull, install, migrate, build, clear caches, reload services.
# Usage: sudo ./deploy.sh [--no-pull] [--no-build] [--mcp] [--whatsapp] [--embedder] [--freellm]
#   --embedder  image-search embedder (image-embedder/): install/update its venv, export the model if missing, refresh the systemd unit, restart it
#   --freellm   FreeLLMAPI gateway (backend/deploy/freellmapi → $FREELLM_DIR, default /opt/freellmapi): refresh the compose file,
#               docker compose pull + up -d, then php artisan llm:check. The .env there (ENCRYPTION_KEY) is never touched.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP="$ROOT/backend"
WEB_USER="www-data"
PHP_FPM="php8.5-fpm"

PULL=1; BUILD=1; MCP=0; WA=0; EMB=0; FREELLM=0
FREELLM_DIR="${FREELLM_DIR:-/opt/freellmapi}"
for arg in "$@"; do
  case "$arg" in
    --no-pull) PULL=0 ;;
    --no-build) BUILD=0 ;;
    --mcp) MCP=1 ;;
    --whatsapp) WA=1 ;;
    --embedder) EMB=1 ;;
    --freellm) FREELLM=1 ;;
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
COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_HOME=/tmp/composer composer install --no-dev --optimize-autoloader --no-interaction

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
artisan queue:restart   # every worker exits gracefully, incl. 3alraf-queue-embeddings; supervisor starts fresh ones
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

if [ "$EMB" -eq 1 ]; then
  step "Updating and restarting the image embedder"
  "$ROOT/image-embedder/scripts/setup.sh"   # idempotent: venv from requirements.txt; exports the ONNX model only if it is missing
  UNIT=/etc/systemd/system/3alraf-embedder.service
  if ! cmp -s "$ROOT/image-embedder/deploy/3alraf-embedder.service" "$UNIT"; then
    install -m 0644 "$ROOT/image-embedder/deploy/3alraf-embedder.service" "$UNIT"
    systemctl daemon-reload
    systemctl enable 3alraf-embedder
  fi
  systemctl restart 3alraf-embedder
  EMB_URL="$(grep -E '^IMAGE_EMBEDDER_URL=' "$APP/.env" | tail -n1 | cut -d= -f2- | tr -d '"' || true)"
  EMB_URL="${EMB_URL:-http://127.0.0.1:8090}"
  for _ in $(seq 1 20); do curl -fsS "$EMB_URL/health" >/dev/null 2>&1 && break; sleep 1; done
  curl -fsS "$EMB_URL/health" && echo || echo "WARNING: embedder health check failed (journalctl -u 3alraf-embedder)"
fi

if [ "$FREELLM" -eq 1 ]; then
  step "Updating FreeLLMAPI ($FREELLM_DIR)"
  install -d -m 0750 "$FREELLM_DIR"
  install -m 0644 "$APP/deploy/freellmapi/docker-compose.yml" "$FREELLM_DIR/docker-compose.yml"
  install -m 0644 "$APP/deploy/freellmapi/.env.example" "$FREELLM_DIR/.env.example"
  if [ ! -f "$FREELLM_DIR/.env" ]; then
    echo "WARNING: $FREELLM_DIR/.env is missing (ENCRYPTION_KEY). See docs/FREELLMAPI_SETUP.md; FreeLLMAPI was not started."
  else
    (cd "$FREELLM_DIR" && docker compose pull && docker compose up -d --remove-orphans)
    for _ in $(seq 1 30); do
      [ "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:3001/v1/models || true)" != "000" ] && break
      sleep 1
    done
    artisan llm:check --no-search || echo "WARNING: llm:check reported problems (docker compose -f $FREELLM_DIR/docker-compose.yml logs --tail 50)"
  fi
fi

step "Deploy finished at $(date)"
