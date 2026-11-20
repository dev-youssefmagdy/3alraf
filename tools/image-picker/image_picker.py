#!/usr/bin/env python3
"""
Image Picker: find web photos for products and their variants, pick the ones you want, download them.

Free, with no API key and no quota: images come from Bing and DuckDuckGo through the open-source `ddgs` library.
More engines can be added in ENGINES (one function each).

    python image_picker.py serve products.json            # local picker page at http://127.0.0.1:8765
    python image_picker.py serve --product "Whitening Cream 50ml" --brand Starville --variant Red --variant Blue
    python image_picker.py search "Starville whitening cream" -n 20   # print results as JSON

Picked images are saved under ./picked/<product>/<variant>/ and recorded in ./picked/selections.json together with
the page each one came from. Search results are NOT free to use: keep only images the brand / supplier provides for
resellers, or that the owner allowed you to use.
"""
from __future__ import annotations

import argparse
import hashlib
import io
import ipaddress
import json
import mimetypes
import re
import socket
import sys
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
import webbrowser
from datetime import datetime, timezone
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

try:
    from ddgs import DDGS
except ImportError:  # pragma: no cover
    sys.exit('Missing dependency: pip install -r requirements.txt  (ddgs, pillow)')
try:
    from PIL import Image
except ImportError:  # pragma: no cover
    sys.exit('Missing dependency: pip install -r requirements.txt  (ddgs, pillow)')

ROOT = Path(__file__).resolve().parent
MAX_DOWNLOAD = 15 * 1024 * 1024          # bytes per image
ALLOWED_FORMATS = {'JPEG': 'jpg', 'PNG': 'png', 'WEBP': 'webp', 'AVIF': 'avif', 'GIF': 'gif'}
CACHE_DAYS = 7
PER_PAGE = 30
USER_AGENT = 'image-picker/1.0 (+local product photo tool)'


# ---------------------------------------------------------------------------------------------- engines

def _ddgs(query: str, n: int, backend: str) -> list[dict]:
    rows = DDGS().images(query, backend=backend, max_results=n, safesearch='moderate')
    out = []
    for r in rows or []:
        url = r.get('image') or ''
        if not re.match(r'^https?://', url):
            continue
        out.append({
            'image': url,
            'thumbnail': r.get('thumbnail') if re.match(r'^https?://', r.get('thumbnail') or '') else url,
            'title': (r.get('title') or '').strip()[:300],
            'page': r.get('url') if re.match(r'^https?://', r.get('url') or '') else None,
            'source': r.get('source') or urllib.parse.urlparse(url).hostname,
            'width': _int(r.get('width')), 'height': _int(r.get('height')),
        })
    return out


def search_bing(query: str, n: int) -> list[dict]:
    return _ddgs(query, n, 'bing')


def search_duckduckgo(query: str, n: int) -> list[dict]:
    return _ddgs(query, n, 'duckduckgo')


# name -> function(query, max_results) -> list of {image, thumbnail, title, page, source, width, height}
ENGINES = {'bing': search_bing, 'duckduckgo': search_duckduckgo}


def _int(v) -> int | None:
    try:
        return int(v)
    except (TypeError, ValueError):
        return None


# ---------------------------------------------------------------------------------------------- search + cache

class Searcher:
    def __init__(self, cache_dir: Path | None):
        self.cache_dir = cache_dir  # None = no file cache (the caller caches, e.g. the 3alraf admin)
        if cache_dir:
            cache_dir.mkdir(parents=True, exist_ok=True)
        self.lock = threading.Lock()

    def search(self, query: str, engines: list[str], page: int = 1) -> dict:
        """Merged, de-duplicated results of the engines for one page. Each engine answer is cached for CACHE_DAYS."""
        want = PER_PAGE * page
        merged, errors, seen = [], {}, set()
        per_engine = {}
        for name in engines:
            fn = ENGINES.get(name)
            if not fn:
                continue
            try:
                per_engine[name] = self._cached(name, query, want, fn)
            except Exception as e:  # an engine failing must not break the others
                errors[name] = f'{type(e).__name__}: {e}'[:200]
                per_engine[name] = []
        # interleave engines so the first page mixes them
        for i in range(max((len(v) for v in per_engine.values()), default=0)):
            for name, rows in per_engine.items():
                if i < len(rows):
                    r = rows[i]
                    key = _norm_url(r['image'])
                    if key in seen:
                        continue
                    seen.add(key)
                    merged.append({**r, 'engine': name, 'id': hashlib.sha1(key.encode()).hexdigest()[:12]})
        start = PER_PAGE * (page - 1)
        return {'query': query, 'page': page, 'results': merged[start:start + PER_PAGE], 'has_more': len(merged) >= want, 'errors': errors}

    def _cached(self, engine: str, query: str, n: int, fn) -> list[dict]:
        if self.cache_dir is None:
            return fn(query, n)
        path = self.cache_dir / (hashlib.sha1(f'{engine}|{query.lower().strip()}|{n}'.encode()).hexdigest() + '.json')
        if path.exists() and time.time() - path.stat().st_mtime < CACHE_DAYS * 86400:
            return json.loads(path.read_text('utf-8'))
        with self.lock:  # one request at a time: friendlier to the engines, fewer blocks
            rows = fn(query, n)
            time.sleep(1.0)
        path.write_text(json.dumps(rows, ensure_ascii=False), 'utf-8')
        return rows


