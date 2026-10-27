# Growth N9: small-gap closure plan (contract for prompts/31-growth-n9-small-gaps.md)

| # | Gap | Decision |
|---|---|---|
| 1 | Approval handlers don't re-check module switch at approve time | FIX. `ApprovalService::approve` checks the handler's registered feature via GrowthFeatures; OFF => 422 `feature_disabled`, approval stays pending. Registry maps approval type -> feature key |
| 2 | Customer deletion doesn't erase interactions | FIX. Additive `customer_id` nullable on interactions (+ index), set at ingest when the customer is matched by hashed phone/email; Customer delete/anonymise hook calls retention `eraseCustomer` (interactions, small-batch contacts, ambassador PII), audited as `data_erasure` |
| 3 | Ambassador rewards have no payout ledger | FIX. `ambassador_rewards` ledger (affiliate, order, amount, status pending/valid/excluded/paid, reason, valid_at), written idempotently by a daily job when an order passes the return window; report reads the ledger; payout reuses existing AffiliatePayout flow, no new money rules; document in CALCULATIONS.md |
| 4 | `MetricsController::returnReasons` dead code | FIX. Remove |
| 5 | Integration OpenAPI lacks request schemas | FIX. Generate schemas from the FormRequests/validators (request bodies, params, responses, error codes, auth, scopes); test asserts every integration route is documented |
| 6 | RTL not checked; N+1 only read | FIX. Query-count harness tests (DB::listen) on every growth admin page + API list endpoint with seeded volume, asserting a bounded count; RTL: automated check that growth Blade views use logical utilities (ms/me/ps/pe/text-start) and render with dir=rtl in ar; fix violations. Visual browser check only if tests/Browser tooling is runnable, otherwise report honestly |
| 7 | Gift Finder: add-to-cart only, no order link | FIX. Carry gift-session id into cart/order attribution; report completed orders and AOV |
| 8 | Bundle A/B web-only, no order attribution | FIX. Hook API cart/bundle page; attribute orders per variant via order attribution; winner test uses orders when sample allows |
| 9 | No storefront nav link to Gift Finder | FIX. Link shown only when `gift_finder` ON (menu + home CTA) |
| 10 | Interactions inbox last 100 rows | FIX. Yajra DataTables server-side paging/filter for interactions and missed-opportunity lists |
| 11 | No alert when daily ad spend exceeds cap; thresholds global | FIX. Daily-cap breach creates a notification + action log + brief item; per-tenant overrides for lab thresholds via GrowthConfig and settings UI |
| 12 | No customer-facing cancellation reason | FIX. Reason select (reason codes from N6) on the customer cancel flow, web + `/api/v1` |
| 13 | Keyword classifier only | DEFER (needs AI-quality evaluation set; n8n can classify) |
| 14 | Live n8n dry run, Hijri sighting offset, FIFO stock batches, supplier-term tables | DEFER, require external system / larger scope; listed in runbook |

Rules: respect plan sections 6 and 9 of n8n-growth-system.md, CALCULATIONS.md updates for #3, additive migrations, tests in tests/Feature/GrowthSmallGapsTest.php, AR/EN parity, Pint on changed files only, PROGRESS.md <=15 lines. Commit on main (own files only, no push).
