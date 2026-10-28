# n8n workflow pack and runbook (Growth N7)

Importable templates live in `docs/n8n/workflows/` (21 JSON files, inactive on import, tag `3alraf-growth`). They contain NO secrets: credentials are referenced by name and secrets come from n8n environment variables.

## Setup
1. In Laravel admin > Growth > Integration, create a client (scopes: only what you need: `read`, `events:read`, `proposals:write`, `ingest:write`; any scope containing price/refund/stock/budget/payout/discount is refused). Copy the token and HMAC secret once.
2. n8n credential (type HTTP Header Auth), name exactly **3alraf Integration Bearer**: `Authorization` = `Bearer <token>`. Optional alert credential **3alraf Owner Alerts** (your Slack/Telegram/Email node).
3. n8n env: `GROWTH_API_BASE=https://<shop-host>/api/integration/v1`, `GROWTH_HMAC_SECRET=<secret>`, `NODE_FUNCTION_ALLOW_BUILTIN=crypto`, `N8N_BLOCK_ENV_ACCESS_IN_NODE=false`. Use separate values for staging and production (separate Laravel client per environment; never share a secret).
4. Import `00-error-handler.json` first, then the others; every workflow points its error workflow to "3alraf - 00 Error handler". Activate only the workflows whose Laravel feature switch you turned on.
5. Signing: header `X-Timestamp` (unix seconds, 5 min window) and `X-Signature` = hex HMAC-SHA256(secret, `"{timestamp}.{raw body}"`). The "Sign" Code node implements it. POSTs send `Idempotency-Key` (7-day replay window; pass a unique key for intentionally repeated identical posts).

## Workflow to feature map (every built switch is covered)
| File | Schedule / trigger | Feature keys |
|---|---|---|
| 01 health monitor | every 5 min | integration_api |
| 02 events receiver | webhook `/webhook/3alraf-events` (give this URL as the client webhook; https only) | events_outbox |
| 03 events poll | every 15 min | events_outbox |
| 04 approval wait | webhook `3alraf-propose` | approvals |
| 05 experiment results | webhook `3alraf-experiment-result` | experiments |
| 06 owner brief | 08:00 daily | brief |
| 07 brand memory | Mon 03:00 | brand_memory |
| 08 truth guard | webhook `3alraf-truth-guard` | truth_guard |
| 09 content factory | webhook `3alraf-content` | content_factory |
| 10 offers weekly | Mon 07:00 | bundle_engine, bundle_ab, seasonal_calendar, gift_finder |
| 11 interactions ingest | webhook `3alraf-interaction` | interactions |
| 12 reply sender | webhook `3alraf-reply` | auto_reply |
| 13 VOC/reputation/missed | 09:00 daily | voc, reputation_radar, missed_opportunities |
| 14 comment to cart | webhook `3alraf-comment-link` | comment_to_cart |
| 15 experiment lab | 06:30 daily | experiment_lab |
| 16 small-batch + ambassadors | Sun 08:00 | small_batch_lab, ambassadors |
| 17 inventory | 07:00 daily | inventory_engine |
| 18 return reasons | webhook `3alraf-return-reason` | return_intel |
| 19 trend radar | Mon 07:00 | trend_radar |
| 20 CEO challenge | Mon 08:00 | ceo_challenge |

Each workflow first calls `GET /features` and stops quietly when its keys are not active (Laravel also answers 403 `feature_disabled`). Endpoints used are only those in `routes/api_integration.php` (see `docs/api/integration-openapi.json`). No price, refund, stock or ad-budget endpoint exists; money actions happen only through human approvals in Laravel. Content factory, seasonal calendar, gift finder, small-batch and ambassadors have no dedicated endpoint: their flows use the generic read endpoints and file a `note` approval.

## Laravel server requirements
- **Queue worker is mandatory**: `php artisan queue:work --tries=3` under Supervisor/systemd (event delivery `DispatchGrowthEventJob`, AI jobs). Without it the outbox only drains via the scheduler fallback and AI jobs never run.
- **Scheduler is mandatory**: `* * * * * php artisan schedule:run` (growth:dispatch-pending every minute, scan-low-stock 15 min, aggregate hourly, engage hourly, operate 06:30, maintain 02:45, prune 03:10).
- **Shared cache driver** (Redis, or database; NOT `file`/`array` when running more than one PHP process/server): idempotent counters (unique-session counting for storefront visits, bundle A/B exposures, once-per-day event guards, rate limits) use `Cache::add`; a per-server cache double counts and breaks dedupe.
- Optional hardening: `GROWTH_INTEGRATION_IPS=1.2.3.4,10.0.0.0/8` restricts the whole `/api/integration/v1` prefix (403 `ip_not_allowed`); responses carry `no-store`, `nosniff`, `no-referrer`. `GROWTH_WEBHOOK_HTTPS=true` (default) refuses plain-http webhooks.

