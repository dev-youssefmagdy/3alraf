"""3alraf image embedder: FastAPI app, loopback only, no auth (Laravel is the only caller).

GET  /health -> {status, model, dim, threads, concurrency}
POST /embed  -> multipart field `image` OR the raw image bytes as the body
             <- {model, dim, vector: base64 little-endian float32 (L2-normalised), ms}
Errors: 400 empty, 413 too large, 422 not a decodable image, 503 model not loaded. The image is never written to disk by this code.
"""
import asyncio
import base64
import logging
import time
from contextlib import asynccontextmanager

from fastapi import FastAPI, Request
from fastapi.concurrency import run_in_threadpool
from fastapi.responses import JSONResponse

from . import preprocess
from .config import Settings

log = logging.getLogger('embedder')


def create_app(settings: Settings | None = None, embedder=None) -> FastAPI:
    settings = settings or Settings.load()
    state: dict = {'embedder': embedder}

    @asynccontextmanager
    async def lifespan(_app: FastAPI):
        if state['embedder'] is None:
            from .model import Embedder

            t = time.perf_counter()
            state['embedder'] = Embedder(settings.model_path, settings.model_name, settings.threads)
            log.info('model %s loaded (dim %d) in %.1fs', settings.model_name, state['embedder'].dim, time.perf_counter() - t)
        yield

    app = FastAPI(title='3alraf image embedder', docs_url=None, redoc_url=None, openapi_url=None, lifespan=lifespan)
    gate = asyncio.Semaphore(settings.concurrency)

    def error(status: int, code: str, detail: str = '') -> JSONResponse:
        return JSONResponse({'error': code, 'detail': detail}, status_code=status)

    @app.get('/health')
    async def health():
        emb = state['embedder']
        if emb is None:
            return error(503, 'model_not_loaded')
        return {'status': 'ok', 'model': emb.model_name, 'dim': emb.dim, 'threads': settings.threads, 'concurrency': settings.concurrency}

    async def read_capped(request: Request) -> bytes | None:
        """Raw body, at most max_bytes (None when larger)."""
        chunks, size = [], 0
        async for chunk in request.stream():
            size += len(chunk)
            if size > settings.max_bytes:
                return None
            chunks.append(chunk)
        return b''.join(chunks)

    @app.post('/embed')
    async def embed(request: Request):
        emb = state['embedder']
        if emb is None:
            return error(503, 'model_not_loaded')
        declared = request.headers.get('content-length')
        if declared and declared.isdigit() and int(declared) > settings.max_bytes + 64 * 1024:
            return error(413, 'too_large', f'max {settings.max_bytes} bytes')

        if request.headers.get('content-type', '').startswith('multipart/form-data'):
            form = await request.form(max_files=1, max_fields=4)
            upload = form.get('image')
            if upload is None or not hasattr(upload, 'read'):
                return error(400, 'missing_image', 'multipart field `image` is required')
            data = await upload.read(settings.max_bytes + 1)
            await form.close()
            if len(data) > settings.max_bytes:
                return error(413, 'too_large', f'max {settings.max_bytes} bytes')
        else:
            data = await read_capped(request)
            if data is None:
                return error(413, 'too_large', f'max {settings.max_bytes} bytes')
        if not data:
            return error(400, 'empty', 'no image bytes')

        def work():
            t = time.perf_counter()
            batch = preprocess.pixels(preprocess.decode(data, settings.max_pixels))
            vec = emb.run(batch)[0]
            return vec, (time.perf_counter() - t) * 1000

        async with gate:
            try:
                vec, ms = await run_in_threadpool(work)
            except preprocess.InvalidImage as e:
                return error(422, 'invalid_image', str(e))
        return {'model': emb.model_name, 'dim': int(vec.shape[0]), 'vector': base64.b64encode(vec.astype('<f4').tobytes()).decode('ascii'), 'ms': round(ms, 1)}

    return app
