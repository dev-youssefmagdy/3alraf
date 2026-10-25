# Plan: Bundles, Special Packaging, Offline Payment Proof at Checkout

Team: **Analyst** (this plan) → **Dev agents** (one per feature, sequential: they share OrderCalculator / quote / placement / checkout) → **Problem Solver** (adversarial review + fixes) → **QA** (tests, regression, docs).
Rules for every agent: read `CLAUDE.md`, `docs/CALCULATIONS.md` (update it FIRST for any money rule), `docs/PROGRESS.md`. Layering Controller → FormRequest → DTO → Service → Repository → Model. ar/en strings in lang files. Web AND mobile API (`/api/v1`) must both work. Do NOT commit. Do not touch unrelated uncommitted files (login/landing/layout blades, public/logo.png). Append a section to `docs/PROGRESS.md` when done.

## Findings from analysis
- `PaymentMethodType::WALLET` is already the "offline" type (`requires_proof`, ProofStatus pending/approved/rejected, private disk `tenants/{id}/payment-proofs`, admin approve/reject). BUT proof is uploaded only AFTER the order exists (`OrderController` proof route), so a customer can place unlimited offline orders with no proof. COD is its own type and stays out.
- Checkout limiter `checkout` = 20/min per route/IP; no offline-specific protection.
- Cart/order lines are product+variant only (`cart_items`, `order_items`). Price math lives ONLY in `OrderCalculator`; prices in `PriceResolver`; flow `OrderQuoteService` → `OrderPlacementService`.

## Decisions (business rules)
### 1. Offline gateways (Feature 3) — build FIRST (security-critical)
- "Offline" = `PaymentMethodType::WALLET` (relabel in UI/lang as "Offline / manual transfer"; keep enum value for data compat). COD never offline. COD's prepaid-shipping leg that uses a wallet method is also offline ⇒ proof required.
- Proof is **mandatory in the checkout request** (multipart `proof` file) whenever the chosen payment method (or prepay method) is offline; validated in `CheckoutRequest` (+ pre-validate endpoint, which validates the file too but never stores it), enforced in service layer (not only FormRequest), web + API. Order is created with `proof_status=pending`, `payment_status=review`, in the same DB transaction; if placement fails the stored file is deleted. Existing re-upload-after-reject flow stays.
- File hardening: jpg/jpeg/png/webp/pdf, ≤5 MB, real MIME sniff (not extension), images must decode, dimension cap, random hash filename, private disk only, served with existing nosniff/sandbox headers. Store `orders.payment_proof_hash` (sha256); a hash already used by another order of the tenant ⇒ rejected as reused proof (422) unless that order was rejected/cancelled.
- Anti-abuse limiter `checkout-offline` (applies only when method is offline; layered, all via RateLimiter): per tenant+IP **5 / 10 min**, per tenant+IP **20 / day**, per tenant+phone and per tenant+email **3 / hour**, plus a cap of **3 open (pending-review) offline orders per phone/customer** at once. Counted on attempts that reach upload (including failed validation, so file-spam is limited). Returns 429 with `Retry-After` (web JSON + API envelope). Re-upload route gets its own stricter limiter (5/10 min per order token+IP). Limits configurable in `config/checkout.php` (env overridable). Log (warning) when tripped.
- Admin: payment-method form labels, order view shows proof + hash-reuse warning; nothing else changes.

