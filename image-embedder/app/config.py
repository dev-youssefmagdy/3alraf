"""Settings from environment variables (systemd: /etc/3alraf-embedder.env or Environment= lines)."""
import ipaddress
import os
from dataclasses import dataclass
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent


def _int(name: str, default: int, lo: int, hi: int) -> int:
    try:
        value = int(os.environ.get(name, default))
    except ValueError:
        value = default
    return max(lo, min(hi, value))


@dataclass(frozen=True)
class Settings:
    host: str
    port: int
    model_path: Path
    model_name: str
    threads: int          # ONNX Runtime intra-op threads per inference
    concurrency: int      # inferences allowed at the same time (semaphore)
    max_bytes: int        # request body cap
    max_pixels: int       # decoded image cap (decompression-bomb guard)

    @staticmethod
    def load() -> 'Settings':
        host = os.environ.get('EMBEDDER_HOST', '127.0.0.1')
        model_path = Path(os.environ.get('EMBEDDER_MODEL_PATH', str(ROOT / 'models' / 'siglip-base-p16-224.onnx')))
        return Settings(
            host=host,
            port=_int('EMBEDDER_PORT', 8090, 1, 65535),
            model_path=model_path,
            model_name=os.environ.get('EMBEDDER_MODEL_NAME', 'siglip-base-p16-224@pad224'),
            threads=_int('EMBEDDER_THREADS', 4, 1, 32),
            concurrency=_int('EMBEDDER_CONCURRENCY', 2, 1, 4),
            max_bytes=_int('EMBEDDER_MAX_BYTES', 10 * 1024 * 1024, 1024, 50 * 1024 * 1024),
            max_pixels=_int('EMBEDDER_MAX_PIXELS', 40_000_000, 1_000_000, 200_000_000),
        )

    def assert_loopback(self) -> None:
        """The service has no authentication: it must only ever listen on a loopback address."""
        try:
            loopback = ipaddress.ip_address(self.host).is_loopback
        except ValueError:
            loopback = self.host == 'localhost'
        if not loopback:
            raise SystemExit(f'EMBEDDER_HOST={self.host!r} is not a loopback address; refusing to start (the service has no auth).')
