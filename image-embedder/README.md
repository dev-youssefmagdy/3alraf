# 3alraf image embedder

Small Python service that turns an image into a 768-number vector (an "embedding") for the admin **search by image** (see `IMAGE_SEARCH_PLAN.md`). Laravel calls it to index catalog images (queue `embeddings`) and to embed the photo an admin uploads. Laravel stores the vectors and does the search itself; this service keeps nothing.

- Model: `google/siglip-base-patch16-224`, image tower only, exported to ONNX (372 MB), run with ONNX Runtime on the CPU. Apache-2.0.
- Model string stored with every vector: `siglip-base-p16-224@pad224`. Laravel's `IMAGE_SEARCH_MODEL` must match it.
- Preprocessing (same for indexing and queries): EXIF transpose → RGB, transparency on white → pad to a square on white → 224×224 bicubic → mean/std 0.5. Output is L2-normalised.
- About 75 ms per image with 4 threads, ~0.75 GB RAM.

## Endpoints (127.0.0.1 only, no auth)

| Method | Path | Body | Answer |
|---|---|---|---|
| GET | `/health` | | `{status, model, dim, threads, concurrency}` |
| POST | `/embed` | multipart field `image`, or the raw image bytes | `{model, dim, vector, ms}`; `vector` = base64 of little-endian float32 |

Errors: `400` empty, `413` larger than `EMBEDDER_MAX_BYTES`, `422 invalid_image` (not decodable, or more than `EMBEDDER_MAX_PIXELS`), `503` model not loaded. The app refuses to start on a non-loopback address.

## Settings (environment)

| Variable | Default | |
|---|---|---|
| `EMBEDDER_HOST` | `127.0.0.1` | must be a loopback address |
| `EMBEDDER_PORT` | `8090` | Laravel: `IMAGE_EMBEDDER_URL=http://127.0.0.1:8090` |
| `EMBEDDER_MODEL_PATH` | `models/siglip-base-p16-224.onnx` | |
| `EMBEDDER_MODEL_NAME` | `siglip-base-p16-224@pad224` | |
| `EMBEDDER_THREADS` | `4` | ONNX Runtime threads per inference |
| `EMBEDDER_CONCURRENCY` | `2` | inferences at the same time |
| `EMBEDDER_MAX_BYTES` | `10485760` | request cap (10 MB) |
| `EMBEDDER_MAX_PIXELS` | `40000000` | decoded image cap |

## Install (server, once)

Needs Python 3.11+ with `venv` (the server has 3.12) and internet access for the one-off model export.

```bash
cd /var/www/3alraf/image-embedder
sudo scripts/setup.sh                      # creates .venv, exports models/siglip-base-p16-224.onnx (~5 min, ~3 GB temp download, deleted after)
sudo cp deploy/3alraf-embedder.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now 3alraf-embedder
curl -s 127.0.0.1:8090/health
```

If port 8090 is taken, put `EMBEDDER_PORT=8091` in `/etc/3alraf-embedder.env` and set `IMAGE_EMBEDDER_URL=http://127.0.0.1:8091` in `backend/.env`.

`scripts/setup.sh --onnx /path/model.onnx` copies an already exported model instead of exporting. After that, `../deploy.sh --embedder` keeps the venv up to date and restarts the service.

## Development

```bash
python3 -m venv .venv && .venv/bin/pip install -r requirements-dev.txt
.venv/bin/python -m pytest -q tests        # preprocessing + HTTP contract, no model needed
EMBEDDER_PORT=8091 .venv/bin/python -m app  # needs the model file
```
