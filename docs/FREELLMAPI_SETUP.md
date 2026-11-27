# FreeLLMAPI setup (Product AI tools)

This guide takes a fresh **Ubuntu 24.04** VPS to a working AI gateway for **Admin → Products → Edit → AI tools**
(prices by image, marketplace prices, title ideas). It covers the install, `https://llm.3alraf.com` behind nginx, free provider
keys, the fallback chain, the Laravel settings and the checks.

[FreeLLMAPI](https://github.com/tashfeenahmed/freellmapi) is an OpenAI-compatible gateway in front of the free tiers of Gemini,
Groq, OpenRouter, Mistral and other providers. It runs in Docker and is reachable only on `127.0.0.1:3001`. nginx is the only
public entry point.

> **Upstream caveat.** The FreeLLMAPI author calls the project "personal experimentation, single-user". Free tiers change and run
> out. The store treats the gateway as **best effort**: every AI action fails soft with a clear message, product saving never
> depends on it, and no price is ever invented (see `PRODUCT_AI_FREELLMAPI_PLAN.md` §2).

Commands run as a sudo user on the VPS. Set these once per shell:

```bash
export LLM_DOMAIN=llm.3alraf.com          # the subdomain for the gateway (change it here and in step 5)
export REPO=/var/www/3alraf               # the store checkout
export VPS_IP=$(curl -4 -s https://ifconfig.me); echo "$VPS_IP"
```

---

## 1. Prerequisites: Docker Engine and the compose plugin

Install Docker from **Docker's own apt repository** (the Ubuntu `docker.io` package has no compose v2 plugin):

```bash
sudo apt-get update
sudo apt-get install -y ca-certificates curl gnupg
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu \
$(. /etc/os-release && echo "$VERSION_CODENAME") stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo systemctl enable --now docker
docker --version && docker compose version
```

Also needed later: `sudo apt-get install -y nginx certbot python3-certbot-nginx python3-venv dnsutils sqlite3`.

## 2. DNS: point the subdomain at the VPS

At your DNS provider, add an **A record**: name `llm`, value = the VPS IPv4 (`$VPS_IP`), TTL 300. Add an `AAAA` record too only if
the VPS has working IPv6.

Check that it has propagated (both lines must print the VPS IP):

```bash
dig +short A "$LLM_DOMAIN"
dig +short A "$LLM_DOMAIN" @1.1.1.1
```

Do not request the certificate (step 5) until this works.

## 3. Install FreeLLMAPI in /opt/freellmapi

The compose file, an example `.env` and the nginx files are in the repo under `backend/deploy/freellmapi/`.

```bash
sudo install -d -m 0750 /opt/freellmapi
sudo cp "$REPO/backend/deploy/freellmapi/docker-compose.yml" "$REPO/backend/deploy/freellmapi/.env.example" /opt/freellmapi/
cd /opt/freellmapi
sudo cp .env.example .env
sudo sed -i "s/^ENCRYPTION_KEY=.*/ENCRYPTION_KEY=$(openssl rand -hex 32)/" .env
sudo sed -i "s/^ALLOWED_HOSTS=.*/ALLOWED_HOSTS=$LLM_DOMAIN/" .env
sudo chmod 600 .env
sudo grep -E '^(ENCRYPTION_KEY|PORT|ALLOWED_HOSTS|TRUST_PROXY)=' .env    # ENCRYPTION_KEY must be 64 hex characters
```

This container `.env` stays a **file** (not the store's settings table): the container reads `ENCRYPTION_KEY` before any database exists.

> **Keep `ENCRYPTION_KEY`.** It encrypts the provider keys stored in the data volume. Without it, a restored volume is useless.
> Store a copy in your password manager now.

Start it:

```bash
cd /opt/freellmapi
sudo docker compose pull
sudo docker compose up -d
sudo docker compose ps                     # STATUS should become "healthy" within ~30 s
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:3001/v1/models    # 401 = up (no key yet)
sudo ss -ltnp | grep 3001                  # must show 127.0.0.1:3001 only, never 0.0.0.0:3001
```

Optional: pin a version for reproducible upgrades by adding `FREELLMAPI_TAG=<tag>` to `/opt/freellmapi/.env`.

## 4. First-run admin setup (through an SSH tunnel, BEFORE the site is public)

The dashboard's first-run setup page is **open to anyone until the admin account exists**. Do the setup through an SSH tunnel
first, and only then enable the public site (step 5). If you must enable the site first, keep the IP allow-list on (step 5.4)
until setup is done.

On **your own computer**:

```bash
ssh -N -L 3001:127.0.0.1:3001 youruser@YOUR_VPS_IP
```

Open <http://localhost:3001>, create the admin account with a **strong, unique password** (a password manager, 20+ characters),
and sign in. Keep the tunnel open for steps 6 to 8, or use `https://llm.3alraf.com` once step 5 is done.

## 5. nginx site and TLS for llm.3alraf.com

### 5.1 Firewall (UFW)

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'                # 80 + 443
sudo ufw enable
sudo ufw status verbose                    # 3001 must NOT be listed
```

Docker publishes the port on `127.0.0.1` only, so UFW does not need a rule for 3001. Never change the compose `ports:` line to
`3001:3001` (Docker bypasses UFW for published ports).

### 5.2 Certificate

```bash
sudo certbot certonly --nginx -d "$LLM_DOMAIN" --agree-tos -m you@example.com --no-eff-email
sudo ls /etc/letsencrypt/live/"$LLM_DOMAIN"/  # fullchain.pem privkey.pem
```

`certonly --nginx` proves the domain through the running nginx and leaves our site file untouched. Running
`sudo certbot --nginx -d "$LLM_DOMAIN"` also works, but it rewrites the server block, so diff it against the repo file afterwards.

### 5.3 Install the site

```bash
sudo cp "$REPO/backend/deploy/freellmapi/nginx/llm.3alraf.com.conf" /etc/nginx/sites-available/"$LLM_DOMAIN".conf
sudo sed -i "s/llm\.3alraf\.com/$LLM_DOMAIN/g" /etc/nginx/sites-available/"$LLM_DOMAIN".conf
sudo ln -s /etc/nginx/sites-available/"$LLM_DOMAIN".conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
curl -sI "http://$LLM_DOMAIN/" | head -1                   # 301 to https
curl -s -o /dev/null -w '%{http_code}\n' "https://$LLM_DOMAIN/v1/models"   # 401 = proxied, key required
```

What the site does: HTTP → HTTPS; `/v1/` (the API) is public but needs the unified key and is rate limited per IP (`limit_req`);
`/` (dashboard) and `/api/` (dashboard API) can be limited to your IPs; streaming-friendly proxying (`proxy_buffering off`, 300 s reads,
HTTP/1.1 with Upgrade); `client_max_body_size 10m` for base64 photos; HSTS, `nosniff`, `X-Frame-Options DENY`, no referrer.
There is **no HTTP basic auth** on purpose: it would use the same `Authorization` header as the API's Bearer key.

### 5.4 Optional: IP allow-list for the dashboard

```bash
sudo cp "$REPO/backend/deploy/freellmapi/nginx/freellmapi-allow.conf.example" /etc/nginx/snippets/freellmapi-allow.conf
sudo nano /etc/nginx/snippets/freellmapi-allow.conf        # your IPs, then "deny all;"
sudo sed -i 's|# include /etc/nginx/snippets/freellmapi-allow.conf;|include /etc/nginx/snippets/freellmapi-allow.conf;|' /etc/nginx/sites-available/"$LLM_DOMAIN".conf
sudo nginx -t && sudo systemctl reload nginx
```

### 5.5 Auto-renewal

```bash
sudo certbot renew --dry-run
systemctl list-timers | grep certbot       # certbot.timer runs twice a day
```

From now on open the dashboard at **https://llm.3alraf.com**. The SSH tunnel of step 4 stays the fallback (for example when the
allow-list locks you out, or nginx is down).

## 6. Free API keys for the providers

Add each key in the dashboard (**Keys** / providers page). Add several providers: when one runs out, the gateway moves on to the next.

| Provider | Get a key | Vision models | Notes |
|----------|-----------|---------------|-------|
| Google AI Studio (Gemini) | <https://aistudio.google.com/apikey> | yes (Gemini 2.5 Flash / Flash-Lite) | best free vision option; daily request caps |
| Groq | <https://console.groq.com/keys> | yes (Llama 4 Scout / Maverick) | very fast text |
| OpenRouter | <https://openrouter.ai/keys> | some `:free` models | free models end in `:free`; low daily cap without credit |
| Mistral | <https://console.mistral.ai/api-keys> | yes (Pixtral / Mistral Small) | choose the free "Experiment" plan; phone verification |
| Cerebras | <https://cloud.cerebras.ai/> | no | fast text models |
| NVIDIA NIM | <https://build.nvidia.com/> | some (Llama 3.2 Vision and others) | free credits per account |
| GitHub Models | <https://github.com/marketplace/models> | yes (GPT-4o / GPT-4.1 family) | a fine-grained token with the `models:read` permission |
| Cloudflare Workers AI | <https://dash.cloudflare.com/> → AI → Workers AI | yes (Llama 3.2 11B Vision) | needs the account ID and an API token with Workers AI access |

Free tiers and model names change often; the dashboard shows which models each key unlocks and whether a model reads images.

## 7. Fallback chain and automatic model switching

Two layers switch models automatically when a limit is hit:

1. **In FreeLLMAPI:** the model `auto` follows the dashboard's fallback chain. On a 429 or 5xx from one provider it tries the next
   model in the chain. Build the chain with models from **several providers** (for example Gemini 2.5 Flash → Groq Llama → Mistral
   Small → OpenRouter free → Cerebras), so one exhausted quota does not stop the chain. Named chains appear as `auto:<name>`, and
   presets as `auto:reliable`, `auto:fast`, `auto:smart`.
2. **In the store:** `FreeLlmClient` tries the ordered lists from Admin → System settings → AI (`Text models`, `Vision models`, default
   `auto,auto:reliable,auto:fast` and `auto,gemini-2.5-flash`). On 429 / `quota_exceeded`, 5xx, a timeout or 422 `no_vision_model`
   it uses the next entry and lets the failed one cool down for `Retry-After` seconds (default 60). `php artisan llm:check` shows
   each entry as ready, cooling down or failed.

**Vision:** enable at least one vision model (Gemini 2.5 Flash is the easiest) and make sure the chain can reach it. Without one,
*Prices by image* answers "No AI model that reads photos is turned on" (`no_vision_model`).

**Optional web search engine:** the store searches the web with the free `ddgs` library (step 9.2). FreeLLMAPI's own
`/v1/search` is a second source, used only when an engine key is set in its dashboard (Models → Search: Tavily, Exa, Firecrawl or
Linkup; Tavily has a free monthly allowance at <https://tavily.com>). To use it, set `PRODUCT_AI_FREELLM_SEARCH=true` in
`backend/.env`. `llm:check` lists the engines it finds.

## 8. The unified key

In the dashboard **Keys** page, copy the unified key (`freellmapi-…`). It is the only key the store needs. It lives only in the
store's encrypted settings (step 9), never in git, never in frontend code.

## 9. Laravel side

### 9.1 Settings

**Admin → System settings → AI tools (FreeLLMAPI)**:

- **FreeLLMAPI base URL**
  - Laravel on the **same VPS** (recommended): `http://127.0.0.1:3001/v1`. There is no TLS hop and no public round trip.
  - Laravel on **another server**: `https://llm.3alraf.com/v1`. The `/v1/` rate limit in nginx (30 requests/min per IP, burst 20)
    is enough for the admin tools.
- **FreeLLMAPI unified key**: paste the key from step 8. It is stored encrypted. Leave the field empty later to keep it.
- Leave the rest at the defaults, then press **Save** and **Test connection**.

These values live in the `settings` table and are applied at boot, so `php artisan config:cache` does not matter for them. The
`FREELLM_*` / `PRODUCT_AI_*` lines in `backend/.env.example` are only defaults and the first import.

### 9.2 Web search (ddgs, the same venv as *Find images*)

```bash
cd "$REPO/tools/image-picker"
sudo python3 -m venv .venv                          # skip if Find images already set it up
sudo .venv/bin/pip install -r requirements.txt
sudo mkdir -p picked/.cache
sudo chown -R www-data:www-data .venv picked        # php-fpm runs the script as www-data
sudo -u www-data .venv/bin/python image_picker.py text "Philips air fryer" -n 3 --backend google,bing,duckduckgo --region eg-ar
```

The last command must print JSON with `results`. An engine that refuses is listed under `errors`, and the others still answer.

### 9.3 Migrate and check

```bash
cd "$REPO/backend"
sudo -u www-data php artisan migrate --force        # adds product_variants.name
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan llm:check
```

`llm:check` prints `[OK]` or `[FAIL]` for: configured, reachable + key valid, each text and vision model (ready / cooling / failed,
with a tiny test prompt), a vision model available, ddgs text search, and FreeLLMAPI's search engines. Use `--no-probe` to skip the
test prompts and `--no-search` to skip the web search.

## 10. Smoke tests with curl

```bash
KEY='freellmapi-…'   # the unified key

# local (same VPS)
curl -s http://127.0.0.1:3001/v1/models?available=true -H "Authorization: Bearer $KEY" | head -c 400; echo
curl -s http://127.0.0.1:3001/v1/chat/completions -H "Authorization: Bearer $KEY" -H 'Content-Type: application/json' \
  -d '{"model":"auto","messages":[{"role":"user","content":"Reply with OK"}],"max_tokens":5}' -D - | grep -iE '^(HTTP|x-routed-via)|"content"'

# public, through nginx and TLS
curl -s "https://$LLM_DOMAIN/v1/models" -H "Authorization: Bearer $KEY" | head -c 400; echo
curl -s -o /dev/null -w '%{http_code}\n' "https://$LLM_DOMAIN/v1/models"     # 401 without the key
```

Then in the admin: open a product → **AI tools** → *Title ideas* → *Suggest titles*.

## 11. Updates and backups

**Update** (also refreshes the compose file from the repo and runs `llm:check`):

```bash
cd "$REPO" && sudo ./deploy.sh --freellm          # or: cd /opt/freellmapi && sudo docker compose pull && sudo docker compose up -d
```

**Back up** the data volume (SQLite: provider keys, unified key, chains) **and** `ENCRYPTION_KEY`. Both are needed to restore.

```bash
sudo install -d -m 0700 /root/backups
cd /opt/freellmapi && sudo docker compose stop
sudo docker run --rm -v freellmapi-data:/data:ro -v /root/backups:/backup alpine \
  tar czf /backup/freellmapi-data-$(date +%F).tgz -C /data .
sudo cp /opt/freellmapi/.env /root/backups/freellmapi-env-$(date +%F)
sudo docker compose start
```

**Restore** on a new server: do step 3 with the **old** `.env` (same `ENCRYPTION_KEY`), then before `up -d`:

```bash
sudo docker volume create freellmapi-data
sudo docker run --rm -v freellmapi-data:/data -v /root/backups:/backup alpine sh -c 'cd /data && tar xzf /backup/freellmapi-data-YYYY-MM-DD.tgz'
```

## 12. Security checklist

- The container listens on **127.0.0.1:3001** only (`ss -ltnp | grep 3001`). nginx on 443 is the only public way in.
- **Never** use the `curl … | bash` installer from freellmapi.co. Install only the GitHub image through our compose file.
- The unified key lives only in the store's encrypted settings; `ENCRYPTION_KEY` lives only in `/opt/freellmapi/.env` (mode 600)
  and in your backups.
- UFW allows only SSH, 80 and 443.
- Do the first-run setup through the SSH tunnel before the site is public, with a strong password. Keep the dashboard behind the IP
  allow-list if your IP is stable.
- `ALLOWED_HOSTS` (DNS-rebinding guard) is set to the subdomain; loopback stays allowed for Laravel.
- Rotate the unified key in the dashboard if it ever leaks, then paste the new one in System settings.

## 13. Troubleshooting

| Code / symptom | Meaning | Fix |
|----------------|---------|-----|
| `not_configured` (503) | base URL or key empty in System settings, or the gateway rejected the key | Fill both fields in Admin → System settings → AI and Save; `llm:check` says "key valid: FAIL" when the key is wrong |
| `gateway_down` (503) | nothing answers, 5xx, or no model gave a usable answer | `cd /opt/freellmapi && sudo docker compose ps` / `logs --tail 100`; check the base URL; `llm:check` |
| `quota_exceeded` (429) | every model in the list is out of free quota or cooling down | add more providers or models to the chain; wait for `Retry-After`; `llm:check` shows cooling models |
| `no_vision_model` (422) | no enabled model reads images | enable Gemini 2.5 Flash (or another vision model) in the dashboard; check `Vision models` in System settings |
| `throttled` (429) | more than the per-admin limit (default 10/min) | wait the seconds shown; raise "AI requests per admin per minute" if needed |
| `timeout` (504) | the models took too long | shorter search text; lower-latency models first; raise the model timeout (max 120 s, nginx/PHP still cap the request at ~60 s) |
| "No listings found" with errors `google`/`bing` | ddgs engines refused (rate limit) | wait a few minutes; try `PRODUCT_AI_SEARCH_BACKENDS=bing,duckduckgo`; run the step 9.2 command |
| errors `ddgs: unavailable` | the venv or script is missing / not readable by www-data | redo step 9.2 |
| 403 on the dashboard | IP allow-list | add your IP to `/etc/nginx/snippets/freellmapi-allow.conf` or use the SSH tunnel |
| 403 "host not allowed" | `ALLOWED_HOSTS` does not list the name used | fix it in `/opt/freellmapi/.env`, then `sudo docker compose up -d` |
| 502 from nginx | container down or restarting | `sudo docker compose ps`; `sudo docker compose up -d` |
| certificate errors | DNS not pointing at the VPS, or expired | `dig +short A $LLM_DOMAIN`; `sudo certbot renew --dry-run` |
