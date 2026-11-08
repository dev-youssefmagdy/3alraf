import express, { type Express, type NextFunction, type Request, type Response } from 'express';
import { rateLimit } from 'express-rate-limit';
import { mcpAuthRouter, getOAuthProtectedResourceMetadataUrl } from '@modelcontextprotocol/sdk/server/auth/router.js';
import { hostHeaderValidation } from '@modelcontextprotocol/sdk/server/middleware/hostHeaderValidation.js';
import { StreamableHTTPServerTransport } from '@modelcontextprotocol/sdk/server/streamableHttp.js';
import type { Logger } from 'pino';
import { createAuthMiddleware, sha256 } from './auth/bearer.js';
import { OAUTH_SCOPES_SUPPORTED, OwnerOAuthProvider } from './auth/oauth.js';
import type { Config } from './config.js';
import { LaravelClient } from './laravel-client.js';
import { buildMcpServer, SERVER_VERSION } from './mcp.js';

const HEALTH_CACHE_MS = 10_000;
const WINDOW_MINUTE = 60_000;
const WINDOW_LOGIN = 15 * 60_000;

export interface AppDeps {
  config: Config;
  logger: Logger;
  fetchImpl?: typeof fetch;
}

export interface BuiltApp {
  app: Express;
  laravel: LaravelClient;
  oauth?: OwnerOAuthProvider;
}

const rpcError = (code: number, message: string) => ({ jsonrpc: '2.0', error: { code, message }, id: null });

const methodNotAllowed = (_req: Request, res: Response): void => {
  res.status(405).set('Allow', 'POST').json(rpcError(-32000, 'Method not allowed: this server is stateless, use POST'));
};

function requestLogger(logger: Logger) {
  return (req: Request, res: Response, next: NextFunction): void => {
    const startedAt = Date.now();
    res.on('finish', () => {
      logger.info({ method: req.method, path: req.path, status: res.statusCode, durationMs: Date.now() - startedAt }, 'http request');
    });
    next();
  };
}

function healthProbe(laravel: LaravelClient): () => Promise<boolean> {
  let cached: { at: number; value: boolean } | undefined;
  return async () => {
    if (cached && Date.now() - cached.at < HEALTH_CACHE_MS) return cached.value;
    const value = await laravel.isReachable();
    cached = { at: Date.now(), value };
    return value;
  };
}

/** Per-credential key: the raw bearer never reaches the limiter's store. */
function clientKey(req: Request, res: Response): string {
  const clientId = res.locals['clientId'];
  return typeof clientId === 'string' ? clientId : sha256(req.headers.authorization ?? 'anonymous').toString('hex');
}

export function createApp({ config, logger, fetchImpl }: AppDeps): BuiltApp {
  const laravel = new LaravelClient({ ...config.laravel, logger, ...(fetchImpl ? { fetchImpl } : {}) });
  const toolContext = { laravel, logger, panelUrl: config.panelUrl };
  const isLaravelReachable = healthProbe(laravel);

  const app = express();
  app.disable('x-powered-by');
  app.set('trust proxy', 'loopback'); // nginx on the same host
  app.use(hostHeaderValidation(config.allowedHosts));
  app.use(requestLogger(logger));

  app.get('/health', async (_req, res) => {
    const reachable = await isLaravelReachable();
    res.status(reachable ? 200 : 503).json({ status: reachable ? 'ok' : 'degraded', version: SERVER_VERSION, laravel: { reachable } });
  });

  let oauth: OwnerOAuthProvider | undefined;
  let resourceMetadataUrl: string | undefined;
  if (config.oauth) {
    oauth = new OwnerOAuthProvider(config.oauth, config.mcpUrl, logger);
    resourceMetadataUrl = getOAuthProtectedResourceMetadataUrl(config.mcpUrl);
    app.use(
      mcpAuthRouter({
        provider: oauth,
        issuerUrl: config.publicUrl,
        resourceServerUrl: config.mcpUrl,
        scopesSupported: OAUTH_SCOPES_SUPPORTED,
        resourceName: '3alraf marketing',
      }),
    );
    // Two ceilings: one per address, and one overall so spreading attempts across addresses does not help.
    app.post(
      '/oauth/consent',
      rateLimit({ windowMs: WINDOW_LOGIN, limit: 10, standardHeaders: 'draft-7', legacyHeaders: false }),
      rateLimit({ windowMs: WINDOW_LOGIN, limit: 50, keyGenerator: () => 'global', standardHeaders: false, legacyHeaders: false }),
      express.urlencoded({ extended: false, limit: '10kb' }),
      oauth.consentHandler,
    );
  }

  const authenticate = createAuthMiddleware({
    logger,
    ...(config.bearerToken ? { staticToken: config.bearerToken } : {}),
    ...(oauth ? { oauth, expectedResource: config.mcpUrl.href } : {}),
    ...(resourceMetadataUrl ? { resourceMetadataUrl } : {}),
  });

  // Counts only rejected attempts, per address, so a valid client is limited solely by its own quota below.
  const failedAuthLimiter = rateLimit({ windowMs: WINDOW_MINUTE, limit: 30, skipSuccessfulRequests: true, standardHeaders: 'draft-7', legacyHeaders: false });
  const clientLimiter = rateLimit({
    windowMs: WINDOW_MINUTE,
    limit: config.rateLimitPerMinute,
    keyGenerator: clientKey,
    standardHeaders: 'draft-7',
    legacyHeaders: false,
    message: rpcError(-32029, 'Rate limit exceeded'),
  });

  app.post('/mcp', failedAuthLimiter, authenticate, clientLimiter, express.json({ limit: config.bodyLimit }), async (req: Request, res: Response) => {
    const server = buildMcpServer(toolContext);
    const transport = new StreamableHTTPServerTransport({ sessionIdGenerator: undefined, enableJsonResponse: true });
    res.on('close', () => {
      void transport.close();
      void server.close();
    });
    try {
      await server.connect(transport);
      await transport.handleRequest(req, res, req.body);
    } catch (err) {
      logger.error({ err: { name: (err as Error).name } }, 'mcp request failed');
      if (!res.headersSent) res.status(500).json(rpcError(-32603, 'Internal server error'));
    }
  });
  app.get('/mcp', failedAuthLimiter, authenticate, methodNotAllowed);
  app.delete('/mcp', failedAuthLimiter, authenticate, methodNotAllowed);

  app.use((_req, res) => {
    res.status(404).json({ error: 'not_found' });
  });

  app.use((err: Error & { status?: number; type?: string }, _req: Request, res: Response, _next: NextFunction) => {
    if (res.headersSent) return;
    if (err.type === 'entity.too.large') {
      res.status(413).json(rpcError(-32600, 'Request body too large'));
      return;
    }
    if (err.type === 'entity.parse.failed') {
      res.status(400).json(rpcError(-32700, 'Parse error'));
      return;
    }
    logger.error({ err: { name: err.name, status: err.status } }, 'unhandled error');
    res.status(err.status && err.status < 500 ? err.status : 500).json({ error: 'server_error' });
  });

  return { app, laravel, ...(oauth ? { oauth } : {}) };
}
