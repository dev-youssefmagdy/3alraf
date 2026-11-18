"""Preprocessing + HTTP contract tests. No model download: the API tests use a fake embedder."""
import base64
from io import BytesIO

import numpy as np
import pytest
from fastapi.testclient import TestClient
from PIL import Image

from app import preprocess
from app.config import Settings
from app.main import create_app


def png(img: Image.Image, fmt: str = 'PNG', **kw) -> bytes:
    buf = BytesIO()
    img.save(buf, fmt, **kw)
    return buf.getvalue()


def test_pixels_shape_and_range():
    a = preprocess.pixels(Image.new('RGB', (300, 120), (255, 0, 0)))
    assert a.shape == (1, 3, 224, 224) and a.dtype == np.float32
    assert a.min() >= -1.0 and a.max() <= 1.0


def test_pad_to_square_on_white_never_crops():
    img = Image.new('RGB', (400, 100), (0, 0, 0))
    sq = preprocess.pad_square(img)
    assert sq.size == (400, 400)
    assert sq.getpixel((200, 10)) == (255, 255, 255)   # padding is white
    assert sq.getpixel((0, 200)) == (0, 0, 0)          # the full width of the original is kept
    assert sq.getpixel((399, 200)) == (0, 0, 0)


def test_transparent_pixels_become_white():
    img = Image.new('RGBA', (10, 10), (0, 0, 0, 0))
    out = preprocess.to_rgb_on_white(img)
    assert out.mode == 'RGB' and out.getpixel((5, 5)) == (255, 255, 255)
    pal = Image.new('P', (10, 10), 0)
    pal.putpalette([0, 0, 0] * 256)
    pal.info['transparency'] = 0
    assert preprocess.to_rgb_on_white(pal).getpixel((1, 1)) == (255, 255, 255)


def test_exif_orientation_is_applied():
    img = Image.new('RGB', (40, 20), (10, 20, 30))
    exif = Image.Exif()
    exif[0x0112] = 6  # rotate 90 CW on display
    decoded = preprocess.decode(png(img, 'JPEG', exif=exif.tobytes()), 40_000_000)
    assert preprocess.to_rgb_on_white(decoded).size == (20, 40)


def test_decode_rejects_garbage_and_bombs():
    with pytest.raises(preprocess.InvalidImage):
        preprocess.decode(b'not an image', 40_000_000)
    with pytest.raises(preprocess.InvalidImage):
        preprocess.decode(png(Image.new('L', (2000, 2000))), 1_000_000)


def test_avif_decodes():
    if 'AVIF' not in Image.registered_extensions().values():
        pytest.skip('Pillow built without AVIF')
    data = png(Image.new('RGB', (64, 64), (0, 128, 0)), 'AVIF')
    assert preprocess.pixels(preprocess.decode(data, 40_000_000)).shape == (1, 3, 224, 224)


class FakeEmbedder:
    model_name = 'test-model@pad224'
    dim = 4

    def run(self, batch):
        assert batch.shape == (1, 3, 224, 224)
        v = np.array([[1.0, 2.0, 2.0, 0.0]], dtype=np.float32)
        return v / np.linalg.norm(v)


@pytest.fixture()
def client():
    settings = Settings(host='127.0.0.1', port=0, model_path=None, model_name='x', threads=1, concurrency=1, max_bytes=50_000, max_pixels=40_000_000)
    with TestClient(create_app(settings, FakeEmbedder())) as c:
        yield c


def test_health(client):
    assert client.get('/health').json() == {'status': 'ok', 'model': 'test-model@pad224', 'dim': 4, 'threads': 1, 'concurrency': 1}


@pytest.mark.parametrize('mode', ['multipart', 'raw'])
def test_embed_returns_base64_float32(client, mode):
    data = png(Image.new('RGB', (50, 30), (200, 10, 10)))
    r = client.post('/embed', files={'image': ('a.png', data, 'image/png')}) if mode == 'multipart' else \
        client.post('/embed', content=data, headers={'content-type': 'application/octet-stream'})
    body = r.json()
    assert r.status_code == 200 and body['model'] == 'test-model@pad224' and body['dim'] == 4
    vec = np.frombuffer(base64.b64decode(body['vector']), dtype='<f4')
    assert vec.shape == (4,) and abs(float(np.linalg.norm(vec)) - 1.0) < 1e-6


def test_embed_errors(client):
    assert client.post('/embed', content=b'', headers={'content-type': 'application/octet-stream'}).status_code == 400
    assert client.post('/embed', content=b'x' * 60_000, headers={'content-type': 'application/octet-stream'}).status_code == 413
    assert client.post('/embed', files={'image': ('a.png', b'y' * 60_000, 'image/png')}).status_code == 413
    r = client.post('/embed', files={'image': ('a.png', b'garbage', 'image/png')})
    assert r.status_code == 422 and r.json()['error'] == 'invalid_image'


def test_refuses_public_bind():
    with pytest.raises(SystemExit):
        Settings(host='0.0.0.0', port=1, model_path=None, model_name='x', threads=1, concurrency=1, max_bytes=1, max_pixels=1).assert_loopback()
    Settings(host='127.0.0.1', port=1, model_path=None, model_name='x', threads=1, concurrency=1, max_bytes=1, max_pixels=1).assert_loopback()
