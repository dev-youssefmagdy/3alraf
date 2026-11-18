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
Date: 2026-11-18. Benchmark scripts, query images, vectors and raw results are in the P1 scratchpad (`.../scratchpad/p1`: `prep.py`, `prep_hard.py`, `bench.py`, `onnx_bench.py`, `ort_rss.py`, `negatives.py`, `php_search.php`, `result_*.json`). No project code, config, data or services were changed.

### 8.1 Test setup
- **Images:** media disk = `local`, root `storage/app/media` (no `MEDIA_ROOT` override). All 1,196 `images.path` files exist (`catalog/products/gifts/*.avif`, RGB, mostly 1000×1000). All are product images, 1,192 products. The catalog is gifts, cosmetics and accessories, not mainly watches.
- **Index:** all 1,196 images, per model. Hit = the correct product (best image per product). Ranking is over all products.
- **Standard set (150 queries):** 50 random products × 3 variants: centre crop 70%; rotate 12° + 10% zoom; darker + lower contrast + JPEG q50.
- **Hard set (100 queries):** the same 50 products as (a) a "phone photo": product at ~65% size on a tinted, noisy background, perspective tilt, blur, warm cast, JPEG q40; and (b) all 3 standard edits stacked. The standard set does not separate the models (they all get 97–100%). The hard set does.
- **CPU:** single-image latency includes decoding and preprocessing, with `torch.set_num_threads(4)`. Indexing is batched (32) with 16 threads.

### 8.2 Results

| Model | dim | Top-1 std | Top-5 std | **Top-1 hard** | Top-5 hard | ms/image (4 thr) | Index 1,196 imgs | Download | RSS (torch) |
|---|---|---|---|---|---|---|---|---|---|
| DINOv2-small (CLS) | 384 | 96.7% | 99.3% | 83% (pad: 88%) | 94% | **29** | 29 s | 88 MB | 0.6 GB |
| DINOv2-base (CLS) | 768 | 97.3% | 99.3% | 88% | 94% | 87 | 86 s | 346 MB | 0.9 GB |
| CLIP ViT-B/32 (openai) | 512 | 99.3% | 100% | 74% (pad: 73%) | 85% | 28 | 21 s | 605 MB | 1.0 GB |
| SigLIP base p16-224 | 768 | 99.3% | 100% | 96% | 100% | 73 | 74 s | 813 MB | 1.2 GB |
| **SigLIP base p16-224 + pad-to-square** | **768** | **100%** | **100%** | **97%** | **100%** | **75** | 74 s | 813 MB (vision ONNX 372 MB) | 1.0 GB torch / **0.75 GB ORT** |

Notes:
- DINOv2 CLS+mean-patch concat did not help (80% hard).
- Excluding the 9 placeholder queries, DINOv2-small reaches 99.3% on the standard set. All its standard-set misses were placeholder cards or a gold vs silver necklace.
- The 3 SigLIP-pad hard misses were all "phone" shots: the correct product came 2nd behind a look-alike (score gap 0.02–0.04), e.g. Starville underarm cream → Starville whitening cream.
- Acceptance criterion 1 (≥ 90% top-1) is met with margin by SigLIP on both sets.

**Runtime: torch vs ONNX Runtime (SigLIP vision tower, 4 threads)**

| Runtime | Latency | Install size | RSS |
|---|---|---|---|
| torch 2.15 CPU | 70 ms | torch 809 MB + transformers 123 MB | ~1.0–1.2 GB |
| onnxruntime 1.x | 65 ms (2 thr: 129 ms, 8 thr: 41 ms) | onnxruntime 78 MB, no torch needed | ~0.75 GB |

- The outputs are identical: cosine ≥ 0.9999995.
- For DINOv2-small, ORT was slower (34 vs 25 ms).

**Search cost**

| Method | Cost |
|---|---|
| numpy brute force, 1,196 × 768 | < 0.1 ms |
| PHP 8.3 (no opcache/JIT on CLI), dim 384: `unpack('g*')` all blobs + dot + `arsort` | 9 + 3 = **12 ms** |
| PHP, dim 768 | 18 + 6 = **24 ms** (35 MB peak) |
| PHP, 10× catalog (11,960 × 768) | 247 ms, 337 MB |

