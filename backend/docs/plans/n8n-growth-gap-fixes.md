# Growth N0/N1 gap-fix plan (sprint N1.5)

CTO decisions for every gap reported by sprints N0 and N1. Contract for `prompts/30-growth-n15-gap-fixes.md`. Runs after N2, before N3.

| # | Gap | Decision | Why |
|---|---|---|---|
| 1 | `stock.low` missed on raw-query stock changes | FIX. Add `growth:scan-low-stock` (every 15 min, per growth-enabled tenant) that emits `stock.low` for products at/below threshold; dedupe with a per-product/day event key so model-event + scan never double-fire | Safety net is cheaper and more reliable than hunting every write path |
| 2 | No central toggle for the growth flag | FIX. Toggle in the central tenant edit screen (central admin only; audited), keep `growth:enable` | Tenants must not self-enable; owner controls rollout |
| 3 | Client scopes/webhook not editable | FIX. Edit form: name, scopes (still blocked from money scopes), webhook URL (UrlGuard SSRF check). Secret/token untouched; action-log entry | Rotation-only is operationally painful |
| 4 | No repository layer | FIX the CRUD models only: Approval, Experiment, Client, Event repositories (project convention). Metrics/aggregation stay in query services, documented as an accepted deviation in PROGRESS.md | Convention for CRUD, pragmatism for analytics SQL |
| 5 | Conversion = orders/carts estimate | FIX. Privacy-friendly `storefront_visits` daily counters (tenant, date, product_id nullable, channel, visits, unique_sessions; no IP/PII, bot UA filtered, middleware on storefront product/catalog pages + API product show). MetricsService conversion = orders/unique sessions, marked `confirmed` once visits exist, else `estimated` | Real conversion is required by §20 KPIs and N4/N5 |
| 6 | Channel only affiliate vs direct | FIX. First-touch attribution capture: utm_source/medium/campaign/content, `ref`, `gclid/fbclid` presence, stored in session/cart token then copied to new `orders.attribution` json (additive migration) + normalised `channel`. Metrics by channel use it. Tracked-link generation stays in N5 | Unblocks channel profitability now |
| 7 | Missing cost = 0 only | FIX without schema change (columns stay NOT NULL; 0 = unknown, documented). Add `DataQuality` check: products/items with cost 0, shipping 0 where a carrier fee is expected; surfaced in brief (`data_quality[]`), metrics `missing[]`, and an admin warning list | Honest metrics without risky migration |
| 8 | Partial returns not netted | FIX. Completed revenue, COGS and margin net of returned item quantities (OrderReturnItem) per CALCULATIONS.md; update the doc and goldens | Required for correct margin |
| 9 | Bundle margin without shipping/fees allocation | FIX. Allocate order-level shipping subsidy, packaging and payment fees to bundle lines by revenue share | Needed before N3 bundle engine relies on it |
| 10 | Brief decisions/outcomes not saved | FIX. `growth_brief_decisions` (recommendation key, status accepted/dismissed/done, owner, note, decided_by, outcome metric before/after fields); Accept can spawn an experiment; UI buttons on the brief page | Closes the learning loop (§15.6) |
| 11 | Conversations/complaints section "not available" | DEFER to N4 (needs interactions data) | Not buildable now |
| 13 | Per-feature on/off switches (new requirement) | FIX FIRST in N1.5: build the registry, GrowthFeatures service, middleware, settings UI, API endpoint per plan §9 of n8n-growth-system.md; retrofit N0, N1, N2 features (brief, approvals, experiments, events, integration API, brand memory, truth guard, content factory) | User requirement |
| 12 | Queue worker + scheduler needed | DOCUMENT in N7 runbook + central health check shows last scheduler/queue heartbeat | Ops, not code |

Rules: follow `docs/plans/n8n-growth-system.md` section 6 plus CALCULATIONS.md (update it for #8, #9). Additive migrations only. Tests in `tests/Feature/GrowthGapFixesTest.php` (+ update existing goldens); tenant isolation; AR/EN parity. Commit on main when done.
