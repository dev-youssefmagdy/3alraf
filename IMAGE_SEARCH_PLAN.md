# Image search — project plan

Owner: PM / Business analyst · Team: Senior Problem Solver → Senior UI/UX → Senior Software Engineer → Senior QA (run one after another)
Date: 2026-11-17 · Status: planning

## 1. Goal

An admin uploads (or pastes, or drags) a photo, e.g. a supplier picture or a customer's WhatsApp photo, and gets the matching products, variants and bundles from our own catalog, best match first.

To do that, every product, variant and bundle image gets an **image embedding**: a list of numbers that describes what the picture looks like. Similar pictures have similar numbers. Search compares the uploaded photo's embedding with all stored ones.

The embeddings are filled by an MCP tool. It **only adds embeddings for images that are missing one** and never recomputes existing ones.

### Decisions already made (2026-11-17)

| Topic | Decision |
|---|---|
| Embedding engine | Self-hosted open model on our VPS (free per search, photos never leave the server) |
| First release | **Admin panel only** (storefront and Flutter app come later and reuse the same backend) |
| MCP role | Trigger "fill missing" and read coverage status. **Add missing only, never update everything.** |
| Design | First in Figma: existing file `3alraf — Storefront redesign` (key `9ToQZAqy6EBNsLI4ufNq59`), new page "Admin · Image search" |

### Facts from discovery

- 1,192 products and 1,196 images. 0 variants and 0 bundles today; the design must still cover them:
  - Variant images live in the `images` table, linked to the variant.
  - A bundle has a single `bundles.image` column.
- MySQL 8.0 has no vector column type. At this size, comparing in memory in PHP over binary float32 vectors is fast. No vector database is needed.
- Server: 28 cores, 62 GB RAM, Python 3.12 with venv, internet access to download the model, 167 GB free disk, database queue.

## 2. Users and stories

| # | As a… | I want to… | So that… |
|---|---|---|---|
| U1 | Admin | upload, paste or drop a photo on the product list and see matching products, bundles and variants with a similarity % | I can find an item without knowing its name or SKU |
| U2 | Admin | filter the results (products / bundles / all) and open the item with one click | I can act on it right away |
| U3 | Admin | see how much of the catalog is indexed ("1,190 / 1,196 images") | I know when the results are complete |
| U4 | Owner, through Claude (MCP) | say "fill the missing image embeddings" | new or changed images become searchable without touching the ones already done |
| U5 | Admin | have new or replaced images indexed by themselves | I never have to remember to do it |

Out of scope for v1: storefront search, the Flutter app, text-to-image search ("red leather watch"), duplicate-product detection. The backend is built so these can be added later.

## 3. Architecture

```
Admin browser ──upload──▶ Laravel  ImageSearchController (products.view)
                              │  ImageSearchService: embed query ─▶ embedder (127.0.0.1:8090, Python)
                              │  cosine top-k over cached vectors (all embeddings for the current model)
                              ▼
                         results grouped by product / bundle (best image wins), score, thumbnail, links

Images created/replaced ──observer──▶ EmbedImageJob (queue "embeddings") ──▶ embedder ──▶ image_embeddings
Claude ──MCP fill_missing_image_embeddings──▶ Marketing API POST image-embeddings/fill-missing ──▶ queues ONLY missing ones
Scheduler (nightly) ──embeddings:fill-missing──▶ same service (safety net)
```

### 3.1 Embedder service (new, `image-embedder/`)
- Python 3.12 venv, a small HTTP server (FastAPI / uvicorn) **listening only on 127.0.0.1**, run by systemd like `3alraf-mcp`.
- `POST /embed` (image bytes) returns `{model, dim, vector: base64 float32, l2-normalised}`. `GET /health` reports status.
- The model is picked by the Problem Solver after a benchmark on our own product photos. Candidates:
  - DINOv2-small (image-to-image, fine detail)
  - CLIP / SigLIP ViT-B (also allows text search later)
- Runs on the CPU (ONNX Runtime or PyTorch on CPU). A model-name string is stored with every vector, so switching models later means "missing" for the new model, not overwriting.

### 3.2 Data (`image_embeddings`)
`id, source_type (image|bundle), source_id, product_id?, product_variant_id?, bundle_id?, source_hash (sha256 of the file bytes), model, dim, vector (BLOB float32), timestamps`. Unique on `(source_type, source_id, model)`.