Caching the *unpacked PHP arrays* is a trap. `serialize`/`unserialize` of 1,196 × 768 floats costs 283/135 ms and is 26.6 MB, versus 3.7 MB of raw blobs.

### 8.3 Recommendation
- **Model:** `google/siglip-base-patch16-224`, image tower only, 768-d, L2-normalised.
  - Model string to store: `siglip-base-p16-224@pad224`.
  - It is the only candidate that holds up on phone-like photos (97% vs 88% DINOv2-small, 74% CLIP).
  - It reads printed text on packaging, which this cosmetics-heavy catalog depends on.
  - Its text tower enables text-to-image search later.
  - Apache-2.0 licence.
  - Cost: 75 ms/image instead of 29 ms. That is irrelevant against the 1.5 s budget, and the full index takes ~1.5 min with 1 worker × 4 threads.
- **Runtime:** ONNX Runtime CPU with an exported vision-tower `.onnx` (372 MB).
  - Same speed, ~12× smaller install, ~0.3–0.4 GB less RAM.
  - Do the export once with an `export_onnx.py` (torch + transformers) in a throw-away venv. The service venv then needs only `onnxruntime`, `numpy`, `pillow>=11.2` (AVIF is built in; tested 13.0), `fastapi` and `uvicorn`.
  - Fallback if the export is a hassle for P3: torch CPU. Accuracy is identical.
  - Pin versions: transformers 5 changed `get_image_features` to return an output object.
- **Preprocessing:** the same for indexing and queries.
  1. EXIF-transpose (phone photos).
  2. Convert to RGB. Composite RGBA, LA or P with transparency onto **white**: catalog photos are on white.
  3. **Pad to a square on white.** Don't centre-crop or stretch.
  4. Resize to 224×224 bicubic.
  5. Normalise with mean/std 0.5.

  Padding gave +1 point on both sets for SigLIP and +5 points for DINOv2.
- **Score threshold** (SigLIP-pad cosine; correct-match scores: p1 0.645, p5 0.715, median 0.91; best wrong product: median 0.72, p95 0.83):
  - **Hide results below 0.60.** This keeps 99.6% of correct matches.
  - Show a "no confident match" note when the best score is below 0.70. Empty table/background photos score 0.59–0.65 and store banners 0.61–0.69.
  - Don't show raw cosine as "%". Wrong products routinely score ~0.72, which would read as "72% match". Display `clamp((s − 0.60) / 0.35) × 100`, so 0.60 → 0% and 0.95 → 100%. P2 and P3 should use this.
  - Keep both numbers in config (`image_search.min_score`, `image_search.confident_score`).
- **Service shape** (`image-embedder/`, systemd, 127.0.0.1:8090):
  - FastAPI + uvicorn, **1 worker process**. The model is loaded once at startup (~2–3 s with ORT).
  - Inference runs in a threadpool behind a semaphore of 1–2, with ORT `intra_op_num_threads=4`. Two parallel requests ≈ 8 cores.
  - Endpoints:
    - `POST /embed`: multipart image or raw bytes, max 10 MB, decoded with Pillow and size-capped. Returns `{model, dim, vector: base64 <f4>}`.
    - `GET /health`: `{status, model, dim}`.
  - Optional `POST /embed-batch` for the fill job.
  - Memory budget: systemd `MemoryMax=1.5G`.
  - Laravel queue `embeddings` with 1 worker.
- **Where top-k lives: PHP**, as planned.
  - 24 ms for the whole catalog at 768-d keeps Laravel the single source of truth: no index file to sync, model filter, product/bundle grouping and permissions all in one place.
  - Cache the **raw concatenated float32 string + an id/type map** (≈ 3.7 MB, version-stamped), not unpacked arrays. Unpack per request.
  - Revisit when the index passes ~10k images (≈ 250 ms in PHP): move top-k into the embedder (numpy over a `.npy` exported by Laravel, < 1 ms) or use a vector store.