def _norm_url(u: str) -> str:
    p = urllib.parse.urlparse(u)
    return f'{p.netloc.lower().removeprefix("www.")}{p.path}'


# ---------------------------------------------------------------------------------------------- products

def build_query(product: dict, variant: dict | None = None) -> str:
    name = str(product.get('name') or '').strip()
    brand = str(product.get('brand') or '').strip()
    parts = []
    if brand and brand.lower() not in name.lower():
        parts.append(brand)
    parts.append(name)
    if product.get('model'):
        parts.append(str(product['model']))
    if variant:
        attrs = variant.get('attributes') or {}
        values = list(attrs.values()) if isinstance(attrs, dict) else list(attrs)
        if variant.get('name'):
            values.insert(0, variant['name'])
        for v in values:
            if str(v).lower() not in ' '.join(parts).lower():
                parts.append(str(v))
    return re.sub(r'\s+', ' ', ' '.join(parts)).strip()


def load_targets(products: list[dict]) -> list[dict]:
    """Flatten products into searchable targets: the product itself, then each variant."""
    targets = []
    for pi, p in enumerate(products):
        pid = _slug(p.get('sku') or p.get('name') or f'product-{pi + 1}')
        targets.append({'id': pid, 'kind': 'product', 'product': pid, 'label': p.get('name', pid), 'sku': p.get('sku'),
                        'query': p.get('query') or build_query(p), 'dir': pid})
        for vi, v in enumerate(p.get('variants') or []):
            vid = _slug(v.get('sku') or v.get('name') or f'variant-{vi + 1}')
            label = v.get('name') or ' / '.join(map(str, (v.get('attributes') or {}).values())) or vid
            targets.append({'id': f'{pid}--{vid}', 'kind': 'variant', 'product': pid, 'label': label, 'sku': v.get('sku'),
                            'query': v.get('query') or build_query(p, v), 'dir': f'{pid}/{vid}'})
    ids = [t['id'] for t in targets]
    dup = {i for i in ids if ids.count(i) > 1}
    if dup:
        sys.exit(f'Two products / variants share the same id: {", ".join(sorted(dup))}. Give them different sku values.')
    return targets


def _slug(s: str) -> str:
    s = re.sub(r'[^\w\-]+', '-', str(s).strip().lower(), flags=re.UNICODE).strip('-_')
    return s[:80] or 'item'


# ---------------------------------------------------------------------------------------------- safe download

class _NoPrivateRedirects(urllib.request.HTTPRedirectHandler):
    max_redirections = 3

    def redirect_request(self, req, fp, code, msg, headers, newurl):
        _check_public(newurl)
        return super().redirect_request(req, fp, code, msg, headers, newurl)


def _check_public(url: str) -> None:
    """http(s) only, and every address the host resolves to must be public (never the local network)."""
    p = urllib.parse.urlparse(url)
    if p.scheme not in ('http', 'https') or not p.hostname or p.username or p.password:
        raise ValueError('bad url')
    try:
        infos = socket.getaddrinfo(p.hostname, p.port or (443 if p.scheme == 'https' else 80), proto=socket.IPPROTO_TCP)
    except socket.gaierror:
        raise ValueError('dns') from None
    for info in infos:
        ip = ipaddress.ip_address(info[4][0])
        if not ip.is_global or ip.is_multicast:
            raise ValueError('blocked address')


def download_image(url: str) -> tuple[bytes, str, int, int]:
    """Returns (bytes, extension, width, height). Raises ValueError with a short reason."""
    _check_public(url)
    opener = urllib.request.build_opener(_NoPrivateRedirects)
    req = urllib.request.Request(url, headers={'User-Agent': USER_AGENT, 'Accept': 'image/avif,image/webp,image/png,image/jpeg,image/*;q=0.8'})
    try:
        with opener.open(req, timeout=20) as res:
            if int(res.headers.get('Content-Length') or 0) > MAX_DOWNLOAD:
                raise ValueError('too large')
            data = res.read(MAX_DOWNLOAD + 1)
    except urllib.error.HTTPError as e:
        raise ValueError(f'http {e.code}') from None
    except (urllib.error.URLError, TimeoutError, OSError) as e:
        raise ValueError('unreachable') from e
    if len(data) > MAX_DOWNLOAD:
        raise ValueError('too large')
    try:  # trust the bytes, never the URL or Content-Type
        img = Image.open(io.BytesIO(data))
        img.verify()
        img = Image.open(io.BytesIO(data))
    except Exception:
        raise ValueError('not an image') from None
    fmt = (img.format or '').upper()
    if fmt not in ALLOWED_FORMATS:
        raise ValueError(f'unsupported format {fmt or "?"}')
    return data, ALLOWED_FORMATS[fmt], img.width, img.height


