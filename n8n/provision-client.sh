#!/usr/bin/env bash
# Creates (first run) or ROTATES (later runs) the Laravel integration client "n8n (gift finder)" (scope gift_finder:read) through
# the app's IntegrationClientService, then stores the credentials in n8n WITHOUT printing them:
#   - bearer token  -> n8n credential "3alraf integration (bearer)" (type httpHeaderAuth, encrypted with N8N_ENCRYPTION_KEY)
#   - HMAC secret   -> THREEALRAF_HMAC_SECRET in n8n/.env (chmod 600), read by the signing Code node
# and restarts n8n so the new env is loaded. Old token/secret stop working immediately on rotation.
# Usage: ./provision-client.sh [path-to-laravel]   (default ../backend). Production: run as the n8n user with LARAVEL path + php.
set -euo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LARAVEL="${1:-$DIR/../backend}"
set -a; . "$DIR/.env"; set +a
umask 077
TMP="$N8N_USER_FOLDER/integration-client.json"
CRED="$N8N_USER_FOLDER/credential-3alraf-bearer.json"
trap 'rm -f "$CRED"' EXIT

php "$DIR/bin/provision-client.php" "$(cd "$LARAVEL" && pwd)" "$TMP"

python3 - "$TMP" "$CRED" "$DIR/.env" <<'PY'
import json, re, sys
src, cred, env = sys.argv[1:4]
c = json.load(open(src))
json.dump([{"id": "3alrafIntegBearr", "name": "3alraf integration (bearer)", "type": "httpHeaderAuth",
            "data": {"name": "Authorization", "value": "Bearer " + c["token"]}}], open(cred, "w"))
s = open(env).read()
line = "THREEALRAF_HMAC_SECRET=" + c["secret"]
s = re.sub(r"^THREEALRAF_HMAC_SECRET=.*$", line, s, flags=re.M) if re.search(r"^THREEALRAF_HMAC_SECRET=", s, re.M) else s + "\n" + line + "\n"
open(env, "w").write(s)
PY
chmod 600 "$DIR/.env"
"$DIR/n8n-cli.sh" import:credentials --input="$CRED" 2>&1 | grep -v -i "token\|secret" | tail -2
rm -f "$TMP"
if systemctl --user is-enabled n8n.service >/dev/null 2>&1; then systemctl --user restart n8n.service; echo "n8n restarted (systemd --user)";
elif [[ -f "$DIR/n8n.pid" ]]; then "$DIR/start.sh" --stop && "$DIR/start.sh" --daemon; fi
echo "done: bearer stored in n8n credential '3alraf integration (bearer)', HMAC secret in n8n/.env"