### 8.4 Risks observed
| Risk | Evidence | Mitigation |
|---|---|---|
| **52 placeholder images** (4.3%): green gradient cards with only the product name as text | DINOv2 scores them 0.99 against each other; all DINOv2 standard misses were these | SigLIP reads the text and handles them. Still, flag `placeholder-*` images in the coverage strip and replace them with real photos. Option: skip them in the index. |
| Same file used for two products | `eyebrow-razor-single` and `eyebrow-razor-set-3-pcs` have identical bytes (score 1.0) | Results are grouped per product, so both show at the same score. A data fix, not a model fix. |
| Look-alike items (same brand packaging, colour/size/pack variants) | Gold vs silver necklace, Starville underarm vs whitening cream, scrunchies 6 vs 12 pack; 4 cross-product pairs ≥ 0.95 | Show the top 24 with scores (as planned). Variants will be close too, so group variants under their product with the variant badge. |
| Test photos are synthetic | No real customer/WhatsApp photos were available | P4 must run ~20 real phone photos of stock items. Expect slightly lower numbers than the hard set. |
| Screenshots with black bars or app chrome | The perspective test left black corners and still got 94% | Optional later: trim uniform borders before padding. |
| Non-product photo returns "something" | Empty backgrounds score up to 0.65 | Floor 0.60 + "no confident match" note below 0.70. |
| CPU contention with PHP-FPM | 75 ms × 4 threads per image | Single queue worker; semaphore in the service; `Nice=10` for the systemd unit. |
| AVIF decode | System Pillow 10.2 cannot open our AVIFs | Service venv must pin `pillow>=11.2`. |

## 9. Design (P2) — awaiting owner approval
Figma page **"Admin · Image search"** in `3alraf — Storefront redesign` (base URL `https://www.figma.com/design/9ToQZAqy6EBNsLI4ufNq59/?node-id=` + id). 22 frames; every frame has a note with its behaviour and accessibility rules.
- Desktop RTL: header + camera 109-38 · drag-over 109-544 · empty 111-1421 · searching 111-2184 · results 110-1249 · low confidence 111-3095 · no matches 112-2797 · service down 112-3284 · file error 112-3756 · coverage 100% 112-4233
- Mobile RTL: 109-960, 113-4173, 113-4230, 113-4431, 113-4666, 113-4941, 113-5009, 113-5078 · English LTR: results 114-5085, empty 114-5160
- Components (frame 106-3): type badge 108-14, result card 108-143, coverage strip 108-210.
- Strength labels: Strong s ≥ 0.85 · Medium 0.75–0.85 · Weak 0.60–0.75 (hidden < 0.60, low-confidence notice when best < 0.70). Strength = clamp((s−0.60)/0.35)×100, shown as a bar, never as raw cosine. Thresholds go in config (`image_search.*`).
- In LTR, icons lead (logical flex). Copy (ar / en), to be used as is:

| Key | Arabic | English |
|---|---|---|
| title / button | بحث بالصورة | Search by image |
| tooltip | بحث بالصورة · أو الصق صورة Ctrl+V | Search by image · or paste an image (Ctrl+V) |
| subtitle | ارفع صورة منتج لنعرض لك أقرب المنتجات والباقات من الكتالوج | Upload a product photo to see the closest products and bundles in the catalog |
| filter | ابحث في: الكل / المنتجات / الباقات | Search in: All / Products / Bundles |
| drop (desktop / mobile) | اسحب صورة المنتج هنا أو اضغط للاختيار / التقط صورة أو اخترها من المعرض | Drag a product photo here or click to choose / Take a photo or choose from gallery |
| paste hint | أو الصق صورة من الحافظة | or paste an image from the clipboard |
| accepted | الصيغ المقبولة: JPG، PNG، WebP، AVIF · حتى 10 ميجابايت | Accepted: JPG, PNG, WebP, AVIF · up to 10 MB |
| choose | اختيار صورة | Choose image |
| tip | لنتائج أدق: صوّر المنتج وحده على خلفية فاتحة وقرّب الكاميرا منه. | For better results: photograph the product on its own, on a light background, close up. |
| drag overlay | أفلِت الصورة للبحث عن المنتج | Drop the image to search |
| your photo / another | صورتك / بحث بصورة أخرى | Your photo / Search another |
| privacy | لا نحفظ الصورة التي ترفعها. | Uploaded photos are not stored. |
| searching | جارٍ البحث… · نقارن صورتك بصور الكتالوج | Searching… · Comparing your photo with catalog images |
| results header | {n} نتيجة · الأقرب أولًا | {n} results · Closest first |
| badges | منتج / متغير / باقة | Product / Variant / Bundle |
| strength | تطابق قوي / تطابق متوسط / تطابق ضعيف | Strong match / Medium match / Weak match |
| best / open / bundle items | أفضل تطابق / فتح / {n} منتجات | Best match / Open / {n} items |
| low confidence | لا يوجد تطابق مؤكد — النتائج تقريبية · أفضل نتيجة أقل من حد الثقة. تأكّد من المنتج قبل أي إجراء. | No confident match — results are approximate · The best result is below the confidence threshold. Check the item before acting on it. |
| no matches | لا توجد منتجات مشابهة · لم نجد في الكتالوج ما يشبه هذه الصورة بدرجة كافية. | No similar products found · Nothing in the catalog looks close enough to this photo. |
| no-match tips | جرّب صورة أوضح للمنتج وحده، بدون خلفية مزدحمة / قُصّ الصورة حول المنتج / أو ابحث بالاسم أو SKU | Try a clearer photo of the product on its own, without a busy background / Crop the photo around the product / Or search by name or SKU |
| service down | البحث بالصورة غير متاح الآن · خدمة التعرّف على الصور لا تستجيب. لم تُحفظ صورتك. حاول مرة أخرى بعد قليل، وإذا تكررت المشكلة أبلغ الدعم الفني. | Image search is unavailable right now · The image recognition service is not responding. Your photo was not stored. Try again in a moment; if it keeps happening, tell technical support. |
| service-down buttons | إعادة المحاولة / ابحث بالاسم بدلًا من ذلك | Try again / Search by name instead |
| too large / wrong type | الملف أكبر من 10 ميجابايت ({x} ميجابايت). اختر صورة أصغر. / نوع الملف غير مدعوم ({ext}). استخدم JPG أو PNG أو WebP أو AVIF. | The file is larger than 10 MB ({x} MB). Choose a smaller image. / This file type is not supported ({ext}). Use JPG, PNG, WebP or AVIF. |
| coverage | تمت فهرسة {a} من {b} صورة · {n} قيد الانتظار / كل صور الكتالوج مفهرسة · {b} صورة / حالة الفهرسة غير متاحة الآن | Indexed {a} of {b} images · {n} pending / All catalog images indexed · {b} images / Index status unavailable right now |
| close hint | Esc للإغلاق | Esc to close |
- Open questions for the owner: see the PM summary of 2026-11-18.

## 10. QA report (P4)
Date: 2026-11-18 · Senior QA. Run on the dev box (APP_ENV=local) against the real stack:
- `php -S 127.0.0.1:8095` for the admin, with `IMAGE_EMBEDDER_URL` set by process env only.
- The embedder (`python -m app`, P1 SigLIP ONNX) on `127.0.0.1:8091`.
- Queue worker on `embeddings`.
- Headless Chrome (playwright-core + `/usr/bin/google-chrome`) logged in as a temporary `owner` admin.

All temporary data (2 admins, 3 Marketing tokens, 1 variant + image, 1 bundle + 3 items, QR/audit/session rows, `media/qa-temp/`) was removed afterwards, and the replaced image file was restored byte-identical. Scripts and screenshots are in the scratchpad `qa/` folder (`http_bench.py`, `ui.mjs`, `shots/`, `figma/cmp-*.png`).