### 2. Bundles (Feature 1)
- Tenant-admin CRUD (permission `catalog.*` family — reuse existing perm style, new `bundles.*` perms seeded). Tables: `bundles` (tenant_id, name/description translatable, slug unique per tenant, image, price = fixed bundle price, is_active, starts_at/ends_at nullable, sort_order, soft deletes, Audited) and `bundle_items` (bundle_id, product_id, product_variant_id (0 = simple product), quantity ≥1). Components must be listed (active) tenant products/variants; duplicates merged.
- Bundle price is a **fixed price set by the merchant** (must be > 0; UI shows "regular total" and "you save"). Bundle availability = min over components of floor(available_stock / qty_per_bundle) (respect `allow_backorder`). Inactive/out-of-window/component-unavailable ⇒ not purchasable.
- Storefront: bundle card/list section + bundle page (components, savings), add-to-cart. Cart: `cart_items.bundle_id` nullable (a bundle line = bundle_id, product_id/variant 0); quantity = number of bundles. Mobile API: list/show bundles, add bundle to cart, cart + order resources show bundle lines.
- Order modelling (keeps stock, COGS, returns, reports working unchanged): at placement a bundle line is **expanded into component `order_items`** with `order_items.bundle_id`, `bundle_name` (snapshot), `bundle_group` (uuid per bundle line). The bundle price × qty is **allocated to components** proportional to (regular component unit price × component qty), rounded half-up, last component takes the exact remainder (Σ = bundle price × qty, cent-exact, none negative). Components carry unit_cost snapshot normally ⇒ COGS/profit correct. Flash sale does NOT apply inside bundles (bundle price is final). Discount codes treat bundle component lines as ordinary eligible lines. Returns: per component line as today (existing math); UI groups lines by bundle. Stock reservation/deduction goes through existing per-item logic. Money allocation rule written in CALCULATIONS.md (new "Bundles" section) before code.
- Retention purger, QR/other modules: bundles soft-delete; keep rows referenced by order_items.

### 3. Special packaging (Feature 2)
- Tenant-admin CRUD for **packaging options** (`packaging_options`: tenant_id, name/description translatable, image, price (customer-facing fee), cost (merchant cost, default 0), is_active, sort_order, soft deletes, Audited; optional stock link NOT in scope). Setting `packaging_enabled` is implied by having ≥1 active option.
- Customer opts in at cart/checkout ("Special packaging" checkbox → choose an option). It appears as a **separate line/amount** in cart summary, quote, order page, invoice (HTML+PDF), admin order and API. One packaging per order (quantity 1) — documented assumption; option stored on cart (`carts.packaging_option_id`) and order (`orders.packaging_option_id`, `packaging_name` snapshot, `packaging_fee`, `packaging_cost` snapshots).
- Math (CALCULATIONS.md first): `packaging_fee` is added to `total` as its own step, is NOT discountable (discount base = goods only) and not in free-shipping threshold; taxable in tax-exclusive tenants (tax base += packaging_fee) and inside the price when prices_include_tax; payment-fee / COD-fee base includes it (customer pays it online/COD like the rest). Profit: `+ packaging_fee(ex-VAT) − packaging_cost`, recognised on admin confirmation like other income. Returns: packaging fee is NOT refunded on partial returns (kept like COD fee); refunded only when the whole order is cancelled before shipping. Reports: Sales report gets `packaging_fee` column/card like `cod_fee`.
- Mobile API: list packaging options in checkout meta; accept `packaging_option_id` on cart quote/checkout.

## Delivery sequence (agents)
1. **Dev-A Offline payments + rate limiting** (+ its tests).
2. **Dev-B Bundles** (+ tests).
3. **Dev-C Special packaging** (+ tests).
4. **Problem-Solver**: adversarial review of full diff (concurrency, oversell, rounding, tenant isolation, IDOR on proof/bundles, limiter bypass via X-Tenant/IP spoof/phone formatting variants, API parity, mass assignment, file upload attacks) → fix findings.
5. **QA**: full pest suite, routes check, vite build, api:docs regen, browser smoke (`npm run smoke`) incl. new flows, Arabic RTL check of new screens, update docs/PROGRESS.md + CALCULATIONS.md consistency, final report.

## Acceptance
- Offline checkout without file ⇒ 422 (web+API); with file ⇒ order pending/review; COD unaffected; 6th offline attempt in 10 min ⇒ 429.
- Bundle line: stock/COGS/returns/profit identical in total to its components; Σ allocated = bundle price exactly.
- Packaging shows as separate amount everywhere totals are shown; totals identical across quote, placement, invoice, API.
- Pest suite green, no regressions, routes load, vite build OK.