# ---------------------------------------------------------------------------------------------- selections

class Store:
    """picked/selections.json: target id -> list of saved images (file, source image URL, page, engine, size, main)."""

    def __init__(self, out: Path):
        self.out = out
        self.path = out / 'selections.json'
        out.mkdir(parents=True, exist_ok=True)
        self.lock = threading.Lock()
        self.data = json.loads(self.path.read_text('utf-8')) if self.path.exists() else {}

    def save(self, target: dict, items: list[dict]) -> dict:
        folder = self.out / target['dir']
        folder.mkdir(parents=True, exist_ok=True)
        saved, failed = [], []
        main_id = next((i['id'] for i in items if i.get('main')), items[0]['id'] if items else None)
        ordered = sorted(items, key=lambda i: i['id'] != main_id)
        existing = {e['image']: e for e in self.data.get(target['id'], [])}
        for n, item in enumerate(ordered, 1):
            if item['image'] in existing:  # already downloaded earlier: keep the file
                saved.append({**existing[item['image']], 'main': item['id'] == main_id})
                continue
            try:
                data, ext, w, h = download_image(item['image'])
                src = item['image']
            except ValueError as e:
                if not item.get('thumbnail') or item['thumbnail'] == item['image']:
                    failed.append({'id': item['id'], 'error': str(e)})
                    continue
                try:  # the original is gone / blocked: fall back to the engine's (smaller) copy, and say so
                    data, ext, w, h = download_image(item['thumbnail'])
                    src = item['thumbnail']
                except ValueError as e2:
                    failed.append({'id': item['id'], 'error': f'{e}; thumbnail: {e2}'})
                    continue
            name = f'{n:02d}-{hashlib.sha1(data).hexdigest()[:10]}.{ext}'
            (folder / name).write_bytes(data)
            saved.append({'id': item['id'], 'file': f'{target["dir"]}/{name}', 'image': item['image'], 'downloaded_from': src,
                          'page': item.get('page'), 'title': item.get('title'), 'engine': item.get('engine'), 'width': w, 'height': h,
                          'main': item['id'] == main_id, 'thumbnail_only': src != item['image'],
                          'saved_at': datetime.now(timezone.utc).isoformat(timespec='seconds'), 'license': 'unknown - check before use'})
        # files that are no longer selected are removed
        keep = {s['file'] for s in saved}
        for old in self.data.get(target['id'], []):
            if old['file'] not in keep:
                (self.out / old['file']).unlink(missing_ok=True)
        with self.lock:
            self.data[target['id']] = sorted(saved, key=lambda s: not s['main'])
            tmp = self.path.with_suffix('.tmp')
            tmp.write_text(json.dumps(self.data, ensure_ascii=False, indent=2), 'utf-8')
            tmp.replace(self.path)
        return {'saved': self.data[target['id']], 'failed': failed}


# ---------------------------------------------------------------------------------------------- web UI