### 10.1 Verdict per acceptance criterion
| AC | Result | Evidence |
|---|---|---|
| 1 Accuracy ≥ 90% top-1 | **PASS** | Real HTTP endpoint, P1 query images: 50 photos cropped + rotated + darker (stacked) **100%** top-1; standard 150 **100%**; phone-like 50 **94%** top-1, 98% top-5. The 3 phone misses: a look-alike ranked 1st twice, and 1 had no hit ≥ 0.60. |
| 2 p95 < 1.5 s | **PASS** | HTTP round trip, warm: p50 122–127 ms, p95 131–135 ms (n=250). Cold index (version bumped before each request): p95 148 ms (n=20). Server `took_ms` p95 ≈ 100–115 ms. First request after the embedder starts: 932 ms. |
| 3 Fill-missing idempotent | **PASS** | `embeddings:fill-missing` ×2 → 0 / 0 (0.31 s each). Replaced one file in place (product 229) → **queued 1**, then 0 on the second run. Only that row changed: other rows' `max(updated_at)` and Σattempts unchanged. Deletes are covered by tests and also exercised by cleanup (the row was removed with the image). MCP / API `fill-missing` → `queued: 0`. |
| 4 Indexed through the queue < 1 min | **PASS** | Replaced image: job ran in 121 ms, searchable at once (rotated photo → product 229 top-1, strong). New variant image + new bundle were queued by the observers. After the embedder came back, both were embedded within ~8 s of the backoff release. |
| 5 Embedder down | **PASS** | Search → 503 `unavailable` + translated message in 34 ms. Coverage → `service_up:false`, and the UI shows "Index status unavailable right now". Jobs → released with backoff (attempts 2, row `pending` with a `retrying:` reason, 0 failed_jobs), then embedded once it was back. UI down state + Retry + "Search by name" work in all 4 layouts. |
| 6 Security | **PASS** (minor notes in §10.4) | See §10.2. |
| 7 UI RTL/LTR, desktop + phone | **PASS** (cosmetic notes) | 162/162 UI checks (embedder up) + 20/20 (embedder down) in ar/en × 1440/390, on the real page. Copy: every §9 fragment matches `lang/*/admin.php` exactly (scripted check, 0 mismatches). Compared side by side with Figma 110-1249, 111-1421, 111-3095, 112-3284, 113-4431, 114-5085. |

