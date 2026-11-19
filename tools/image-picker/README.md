# Image Picker

Find web photos for a product and each of its variants, click the ones you want, and save them into folders.

- **Free with no time limit:** no API key, no account and no monthly quota. Images come from **Bing** and **DuckDuckGo** through the open-source [`ddgs`](https://pypi.org/project/ddgs/) library.
- Runs on your own computer: a small local page at `http://127.0.0.1:8765`, reachable only from that machine.
- Each product and each variant has its own search and its own selection. Mark one picture as the **main** photo.

## Install

```bash
cd tools/image-picker
python3 -m venv .venv
.venv/bin/pip install -r requirements.txt        # Windows: .venv\Scripts\pip install -r requirements.txt
```

## Use

```bash
# several products with variants, from a file (see products.example.json)
.venv/bin/python image_picker.py serve products.example.json

# one product, quick
.venv/bin/python image_picker.py serve --product "Silk Scrunchies" --brand Glam --variant Red --variant Black

# just print results as JSON (scripts / checks)
.venv/bin/python image_picker.py search "Starville whitening cream" -n 10
```

In the page: choose an item on the left → check the search text (filled from brand + name + variant; tick *white background* for packshots) → **Search** → click pictures to select them, use ★ for the main one → **Save selected**. Use **Load more** for more results.

Saved files go to `picked/<product>/<variant>/01-….jpg`, main photo first. `picked/selections.json` records, for every file, the image URL, the page it came from, the engine, the size and when it was saved. Saving again updates that item: newly picked pictures are downloaded and unticked ones are deleted.

### products.json

| Field | |
|---|---|
| `name` | product name (required) |
| `brand`, `model`, `sku` | optional; `sku` names the folder and must be unique |
| `query` | optional: your own search text instead of the generated one |
| `variants[]` | `sku`, `name` and/or `attributes` (`{"Color": "Red"}`), optional `query` |

## Notes

- **The pictures are not free to use.** Search engines show other people's photos. Keep only photos the brand or supplier provides for resellers, or that the owner allowed you to use. The saved page URL helps you check.
- Results are cached for 7 days in `picked/.cache`, and searches run one at a time, so the engines are not hammered. If an engine starts refusing (rate limit), wait a few minutes. The other engine keeps working.
- Downloads accept only real JPEG, PNG, WebP, AVIF or GIF files of up to 15 MB, and never go to local or private-network addresses. If an original can't be downloaded, the engine's smaller copy is saved and marked `thumbnail_only`.
- **Google** is not included. Its only official API (Custom Search JSON) is closed to new users and stops on 1 January 2027. Reading Google's results page directly breaks often and is against Google's terms. To add another engine, write one function and register it in `ENGINES` in `image_picker.py`.
