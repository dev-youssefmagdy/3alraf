import { z } from 'zod';

const bool = (fallback: boolean) =>
  z
    .enum(['true', 'false', '1', '0'])
    .optional()
    .transform((v) => (v === undefined ? fallback : v === 'true' || v === '1'));

const url = z.url({ protocol: /^https?$/ });

const optionalString = z
  .string()
  .optional()
  .transform((v) => (v === undefined || v.trim() === '' ? undefined : v));

const LOOPBACK_HOSTS = new Set(['localhost', '127.0.0.1', '[::1]']);
const DEFAULT_REDIRECT_URIS = 'https://claude.ai/api/mcp/auth_callback';

const envSchema = z.object({
  MCP_HOST: z.string().default('127.0.0.1'),
  MCP_PORT: z.coerce.number().int().min(1).max(65535).default(8787),
  MCP_PUBLIC_URL: optionalString.pipe(url.optional()),
  MCP_ALLOWED_HOSTS: optionalString,
  MCP_BEARER_TOKEN: optionalString.pipe(z.string().min(32, 'must be at least 32 characters').optional()),
  MCP_RATE_LIMIT_PER_MINUTE: z.coerce.number().int().min(1).default(120),
  MCP_BODY_LIMIT: z.string().default('1mb'),

  MCP_OAUTH_ENABLED: bool(false),
  MCP_OAUTH_OWNER_PASSWORD: optionalString.pipe(z.string().min(16, 'must be at least 16 characters').optional()),
  MCP_OAUTH_STATE_FILE: optionalString,
  MCP_OAUTH_REDIRECT_URIS: z.string().default(DEFAULT_REDIRECT_URIS),
  MCP_OAUTH_ACCESS_TTL_SECONDS: z.coerce.number().int().min(60).default(3600),
  MCP_OAUTH_REFRESH_TTL_DAYS: z.coerce.number().int().min(1).default(30),

  LARAVEL_BASE_URL: url,
  LARAVEL_SERVICE_TOKEN: z.string().regex(/^mkt_[A-Za-z0-9]+\.[A-Za-z0-9]+$/, 'must be a mkt_ service token'),
  LARAVEL_TIMEOUT_MS: z.coerce.number().int().min(100).default(20000),
  LARAVEL_GET_RETRIES: z.coerce.number().int().min(0).max(5).default(2),
  LARAVEL_RETRY_BASE_MS: z.coerce.number().int().min(0).default(300),
  MARKETER_PANEL_URL: optionalString.pipe(url.optional()),

  LOG_LEVEL: z.enum(['fatal', 'error', 'warn', 'info', 'debug', 'trace', 'silent']).default('info'),
});

export interface OAuthConfig {
  ownerPassword: string;
  stateFile?: string;
  redirectUris: string[];
  accessTtlSeconds: number;
  refreshTtlSeconds: number;
}

export interface Config {
  host: string;
  port: number;
  publicUrl: URL;
  mcpUrl: URL;
  allowedHosts: string[];
  bearerToken?: string;
  oauth?: OAuthConfig;
  rateLimitPerMinute: number;
  bodyLimit: string;
  laravel: {
    baseUrl: string;
    serviceToken: string;
    timeoutMs: number;
    getRetries: number;
    retryBaseMs: number;
  };
  panelUrl: string;
  logLevel: string;
}

export class ConfigError extends Error {}

const trimSlash = (s: string): string => s.replace(/\/+$/, '');
const csv = (s: string): string[] =>
  s
    .split(',')
    .map((v) => v.trim())
    .filter(Boolean);

/** Validates the environment. Error messages name the variable and the rule, never its value. */
export function loadConfig(env: Record<string, string | undefined> = process.env): Config {
  const parsed = envSchema.safeParse(env);
  if (!parsed.success) {
    const issues = parsed.error.issues.map((i) => `${i.path.join('.')}: ${i.message}`).join('; ');
    throw new ConfigError(`Invalid configuration: ${issues}`);
  }
  const e = parsed.data;

  if (e.MCP_OAUTH_ENABLED && !e.MCP_OAUTH_OWNER_PASSWORD) {
    throw new ConfigError('Invalid configuration: MCP_OAUTH_OWNER_PASSWORD is required when MCP_OAUTH_ENABLED=true');
  }
  if (!e.MCP_BEARER_TOKEN && !e.MCP_OAUTH_ENABLED) {
    throw new ConfigError('Invalid configuration: set MCP_BEARER_TOKEN and/or MCP_OAUTH_ENABLED=true; refusing to run without authentication');
  }

  const publicUrl = new URL(e.MCP_PUBLIC_URL ?? `http://${e.MCP_HOST}:${e.MCP_PORT}`);
  if (e.MCP_OAUTH_ENABLED && publicUrl.protocol !== 'https:' && !LOOPBACK_HOSTS.has(publicUrl.hostname)) {
    throw new ConfigError('Invalid configuration: MCP_PUBLIC_URL must be https when OAuth is enabled');
  }

  const redirectUris = csv(e.MCP_OAUTH_REDIRECT_URIS);
  for (const uri of redirectUris) {
    if (!URL.canParse(uri)) throw new ConfigError('Invalid configuration: MCP_OAUTH_REDIRECT_URIS contains an invalid URL');
  }

  const allowedHosts = e.MCP_ALLOWED_HOSTS ? csv(e.MCP_ALLOWED_HOSTS) : [...new Set([publicUrl.hostname, '127.0.0.1', 'localhost'])];

  const config: Config = {
    host: e.MCP_HOST,
    port: e.MCP_PORT,
    publicUrl,
    mcpUrl: new URL('/mcp', publicUrl),
    allowedHosts,
    rateLimitPerMinute: e.MCP_RATE_LIMIT_PER_MINUTE,
    bodyLimit: e.MCP_BODY_LIMIT,
    laravel: {
      baseUrl: trimSlash(e.LARAVEL_BASE_URL),
      serviceToken: e.LARAVEL_SERVICE_TOKEN,
      timeoutMs: e.LARAVEL_TIMEOUT_MS,
      getRetries: e.LARAVEL_GET_RETRIES,
      retryBaseMs: e.LARAVEL_RETRY_BASE_MS,
    },
    panelUrl: trimSlash(e.MARKETER_PANEL_URL ?? e.LARAVEL_BASE_URL),
    logLevel: e.LOG_LEVEL,
  };
  if (e.MCP_BEARER_TOKEN) config.bearerToken = e.MCP_BEARER_TOKEN;
  if (e.MCP_OAUTH_ENABLED && e.MCP_OAUTH_OWNER_PASSWORD) {
    config.oauth = {
      ownerPassword: e.MCP_OAUTH_OWNER_PASSWORD,
      redirectUris,
      accessTtlSeconds: e.MCP_OAUTH_ACCESS_TTL_SECONDS,
      refreshTtlSeconds: e.MCP_OAUTH_REFRESH_TTL_DAYS * 86400,
      ...(e.MCP_OAUTH_STATE_FILE ? { stateFile: e.MCP_OAUTH_STATE_FILE } : {}),
    };
  }
  return config;
}