### 10.2 Security checks
- **Permissions:** a user without `products.view` gets 403 on search and coverage (and 403 on the products page). A guest gets 401. No CSRF token gets 419.
- **Throttle:** 30 requests → 200, the 31st → 429 with `Retry-After`.
- **Rejected uploads (422 with the §9 message):**
  - PHP code renamed `.jpg`
  - HTML renamed `.jpg`
  - SVG, and SVG renamed `.jpg`
  - `GIF89a` + PHP renamed `.jpg`
  - real GIF
  - an 11 MB file
  - filename `..%2F..%2Fx.php`
  - a decompression-bomb PNG (12000², refused by the embedder's pixel cap)
- **Accepted uploads, harmless:**
  - A valid JPEG with PHP appended (polyglot): it is only decoded, never stored.
  - PNG bytes named `.jpg`.
  - Filename `../../../public/evil.php.jpg`: the name is never used for a path; the DTO uses `query.<guessed ext>`.
- **Query photo not stored:** no new file under `storage/`, `public/` or `/tmp` after all uploads (file listing taken before and after).
- **Embedder exposure:**
  - It refuses `EMBEDDER_HOST` 0.0.0.0, 192.168.1.5 and `::`.
  - It listens on 127.0.0.1 only; LAN IP:8091 → connection refused.
  - `/docs` → 404.
  - It answers 422 for non-images and 413 above 10 MB.
- **Marketing API:**
  - `status`: `marketing:read` → 200; a `suggestions:write:product`-only token → 403.
  - `fill-missing`: no token → 401; `marketing:read` → 403; `embeddings:write` → 200.
  - Unknown field and `limit:0` → 422.
  - The scope migration only widens non-revoked `suggestions:write:product` tokens.
- **MCP tool:** `fill_missing_image_embeddings` only POSTs `image-embeddings/fill-missing` (optional `limit` 1–5000).
- **Responses:** no raw cosine anywhere in the JSON (checked on 270 responses), only `strength` + `match`.

### 10.3 Automated tests
| Suite | Result |
|---|---|
| ImageSearchTest | 12/12 (151 assertions; 11 + 1 QA regression test) |
| MarketingImageEmbeddingsApiTest | 4/4 |
| MarketingSuggestionsApiTest | 10/10 |
| MarketingApiTest | 7/7 |
| CategorySuggestionTest | 4/4 |
| NewProductSuggestionTest | 18/18 |
| ProductVideoTest | 22/22 |
| CatalogTest | 3/4 (pre-existing) |
| BundlesTest | 15/16 (pre-existing) |
| ImageHygieneTest | 4/8 (pre-existing) |
| mcp-server `npm test` | 100/100; `tsc -p tsconfig.build.json --noEmit` clean |
| image-embedder pytest | 11/11 |

**Pre-existing failures confirmed.** I ran a `git worktree` of HEAD (0eafc0b, none of this work) with its own vendor copy and a separate DB (`3alraf_qa_head`, dropped afterwards). It gives the **identical 6 failures**, with the same messages and lines:
- CatalogTest:152: `GET /admin/products/available` → 405. That route no longer exists at HEAD; only its comment is left.
- BundlesTest:144: the COGS / profit assertion.
- ImageHygieneTest:40/59/74/129: file counts 2≠4 and 4≠8.

They are not caused by image search.

### 10.4 Findings
**Coverage strip "1,190 of 1,196 · 6 pending" in the P3 screenshots: mock data, not a bug.**
- The engineer's `ui/render.php` hard-codes `indexed − 6, waiting 6` for the screenshot.
- The Figma frames use the same sample copy.
- The real endpoint returns `{total 1196, indexed 1196, waiting 0, percent 100, complete true}`. The live UI shows "All catalog images indexed · 1,196 images · 100%", and 1,198 / 1,198 while the QA variant and bundle existed.

**Fixed (with a regression test):**
- **Disk leak in the search-index cache (major → fixed).**
  - Cause: `ImageSearchService::index()` put the version stamp in the cache *key*. Every index change (each job, delete, soft-delete) followed by a search wrote a new ~5 MB entry. The file store never deletes entries that are not read again, so they piled up: 21 orphan files were on disk before QA.
  - Fix: one key per model, with the version stored inside the value.
  - Test: `tests/Feature/ImageSearchTest.php`, "keeps ONE cached index entry per model…".
  - Re-checked over HTTP: 20 version bumps now add 1 file instead of 20, and latency is unchanged (warm p50 124 ms, cold p95 231 ms).
  - The 22 orphan files created during QA were deleted. Older ones from P3 remain; `php artisan cache:clear` or deleting the >1 MB files in `storage/framework/cache/data` once is safe.

**Open (not fixed):**
| # | Severity | Issue | Steps | Expected | Actual |
|---|---|---|---|---|---|
| 1 | minor | A 429 (rate limit) shows the "service unavailable" state | 31 searches within a minute | A "too many searches, wait a moment" note | Down state with "The image recognition service is not responding…" + a toastr "Too Many Attempts." No approved copy exists, so this needs UI/UX copy. |
| 2 | minor | An undecodable or oversized-pixel image is reported as "file type not supported (PNG)" | Upload a 12000×12000 PNG | A message about the image itself | The wrong-type message, naming a supported type |
| 3 | minor / cosmetic | In RTL the file name under "Your photo" is left-aligned (`dir="auto"` + LTR name) | Upload any photo in ar | Right-aligned like Figma 110-1249 / 112-3284 | Left-aligned. On mobile it sits between the meta and the button (Figma 113-4431 shows no name there). |
| 4 | cosmetic | Mobile query panel | ar 390 results | A rounded tinted card with margins (113-4431) | A full-width strip with a bottom border |
| 5 | cosmetic | `counts.all` is capped at the limit (24) while `results` can hold limit + bundles (25) | Photo of the QA bundle | — | The UI is consistent (shows 24 cards, header 24), but one row is sent and never shown |
| 6 | minor | Bundle filter with 0 bundles shows an empty grid + "0 results" instead of the no-matches block | Search, then choose "Bundles" | The none state or a short note | An empty grid |
| 7 | info | The embedder docstring says "never written to disk", but Starlette spools multipart uploads > 1 MB to a temp file (deleted after the request; `PrivateTmp` in systemd) | — | — | Acceptable; reword the comment |
| 8 | info | English frame 114-5085 puts icons after the text ("Search another 📷", "Open ↗"); the code follows the §9 rule "icons lead in LTR" | — | — | The code is right; align the Figma frame |
| 9 | info (environment) | The local `jobs` table has 1,192 queued `SyncQrCodesJob` on `default` (older than this work), and the Reverb websocket isn't running locally | — | — | Not related to image search |

Not tested: real customer / WhatsApp phone photos (§8.4 asked for ~20). Only P1's synthetic sets were used, because none are available on the server.

### 10.5 Recommendation
**GO** for deploy. AC1–AC7 pass and the one major issue (cache disk leak) is fixed and tested; the open items are minor or cosmetic and can follow.

Deploy notes:
- Put the ONNX model in `image-embedder/models/` (it is empty in the checkout: `scripts/setup.sh`).
- Run `php artisan cache:clear` once, or delete the old `image_search:index:*:<version>` files.
- Run the migrations.
- Start the `embeddings` worker and the `3alraf-embedder` unit, then `embeddings:fill-missing`.
