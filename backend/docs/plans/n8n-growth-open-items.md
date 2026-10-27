# Growth N11: open-items analysis and plan (contract for prompts/33-growth-n11-open-items.md)

## Analysis (verified against code/DB/tests)

### A. Demo seeder failure (blocks demos)
- Cause: `TenantDemoSeeder::orders()` places orders with `prepayMethodId` = wallet. `OrderPlacementService::place` (line ~51) now requires an uploaded proof for offline methods (sprint 2 hardening) and the seeder passes none, so every seeded order throws `proof_required`. The seeder was never updated after the payments hardening.
- Impact: no demo orders, so metrics/brief/lab/gift pages are empty in demos and in the old browser smoke.
- Fix: seed prepaid-shipping demo orders by placing with COD and then setting the prepay fields directly (same `forceFill` the seeder already uses for proof_status/paid_amount); no weakening of the proof rule. Add a seeder smoke test (seeder runs clean, >= 10 orders, statuses as planned).

### B. Pre-existing suite failures: root cause found (important)
- 17 of the 21 known failures (OfflineCheckoutTest 10, ReviewFixes 3, Payments 2, Orders 1, Storefront 1) are 401/302 on guest web checkout. Cause: commit 21780b3 deliberately moved web `checkout*` routes under `auth:web` ("Checkout needs a signed-in customer; guests are sent to login"). The tests (and `docs/REQUIREMENTS.md` line 33, "guest or customer") still assume guest checkout. They are STALE, not bugs. `/api/v1` checkout still allows guests.
- DECISION NEEDED FROM OWNER (default below): keep web checkout signed-in-only. Default plan: update the tests to authenticate a customer (and keep one test asserting guest => login redirect/401), update REQUIREMENTS.md, no product-code change. If the owner wants guest web checkout back, this item becomes a route change instead.
- The other 4 (PackagingTest 1, SecurityHardening inactive city 1, QrCodeTest logo 1, GrowthTest affiliate click 1) get a one-line root-cause each; fix when trivial, otherwise document. The affiliate-click test is the same guest-checkout cause.

### C. Supplier price lists
- State: `Supplier` has no prices. Reorder drafts use `tenant_products.cost` and one `ProductSupplyTerm` supplier; purchase history (`purchase_order_items.unit_cost` per supplier) is unused. Nothing compares suppliers.
- Now justified: N6 reorder drafts exist and need a supplier/price choice.
- Design (two layers, additive): (1) derived, no schema: `SupplierPriceService` returns last/avg/min unit cost per supplier+product from received POs; (2) explicit `supplier_product_prices` (tenant_id, supplier_id, product_id, variant_id 0, unit_cost, moq, lead_time_days, valid_from, valid_to, is_preferred, note) with admin CRUD under Suppliers. Reorder proposal ranks suppliers by landed unit cost within lead-time need, shows alternatives and cost variance vs product cost, flags a cost that moves margin below the guardrail. Never auto-changes `tenant_products.cost` (CALCULATIONS.md rule); approval still creates a DRAFT PO only. Update CALCULATIONS.md.

### D. Classifier measurement on real data
- State: dev DB has 0 interactions (not even migrated); existing 157-message eval set is author-biased (same author wrote keywords and labels): 89.8/79.0/89.2%.
- Real data cannot be invented. Build the loop so real accuracy is measured as soon as traffic exists, and remove author bias now:
  1. Blind set: a separate agent that must NOT read `InteractionService`/keyword lists writes >= 200 messages (Egyptian Arabic dialect, Franco-Arabic, English, typos, mixed intent, sarcasm, negations, spam) with labels, in `tests/Fixtures/growth/interactions_blind.json`; `growth:eval-classifier --set=blind`.
  2. Human feedback: admin interactions page gets "correct label" controls storing `reviewed_category/intent/sentiment`, `reviewed_by/at` (additive migration, audited). `growth:export-labeling-sample` exports a stratified, already-masked sample (no PII) for offline review.
  3. `growth:eval-classifier --real` computes accuracy/confusion on reviewed rows only; reports N and refuses a verdict below 300 reviewed rows ("insufficient data"). Config gate: AI fallback / auto-reply L3 stay blocked unless the last real eval meets `classifier.target_accuracy` (stored result per tenant, shown on settings).
  4. Improve keywords from the blind-set failures only after recording the before/after numbers.

## Sprint N11 scope (in order)
A seeder fix + test, B stale-test cleanup (after owner decision), C supplier prices, D classifier loop. Rules: plans n8n-growth-system.md sections 6 and 9, switches (supplier prices under `inventory_engine`; classifier tooling under `interactions`), additive migrations, tests in tests/Feature/GrowthOpenItemsTest.php, AR/EN parity, Pint on changed files, PROGRESS.md <= 15 lines, full suite failure list must shrink to the non-stale remainder. Commit on main (own files only; never n8n_features.md or config/qr.php).
