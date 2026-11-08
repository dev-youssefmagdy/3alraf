#!/usr/bin/env bash
# Installs and starts n8n as a systemd --user service (no sudo). Idempotent.
set -euo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
UNIT_DIR="${XDG_CONFIG_HOME:-$HOME/.config}/systemd/user"
mkdir -p "$UNIT_DIR"
sed "s#@N8N_DIR@#$DIR#g" "$DIR/deploy/n8n.service" >"$UNIT_DIR/n8n.service"
# stop a nohup instance started by start.sh --daemon, if any
"$DIR/start.sh" --stop >/dev/null 2>&1 || true
systemctl --user daemon-reload
systemctl --user enable --now n8n.service
systemctl --user --no-pager status n8n.service | head -5 || true
if [[ "$(loginctl show-user "$USER" -p Linger --value 2>/dev/null)" != "yes" ]]; then
  echo
  echo "NOTE: linger is off for $USER, so n8n only runs while $USER has a session (it starts at login and stops at logout)."
  echo "      To keep it running at boot without a login run once:  sudo loginctl enable-linger $USER"
fi
