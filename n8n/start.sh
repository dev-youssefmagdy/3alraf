#!/usr/bin/env bash
# Start n8n for 3alraf with n8n/.env.
#   ./start.sh            foreground (used by the systemd --user unit)
#   ./start.sh --daemon   background with nohup + pid file (fallback when systemd --user is unavailable)
#   ./start.sh --stop     stop the background instance
#   ./start.sh --status
set -euo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PIDFILE="$DIR/n8n.pid"
cd "$DIR"

case "${1:-}" in
  --stop)
    if [[ -f "$PIDFILE" ]] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
      kill "$(cat "$PIDFILE")"; for _ in $(seq 1 30); do kill -0 "$(cat "$PIDFILE")" 2>/dev/null || break; sleep 1; done
      rm -f "$PIDFILE"; echo "n8n stopped"
    else echo "n8n not running (no live pid file)"; rm -f "$PIDFILE"; fi
    exit 0 ;;
  --status)
    if [[ -f "$PIDFILE" ]] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then echo "running (pid $(cat "$PIDFILE"))"; else echo "not running (pid file)"; fi
    exit 0 ;;
esac

[[ -f "$DIR/.env" ]] || { echo "missing $DIR/.env (copy .env.example)" >&2; exit 1; }
[[ -x "$DIR/node_modules/.bin/n8n" ]] || { echo "n8n not installed: cd $DIR && npm ci" >&2; exit 1; }
set -a; # shellcheck disable=SC1091
. "$DIR/.env"; set +a
mkdir -p "$N8N_USER_FOLDER/logs" "${N8N_RESTRICT_FILE_ACCESS_TO:-$N8N_USER_FOLDER/files}"

if [[ "${1:-}" == "--daemon" ]]; then
  if [[ -f "$PIDFILE" ]] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then echo "already running (pid $(cat "$PIDFILE"))"; exit 0; fi
  nohup "$DIR/node_modules/.bin/n8n" start >>"$N8N_USER_FOLDER/logs/stdout.log" 2>&1 &
  echo $! >"$PIDFILE"; echo "n8n started in background (pid $!), logs: $N8N_USER_FOLDER/logs/"
  exit 0
fi
exec "$DIR/node_modules/.bin/n8n" start