## Monitoring
`GET /api/integration/v1/health` (any valid client): `status` ok|degraded, database, outbox_pending, outbox_dead, oldest_pending_at. Alert on degraded or a growing oldest_pending_at. Dead events can be replayed from Growth > Events.

## Data retention and erasure
`php artisan growth:prune [--tenant=slug] [--dry-run]` (nightly) deletes: interactions > 180d, small-batch signups (hashed+masked contacts) > 180d, ad-day rows > 730d, growth action log > 730d, delivered/dead events > 60d, expired idempotency records. Windows: `config/growth.php` `retention`.
Erasure on request: `php artisan growth:prune --tenant=egy --erase-thread=<thread or external id>` and/or `--erase-contact=<phone or email as submitted>` (writes a `data_erasure` log entry without the identifier). Raw customer text is never stored (masked before save); events carry ids/amounts only.

## Backup / restore test (quarterly)
1. `mysqldump` the database nightly + keep `storage/app` offsite; export n8n workflows and credentials (`n8n export:workflow --all`, `export:credentials` encrypted) and keep `N8N_ENCRYPTION_KEY` separately.
2. Restore into staging, point staging n8n at staging Laravel (separate client/secret), run 01 health and one ingest, confirm idempotent replay returns the stored response.

## Docs notes
`docs/CALCULATIONS.md` had no N6 section; the inventory and return-reason rules were added there in N7. Mobile API docs stay in `docs/api/openapi.json`; the integration API is in `docs/api/integration-openapi.json`.

## Offline self-test and real-n8n dry-run checklist (N10)
**Offline (no n8n needed):** `php artisan growth:n8n-selftest --tenant=egy` runs every "Sign ..." Code node of `docs/n8n/workflows/*.json` through node (`docs/n8n/selftest-runner.cjs`, same JS and HMAC n8n runs), sends each built request through the real routes with a throwaway client (all scopes), once with all features ON and once OFF, and asserts: HMAC accepted (no 401), route exists (no 404-route/405), no 500, ON = authenticated and not forbidden, OFF = 403 `feature_disabled` for feature-gated routes. All changes are rolled back; refuses to run in production. Use a migrated (preferably separate) database. Wrapped by `tests/Feature/GrowthRemainingTest.php`.
**It does not prove n8n itself.** The checklist below has NOT been run live by the development team; run it once on your n8n:
1. Set n8n env `GROWTH_API_BASE`, `GROWTH_HMAC_SECRET`, `NODE_FUNCTION_ALLOW_BUILTIN=crypto` and create credential `3alraf Integration Bearer` (Header Auth) from a client created in Admin > Growth > Integrations.
2. Import `00-error-handler`, then `01-health-monitor`; execute 01 manually: GET /features, /ping and /health must all return 200 (green).
3. Turn one feature OFF in Guardrails and run its workflow: it must stop at "Feature OFF - stop" (no write calls in the execution log).
4. Turn it ON and run `11-interactions-ingest` with a test payload through its webhook: expect 201, the item in Admin > Interactions, and a suggested reply fetch.
5. Re-send the same payload: expect the same stored result (idempotent), not a duplicate.
6. Break the secret on purpose: expect 401 and the error handler (00) firing.
7. Activate the schedules one at a time, confirm queue worker + scheduler run, and check the Event log page shows delivered events.
8. Record the n8n version and date you validated in your runbook.

## Owner notifications (all flows end here)
Every flow ends with **Build owner message** (a Code node that summarises the run's API responses, no customer data) followed by **Notify owner**, which calls the shared workflow **3alraf - 99 Notify owner**; the error handler (00) uses it too. **One-time setup:** open workflow 99, click *Send email*, create an SMTP credential named `3alraf Owner Alerts` (Gmail: host `smtp.gmail.com`, port `465`, SSL on, user = your Gmail address, password = a Google **App Password**, never the normal password; needs 2-Step Verification on the Google account), check the From/To addresses, save. To use Telegram or WhatsApp instead, replace only that node. Import order is 99, 00, then 01-20: the template carries the placeholder `__NOTIFY_WORKFLOW_ID__` in each *Notify owner* node, which must be set to the id of workflow 99 after import (n8n UI: open the node and pick the workflow from the list).
