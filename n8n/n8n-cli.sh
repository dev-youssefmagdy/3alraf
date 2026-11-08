#!/usr/bin/env bash
# Run any n8n CLI command with n8n/.env loaded, e.g. ./n8n-cli.sh list:workflow
set -euo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
set -a; . "$DIR/.env"; set +a
exec "$DIR/node_modules/.bin/n8n" "$@"