def make_handler(targets: list[dict], searcher: Searcher, store: Store, engines: list[str]):
    by_id = {t['id']: t for t in targets}
    page = (ROOT / 'picker.html').read_text('utf-8')

    class Handler(BaseHTTPRequestHandler):
        server_version = 'image-picker'

        def log_message(self, fmt, *args):  # quiet
            pass

        def _json(self, obj, status=200):
            body = json.dumps(obj, ensure_ascii=False).encode()
            self.send_response(status)
            self.send_header('Content-Type', 'application/json; charset=utf-8')
            self.send_header('Content-Length', str(len(body)))
            self.send_header('Cache-Control', 'no-store')
            self.end_headers()
            self.wfile.write(body)

        def _same_origin(self) -> bool:
            # the API only answers the page itself (blocks other websites in your browser from calling it)
            host = self.headers.get('Host', '')
            origin = self.headers.get('Origin')
            return host.startswith(('127.0.0.1:', 'localhost:')) and (origin is None or origin == f'http://{host}')

        def do_GET(self):
            path = urllib.parse.urlparse(self.path).path
            if not self._same_origin():
                return self._json({'error': 'forbidden'}, 403)
            if path == '/':
                body = page.encode()
                self.send_response(200)
                self.send_header('Content-Type', 'text/html; charset=utf-8')
                self.send_header('Content-Security-Policy', "default-src 'self'; img-src * data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'")
                self.send_header('Content-Length', str(len(body)))
                self.end_headers()
                return self.wfile.write(body)
            if path == '/api/targets':
                return self._json({'targets': targets, 'engines': engines, 'all_engines': list(ENGINES), 'saved': store.data})
            if path.startswith('/picked/'):
                f = (store.out / urllib.parse.unquote(path[len('/picked/'):])).resolve()
                if store.out.resolve() not in f.parents or not f.is_file():
                    return self._json({'error': 'not found'}, 404)
                self.send_response(200)
                self.send_header('Content-Type', mimetypes.guess_type(f.name)[0] or 'application/octet-stream')
                self.send_header('X-Content-Type-Options', 'nosniff')
                self.end_headers()
                return self.wfile.write(f.read_bytes())
            self._json({'error': 'not found'}, 404)

        def do_POST(self):
            if not self._same_origin() or self.headers.get('Content-Type', '').split(';')[0] != 'application/json':
                return self._json({'error': 'forbidden'}, 403)
            try:
                body = json.loads(self.rfile.read(min(int(self.headers.get('Content-Length') or 0), 2_000_000)) or b'{}')
            except ValueError:
                return self._json({'error': 'bad json'}, 400)
            path = urllib.parse.urlparse(self.path).path
            if path == '/api/search':
                query = str(body.get('query') or '').strip()[:300]
                if not query:
                    return self._json({'error': 'empty query'}, 422)
                chosen = [e for e in body.get('engines') or engines if e in ENGINES]
                return self._json(searcher.search(query, chosen, max(1, min(10, int(body.get('page') or 1)))))
            if path == '/api/save':
                target = by_id.get(body.get('target'))
                if not target:
                    return self._json({'error': 'unknown target'}, 404)
                items = [i for i in body.get('items') or [] if isinstance(i, dict) and re.match(r'^https?://', str(i.get('image') or ''))][:50]
                return self._json(store.save(target, items))
            self._json({'error': 'not found'}, 404)

    return Handler


# ---------------------------------------------------------------------------------------------- CLI

def main() -> None:
    ap = argparse.ArgumentParser(description='Find and pick product / variant photos (Bing + DuckDuckGo, free, no API key).')
    sub = ap.add_subparsers(dest='cmd', required=True)

    s = sub.add_parser('serve', help='open the picker page')
    s.add_argument('products', nargs='?', help='products JSON file (see products.example.json)')
    s.add_argument('--product', help='quick mode: one product name')
    s.add_argument('--brand')
    s.add_argument('--sku')
    s.add_argument('--variant', action='append', default=[], help='quick mode: a variant name (repeat)')
    s.add_argument('--out', default='picked', help='output folder (default ./picked)')
    s.add_argument('--engines', default='bing,duckduckgo')
    s.add_argument('--port', type=int, default=8765)
    s.add_argument('--no-browser', action='store_true')

    q = sub.add_parser('search', help='print results as JSON')
    q.add_argument('query')
    q.add_argument('-n', type=int, default=20)
    q.add_argument('--engines', default='bing,duckduckgo')
    q.add_argument('--page', type=int, default=1)
    q.add_argument('--no-cache', action='store_true', help='do not read / write picked/.cache')

    a = ap.parse_args()
    engines = [e.strip() for e in a.engines.split(',') if e.strip() in ENGINES]
    if not engines:
        sys.exit(f'No known engine in --engines. Available: {", ".join(ENGINES)}')

    if a.cmd == 'search':
        res = Searcher(None if a.no_cache else Path('picked') / '.cache').search(a.query, engines, max(1, min(10, a.page)))
        res['results'] = res['results'][:a.n]
        print(json.dumps(res, ensure_ascii=False, indent=2))
        return

    if a.products:
        products = json.loads(Path(a.products).read_text('utf-8'))
        products = products if isinstance(products, list) else [products]
    elif a.product:
        products = [{'name': a.product, 'brand': a.brand, 'sku': a.sku, 'variants': [{'name': v} for v in a.variant]}]
    else:
        sys.exit('Give a products JSON file or --product "name".')

    out = Path(a.out).resolve()
    targets = load_targets(products)
    server = ThreadingHTTPServer(('127.0.0.1', a.port), make_handler(targets, Searcher(out / '.cache'), Store(out), engines))
    url = f'http://127.0.0.1:{a.port}/'
    print(f'Image Picker: {len(targets)} targets  ·  {url}  ·  saving to {out}  ·  Ctrl+C to stop')
    if not a.no_browser:
        threading.Timer(0.5, lambda: webbrowser.open(url)).start()
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print('\nbye')


if __name__ == '__main__':
    main()
