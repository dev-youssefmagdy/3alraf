import { existsSync } from 'node:fs';
import type { Server } from 'node:http';
import { createApp } from './app.js';
import { ConfigError, loadConfig } from './config.js';
import { createLogger } from './logger.js';

const SHUTDOWN_TIMEOUT_MS = 10_000;

function main(): void {
  if (existsSync('.env')) process.loadEnvFile('.env');

  let config;
  try {
    config = loadConfig();
  } catch (err) {
    // ConfigError messages name variables and rules, never values.
    console.error(err instanceof ConfigError ? err.message : 'Failed to load configuration');
    process.exit(1);
  }

  const logger = createLogger(config.logLevel);
  const { app } = createApp({ config, logger });

  const server: Server = app.listen(config.port, config.host, () => {
    logger.info({ host: config.host, port: config.port, oauth: Boolean(config.oauth), staticBearer: Boolean(config.bearerToken) }, 'mcp server listening');
  });

  let shuttingDown = false;
  const shutdown = (signal: NodeJS.Signals): void => {
    if (shuttingDown) return;
    shuttingDown = true;
    logger.info({ signal }, 'shutting down');
    const force = setTimeout(() => {
      logger.warn('shutdown timed out; closing remaining connections');
      server.closeAllConnections();
      process.exit(1);
    }, SHUTDOWN_TIMEOUT_MS);
    force.unref();
    server.close(() => {
      clearTimeout(force);
      logger.info('stopped');
      process.exit(0);
    });
    server.closeIdleConnections();
  };

  process.on('SIGTERM', shutdown);
  process.on('SIGINT', shutdown);
  process.on('unhandledRejection', (reason) => {
    logger.error({ err: { name: (reason as Error | undefined)?.name } }, 'unhandled rejection');
  });
}

main();
