"""Entry point: `python -m app` (one uvicorn worker; the model is loaded once at startup)."""
import logging

import uvicorn

from .config import Settings
from .main import create_app


def main() -> None:
    logging.basicConfig(level=logging.INFO, format='%(asctime)s %(levelname)s %(name)s: %(message)s')
    settings = Settings.load()
    settings.assert_loopback()
    if not settings.model_path.is_file():
        raise SystemExit(f'Model file not found: {settings.model_path} (run scripts/setup.sh)')
    uvicorn.run(create_app(settings), host=settings.host, port=settings.port, workers=1, log_level='info',
                access_log=False, timeout_keep_alive=5, limit_concurrency=32, server_header=False)


if __name__ == '__main__':
    main()