**Missing** means: an image with no row for the current model, **or** its `source_hash` differs (the file was replaced). Images whose embedding is already current are never touched. When an image is deleted, its row is deleted too (cascade through an observer).

### 3.3 Laravel
- `ImageEmbeddingService`:
  - `missing()` query
  - `fillMissing(limit)` queues jobs and returns how many
  - `status()`: total, indexed, missing, failed, model
  - `embed(source)`
- `ImageSearchService::search(file, type filter, k=24)`:
  - embed the uploaded photo
  - get the vectors from cache (invalidated by a version stamp whenever a row changes)
  - cosine top-k, grouped per product or bundle, with a minimum score
  - the uploaded photo is **not stored**
- `EmbedImageJob`: retries with backoff and records the failure reason. If the embedder is down, jobs wait instead of losing data.
- Admin: route `admin.image-search`, POST, permission `products.view`, rate-limited, max 10 MB, JPG/PNG/WebP/AVIF.
- Command `embeddings:fill-missing`, scheduled nightly.

### 3.4 MCP (tools that write to an internal index, not to store content)
- Marketing API:
  - `GET image-embeddings/status` (`marketing:read`)
  - `POST image-embeddings/fill-missing` (new scope `embeddings:write`, granted to existing product-writing tokens by migration, as was done for categories)
- MCP tools:
  - `get_image_embedding_status`
  - `fill_missing_image_embeddings` (optional `limit`; idempotent; queues only missing ones; returns how many were queued)
- This is the first MCP write that is not a suggestion. That's acceptable because it changes no customer-visible data. The decision is recorded in `MCP.md`.

## 4. Design (Figma, before code)
New page "Admin · Image search" in the existing file. Arabic RTL first plus English, desktop and mobile widths:
1. The product list header with an **image search** button (camera icon) next to the search box.
2. Search modal with each of its states:
   - empty (drop zone, upload, paste)
   - preview + searching
   - results grid (thumbnail, name, SKU, type badge Product / Variant / Bundle, similarity %, "Open" link)
   - no matches
   - service unavailable
   - file too large / wrong type
3. Coverage strip ("Indexed 1,190 / 1,196 · 6 pending") in the modal footer.

Built from the admin's existing parts (cards, buttons, brand colors, Tailwind tokens). Code follows the approved frames.

## 5. Phases and owners (one at a time)

| Phase | Owner | Output | Done when |
|---|---|---|---|
| P0 | PM / BA | this plan | the owner approves it |
| P1 | Senior Problem Solver | model choice backed by a benchmark on our photos; vector-search math and performance; risks | top-1 hit rate ≥ 90% on the test set, embed time measured, recommendation written in §8 |
| P2 | Senior UI/UX | Figma frames (§4), RTL + LTR, all states | owner approves the frames |
| P3 | Senior Software Engineer | embedder service, migration, services, jobs, observers, admin UI, API, MCP tools, deploy unit, docs | feature tests green, matches Figma |
| P4 | Senior QA | test plan run: functional, RTL, errors, security, performance, accuracy | all acceptance criteria pass, bugs fixed or listed |

## 6. Acceptance criteria
1. Uploading a product's own photo, even cropped, rotated or darker, shows that product first in ≥ 90% of a 50-image test set.
2. Search answers within 1.5 s (p95) for the current catalog on the VPS.
3. `fill_missing_image_embeddings` run twice in a row queues 0 the second time. Existing embeddings are never recomputed. A replaced image is embedded again.
4. A new or replaced image is searchable within 1 minute, through the queue.
5. If the embedder is down, the admin sees a clear message, nothing crashes, and jobs retry later.
6. Uploaded query photos are not stored. Only `products.view` users can search. Endpoints are rate-limited. The embedder is not reachable from outside.
7. Arabic RTL and English layouts match Figma, and the page works on a phone.

## 7. Risks
| Risk | Mitigation |
|---|---|
| The model is weak on very similar watches (same shape, different colour) | Benchmark in P1; show the top 24 with scores so an admin can choose; DINOv2 is strong on fine detail |
| CPU load while filling 1,200 images | Queue with a single worker on "embeddings"; ~0.3 s per image ≈ 6–10 minutes once |
| The embedder service is down | Health check, a clear UI message, job retry with backoff |
| Model change later | The vector is stored with its model name; a new model means "missing", the old vectors are dropped after the switch |
| MCP gets a non-suggestion write | Separate opt-in scope, idempotent, touches only the internal index |

## 8. Problem Solver findings (P1)
_To be filled in P1._
