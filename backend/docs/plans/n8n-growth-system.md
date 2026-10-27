# n8n Growth & Business-Intelligence System: implementation plan

Source spec: `/var/www/3alraf/n8n_features.md` (v1.0). This plan is the contract for sprints N0-N8 (`docs/prompts/21-*.md` to `29-*.md`).

## 1. Scope split (what we build here vs. in n8n)

n8n is an external orchestrator. **This repo builds everything Laravel owns**: trusted APIs, signed events, data models, guardrails, approval/audit trails, metrics, admin UI. It also ships **importable n8n workflow templates** (`docs/n8n/workflows/*.json`) plus a runbook. Credentials (WhatsApp/Meta/TikTok/ads tokens) are never committed; the templates reference named n8n credentials.

Principles from the spec that become hard rules in code:
1. Laravel is the source of truth for prices, costs, stock, orders. n8n has **no write scope** for prices/refunds/stock/budgets. Money is calculated by Laravel (never by AI).
2. Every action is idempotent (`event_id` / `idempotency_key`), audited (reason, source, ts, outcome) and tenant-scoped.
3. Anything that spends money, changes prices, replies publicly, or creates a draft offer goes through a **human approval queue**. Created records are always INACTIVE/draft.
4. AI output is data: validated, claim-checked (Product Truth Guard), PII-masked before leaving Laravel.
5. Confirmed data vs. estimates are labelled; no profit figure when costs are missing. ROAS is never presented as profit.

## 2. Team roles and where each applies

| Role | Responsibility |
|---|---|
| CTO (lead) | Architecture, sequencing, scope control, final review of each sprint |
| Backend dev | Services/DTO/repos, migrations, API, jobs, events |
| Frontend dev | Tenant admin Blade UI (Tailwind, DataTables, AR/EN + RTL), approval screens, dashboards |
| Problem solver | Edge cases: dedupe, ordering, failure modes, guardrail logic, attribution rules |
| Business analyst | Formulas, KPI definitions, score weights, thresholds, Egyptian-market rules (docs/CALCULATIONS.md addendum) |
| DB optimizer | Schema, indexes, retention, aggregation tables, N+1 review |
| QA tester | Pest tests (tenant isolation, idempotency, guardrails, failure injection), final review sprint |

## 3. Reuse (do not rebuild)

- Affiliates (codes, clicks, commissions, payouts, tiers) → Ambassador program extends these.
- `DiscountCode`, `Bundle`, `FlashSale`, `Banner`, `BlogPost`, `CustomPage` + AI `GeneratorService` / `DraftValidator` → draft offers and content approval flow.
- AI stack (`AiGateway`, `AiRunner`, `ai_generations`, `RunAiGenerationJob`, `TrendService`, `GlobalMarketTrendService`, `ProductMatcher`) → classification and generation.
- `OrderProfitService`, `ReportService`, `StockLevel/Movement`, `OrderReturn`, `Review`, `Notification`, Spatie activitylog and permissions, Sentry, backup.
- Rules in `docs/CALCULATIONS.md` are authoritative; N0 adds the contribution-margin definition there.

## 4. Architecture

```
Laravel (truth) ──signed webhooks (outbox, event_id)──▶ n8n
Laravel ◀── scoped read API (bearer token + HMAC, rate-limited) ── n8n
Laravel ◀── n8n "proposal/ingest" endpoints (idempotent) ── n8n
Laravel admin UI: approvals · experiments · opportunities · inbox · brief
```

Namespaces: `App\Services\Growth\*` (domain), `App\Http\Controllers\Api\Integration\*` (n8n API, prefix `/api/integration/v1`), `App\Http\Controllers\Tenant\Growth\*` (admin UI), config `config/growth.php` (guardrails, weights, thresholds). Feature flag per tenant: `growth` (+ sub-flags). Permissions `growth.*`.

## 5. Sprints

### N0: Integration foundation (backend, problem solver, DB, QA)
- `integration_clients` (tenant, name, hashed token, scopes[], HMAC secret encrypted, last_used, revoked) + middleware `integration.auth` (bearer + `X-Signature` HMAC-SHA256 over body+timestamp, 5-min replay window, scope check, throttle). No scope for price/refund/stock/ad-budget writes, enforced and tested.
- Outbound: `growth_events` outbox (uuid `event_id`, type, payload, status, attempts, next_attempt) + `DispatchGrowthEventJob` with signed POST, exponential retry, dead-letter state; events emitted from existing flows (order placed/cancelled/returned, low stock, review created, product created, bundle created, affiliate order). Events are meaningful business events only.
- Inbound idempotency: `integration_requests` (client, idempotency_key unique) replays the stored response.
- `growth_action_log` (who/what/why/source/outcome, subject morph) used by every later sprint.
- `growth_approvals` (type, subject, payload, status pending/approved/rejected/expired, requested_by, decided_by, reason, expires_at) + admin screen + `ApprovalService` (approve runs a registered handler in a transaction with lockForUpdate; second approve → 422).
- `growth_experiments` + `growth_experiment_results` (hypothesis, objective, metric, start/end, budget cap, status, outcome, learnings) + admin CRUD (the "experiment and outcome log").
- `config/growth.php`: min margin %, max discount %, stock guardrails, reorder cash cap, ad budget caps, auto-reply allowlist, PII masking rules. Admin UI for tenant overrides.
- Admin: Integration clients page (create token shown once, rotate, revoke), event log with replay.
- BA: add "Contribution margin" + "completed order" definitions to `docs/CALCULATIONS.md` (revenue − COGS − packaging − shipping subsidy − payment/COD fees − other variable; ads shown separately as "after advertising").

### N1: Data & metrics API (backend, BA, DB)
- `Growth\MetricsService`: contribution margin (order/product/channel/bundle), AOV, conversion, CAC, cancel/return rate, repeat rate, days of cover, sell-through, inventory turnover, slow-mover value. Each metric returns `{value, confidence: confirmed|estimated|insufficient, missing[]}`; never a total profit when a cost is missing.
- Read API (`integration:read` scopes, paginated, tenant-scoped, field-whitelisted, no PII by default): `/products`, `/products/{id}/facts`, `/inventory/velocity`, `/orders/summary`, `/orders/attribution`, `/returns/reasons`, `/bundles`, `/customers/segments` (aggregate), `/metrics/*`, `/brief/daily|weekly` data.
- `growth_daily_stats` aggregation table (tenant, date, product/channel dims) built by a scheduled command to keep queries cheap; indexes reviewed by DB optimizer.
- Executive Owner Brief (§15): `BriefService` builds daily/weekly structured payloads with `evidence[]` per recommendation + admin page; AI summarisation only over computed numbers (via existing AI gateway); email/WhatsApp delivery handled by n8n.
- Tests: formula goldens, missing-cost handling, tenant isolation, API scopes.

### N2: Brand Memory, Product Truth Guard, Content Factory (backend, frontend, problem solver)
- `brand_memory` entries (voice, preferred/prohibited words, reply policies, FAQs, approved claims, offer rules) with versioning + admin UI; `approved_product_claims` per product; API `/brand-memory`.
- `TruthGuardService`: extract claims from generated text, check vs product facts/claims/policies, strip/flag unsupported ingredient/benefit/authenticity/result claims, return `{ok, removed[], flagged[]}`; conflicts → human review. Used by every generator and reply suggestion.
- Content Factory: per-product package (short/long description, 3 angles, hooks, reel/TikTok scripts, stories, ad copy, CTAs, FAQ, bundle ideas, photo briefs, A/B variants) in Egyptian Arabic voice, as `ai_generations(content_package)`; review/approve UI; calendar (`content_assets`, `content_calendar`) with manual publishing tasks; `content_asset_id` + UTM tracked link per asset (Content-to-Profit attribution via N5 links). No AI image presented as the real product; no invented ingredients/testimonials.

### N3: Bundle Innovation Engine, Seasonal Calendar, Gift Finder (backend, frontend, BA)
- `BundleProposalService`: candidate selection (active, in-stock, excluded unsuitable), rule-based pairing (complementary categories, popular + slow-mover, budget tiers, occasion), AI only for names/descriptions, **margin computed by Laravel** with config floor; rejected below threshold; proposal → approval → INACTIVE `Bundle` draft via existing `BundleService`. Budget Box, Seasonal Gift Builder, Slow-Mover Pairing, bundle A/B (variant name/image/description with split + results).
- Post-launch metrics: AOV uplift, margin, sell-through, bundle vs individual.
- Seasonal calendar: `seasonal_events` (Ramadan, Eid, Mother's Day, Valentine, back-to-school, Black Friday/White Friday, wedding season; Hijri dates computed, editable), category mapping, lead-time/inventory/margin readiness check, task checklist, `seasonal_memory` (post-season lessons stored) + admin UI.
- Gift Finder: storefront form (occasion, budget, recipient, category, restrictions), explicit-rule filter (price, availability), ranking (fit + margin-aware but **never hides better fit for lower margin**, never exceeds budget silently), AI explanation from real product data only, prepared-cart link, completion/conversion tracking. Web + `/api/v1`.

### N4: Conversations, Voice of Customer, Missed Opportunities (backend, frontend, problem solver, DB)
- `interactions` (channel whatsapp|facebook|instagram|review|site_search|email, external_id unique per channel, thread, product, masked text, hash for dedupe, consent/opt-out flags, language, status) via integration ingest endpoint (idempotent). PII masking service before any AI use.
- Classification fields from n8n/AI: category (price, shipping, availability, trust, photos, size/color, returns, usage, safety), intent (purchase_intent…), urgency, sentiment; ingest accepts these and Laravel can classify via its own AI as fallback.
- Reply suggestions: `SuggestedReplyService` builds answer only from live facts (price/stock/order status) + brand memory, runs TruthGuard; maturity levels L1 classify → L2 suggest → L3 auto-reply for allowlisted tested FAQs only → L4 routing; per-tenant level setting, default L1. Escalation rules (complaint, safety, refund, conflict, missing data) always human. Opt-out honored; 24h-window flag exposed so n8n picks session vs template.
- Intent-to-Sale and Comment-to-Cart: tracked link generator (short code, channel/interaction/product, UTM) + click/visit/order attribution (shared with N5).
- Reputation Radar: z-score/threshold spike detection per product/campaign on complaint rate (min sample), groups similar complaints, raises alert + `pause_auto_reply` flag per topic; never deletes/argues.
- Voice of Customer: aggregation by product/channel/category, recurring-objection detection, Objection-to-Action rules → `improvement_tasks` (owner, deadline, status, before/after metric).
- Missed Opportunity Inbox: explicit rules (inquiries≫orders, traffic/low conversion, unanswered > SLA, abandoned carts (existing cart data), clicks/low profit, demand without content, recurring objection), each with evidence, probable cause + confidence, one experiment suggestion, owner, deadline, result tracking; weekly report endpoint + admin inbox.

### N5: Marketing Experiment Lab and Ambassador program (backend, frontend, BA)
- Lab on top of `growth_experiments`: product pick (stock+margin guard), angles (from N2), hypothesis/audience/budget/duration, content approval; ad-result import endpoint (spend, impressions, clicks, platform ids, per day); linking campaign → UTM links → completed orders/returns; metrics CAC, CVR, AOV, contribution margin after ads, cancel/return rate, ROAS (labelled not profit); recommendation (stop/revise/retest/scale-candidate) only with minimum sample + minimum duration; budget increase requires approval within cap; Creative Fatigue Watch (rolling CTR/CPA decline vs baseline with min sample).
- Small-Batch Launch Lab: interest page/survey/limited-stock tests with transparent pre-order terms flag, decision gate to reorder.
- Ambassador program extending Affiliates: opt-in enrolment, per-channel tracking links, onboarding content/claims guide (static page), reward rules (valid order = delivered, excluding canceled/returned within the disclosed window), disclosure field, idea library, report per participant: new-customer share, valid orders, referral CAC, margin. No fake-review/artificial-engagement rewards (validation + terms text).

### N6: Capital & Inventory Decision Engine, Return Intelligence, Trend Radar, CEO Challenge (backend, BA, DB)
- `InventoryDecisionService`: per product velocity, days of cover, age, lead time + MOQ (new supplier-product fields), classification (A/B/C, margin, seasonality), scenario modelling reorder/promo/bundle/wait with expected impact and cash tied up, ranked recommendations (never executes purchases; creates approval → draft `PurchaseOrder`). Expiry/seasonality/supplier terms considered before clearance. Stock-to-Story (stock OK + weak sales + repeated "how to use" questions → content/bundle task).
- Return Reason Intelligence: add structured reason codes on returns/cancellations (migration + UI + API), aggregation, recommended fix tasks, before/after tracking.
- Market Trend Radar: weekly job reusing `TrendService`/`GlobalMarketTrendService` + internal search data; opportunity score with config weights (demand 25, margin 25, supplier 15, audience 15, risk 10, content 10), evidence + uncertainty; Trend-to-Test creates an experiment, never a purchase.
- Weekly CEO Challenge: question bank (rule-driven from metrics, e.g. shipping cost vs checkout drop), generates hypothesis/experiment/metric/duration/review date; stored and reviewed.

### N7: n8n workflow pack, security hardening, docs (backend, QA, problem solver)
- Importable workflow JSON per module in `docs/n8n/workflows/` (error workflow, retry, dedupe, HMAC verify, approval wait, env separation) + `docs/n8n/README.md` runbook (credential names, webhook URLs, schedules, staging vs production, backup/restore test, data-retention).
- OpenAPI update (`docs/api/openapi.json`) for integration API. Retention commands (`growth:prune`) for interactions/events/PII; deletion on customer erasure. Health endpoint for n8n monitoring. Security headers/IP allowlist option for the integration prefix.

### N8: Full QA / security / performance review (QA, problem solver, DB optimizer)
Adversarial review of N0-N7: tenant leakage, token/HMAC bypass and replay, scope escalation (price/refund/stock), idempotency races, PII leakage into AI/logs, prompt injection through customer text, guardrail bypass, approval double-submit, N+1/indexes, AR/EN/RTL parity, empty/disabled states, failure injection (n8n down, AI down, missing costs). Fix with regression tests (`tests/Feature/GrowthReviewFixesTest.php`), update `docs/PROGRESS.md`, report gaps honestly.

## 6. Delivery rules for every sprint
Follow `backend/CLAUDE.md` layering and `docs/CALCULATIONS.md`; migrations additive + tenant_id indexed; Pest tests incl. cross-tenant isolation; run `vendor/bin/pest --filter=Growth` (full suite baseline: ~21 pre-existing non-AI failures, don't fix, don't add new ones); AR/EN lang parity; append ≤15 lines to `docs/PROGRESS.md`; **each sprint is committed on `main` when finished (own files only, no push)**.

## 7. Spec coverage map

| Spec § | Sprint | Spec § | Sprint |
|---|---|---|---|
| 2 Architecture/integration, 17 Security | N0, N7 | 12 Content Factory | N2 |
| 3 Market radar | N6 | 13 Gift Finder | N3 |
| 4 Bundle engine | N3 | 14 Seasonal calendar | N3 |
| 5 Experiment lab | N5 | 15 Owner Brief | N1 |
| 6 Voice of Customer | N4 | 16.1 Truth Guard, 16.5 Brand Memory | N2 |
| 7 Ambassadors | N5 | 16.2 Margin-aware recs | N3, N6 |
| 8 Capital/inventory | N6 | 16.3 Return intelligence | N6 |
| 9 WhatsApp hub | N4 (+N7 workflows) | 16.4 Small-batch lab | N5 |
| 10 Social hub, Reputation Radar | N4 | 16.6 CEO Challenge | N6 |
| 11 Missed Opportunity Inbox | N4 | 18-21 Roadmap/KPIs/checklist | N0-N8 (KPIs in N1) |

External dependencies not solvable in code: Meta/WhatsApp Business API approval and templates, TikTok/ads API access, n8n hosting. These are covered by the runbook and by graceful "not connected" states.

## 8. Execution order
Strictly sequential N0 → N8 (shared migrations/models); each runs in its own sub-agent, then the CTO reviews the diff summary and test result before starting the next.
