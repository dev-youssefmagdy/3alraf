import { randomBytes, randomUUID } from 'node:crypto';
import { chmodSync, existsSync, mkdirSync, readFileSync, renameSync, writeFileSync } from 'node:fs';
import { dirname } from 'node:path';
import type { Request, RequestHandler, Response } from 'express';
import type { AuthorizationParams, OAuthServerProvider } from '@modelcontextprotocol/sdk/server/auth/provider.js';
import type { OAuthRegisteredClientsStore } from '@modelcontextprotocol/sdk/server/auth/clients.js';
import type { AuthInfo } from '@modelcontextprotocol/sdk/server/auth/types.js';
import type { OAuthClientInformationFull, OAuthTokenRevocationRequest, OAuthTokens } from '@modelcontextprotocol/sdk/shared/auth.js';
import {
  InvalidClientMetadataError,
  InvalidGrantError,
  InvalidTargetError,
  InvalidTokenError,
  TemporarilyUnavailableError,
} from '@modelcontextprotocol/sdk/server/auth/errors.js';
import type { Logger } from 'pino';
import type { OAuthConfig } from '../config.js';
import { safeEqual, sha256 } from './bearer.js';

/**
 * Single-owner OAuth 2.1 authorization server. Claude's hosted connectors need OAuth (static headers are a limited beta), and this project
 * has no identity provider, so the "sign in" is one owner password checked on a consent page. Only the SHA-256 of tokens is stored.
 */

export const OAUTH_SCOPE = 'mcp';
export const OAUTH_SCOPES_SUPPORTED = [OAUTH_SCOPE, 'offline_access'];

const PENDING_TTL_MS = 10 * 60 * 1000;
const CODE_TTL_MS = 60 * 1000;
const MAX_PENDING = 50;
const MAX_CLIENTS = 100;
const MAX_PASSWORD_ATTEMPTS = 5;

interface TokenRecord {
  kind: 'access' | 'refresh';
  clientId: string;
  scopes: string[];
  resource: string;
  expiresAt: number;
}

interface PersistedState {
  clients: Record<string, OAuthClientInformationFull>;
  tokens: Record<string, TokenRecord>;
}

interface PendingRequest {
  client: OAuthClientInformationFull;
  params: AuthorizationParams;
  expiresAt: number;
  attempts: number;
}

interface CodeRecord {
  clientId: string;
  codeChallenge: string;
  redirectUri: string;
  scopes: string[];
  resource: string;
  expiresAt: number;
}

const tokenKey = (token: string): string => sha256(token).toString('hex');
const newSecret = (prefix: string): string => `${prefix}${randomBytes(32).toString('base64url')}`;

const escapeHtml = (s: string): string =>
  s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c] as string);

export class OwnerOAuthProvider implements OAuthServerProvider {
  private state: PersistedState = { clients: {}, tokens: {} };
  private readonly pending = new Map<string, PendingRequest>();
  private readonly codes = new Map<string, CodeRecord>();

  constructor(
    private readonly config: OAuthConfig,
    private readonly resourceUrl: URL,
    private readonly logger: Logger,
  ) {
    this.load();
  }

  get clientsStore(): OAuthRegisteredClientsStore {
    return {
      getClient: (clientId) => this.state.clients[clientId],
      registerClient: (client) => this.registerClient(client),
    };
  }

  private registerClient(client: Omit<OAuthClientInformationFull, 'client_id' | 'client_id_issued_at'>): OAuthClientInformationFull {
    // Only allowlisted callbacks are kept, so a stolen code can never be redirected to a third party.
    const redirectUris = client.redirect_uris.filter((uri) => this.config.redirectUris.includes(uri));
    if (redirectUris.length === 0) {
      throw new InvalidClientMetadataError('redirect_uris must include an allowed callback URL');
    }
    this.pruneTokens();
    if (Object.keys(this.state.clients).length >= MAX_CLIENTS) this.evictIdleClient();

    const registered: OAuthClientInformationFull = {
      ...client,
      redirect_uris: redirectUris as OAuthClientInformationFull['redirect_uris'],
      client_id: randomUUID(),
      client_id_issued_at: Math.floor(Date.now() / 1000),
    };
    this.state.clients[registered.client_id] = registered;
    this.save();
    this.logger.info({ clientId: registered.client_id }, 'oauth client registered');
    return registered;
  }

  private evictIdleClient(): void {
    const active = new Set(Object.values(this.state.tokens).map((t) => t.clientId));
    const idle = Object.values(this.state.clients)
      .filter((c) => !active.has(c.client_id))
      .sort((a, b) => (a.client_id_issued_at ?? 0) - (b.client_id_issued_at ?? 0))[0];
    if (!idle) throw new TemporarilyUnavailableError('Too many registered clients');
    delete this.state.clients[idle.client_id];
  }

  private resolveResource(resource: URL | undefined): string {
    if (resource && resource.href !== this.resourceUrl.href) {
      throw new InvalidTargetError('Unknown resource');
    }
    return this.resourceUrl.href;
  }

  private grantedScopes(requested: string[] | undefined): string[] {
    return [...new Set([OAUTH_SCOPE, ...(requested ?? []).filter((s) => OAUTH_SCOPES_SUPPORTED.includes(s))])];
  }

  async authorize(client: OAuthClientInformationFull, params: AuthorizationParams, res: Response): Promise<void> {
    this.resolveResource(params.resource);
    this.prunePending();
    if (this.pending.size >= MAX_PENDING) throw new TemporarilyUnavailableError('Too many pending sign-ins');
    const requestId = randomBytes(24).toString('base64url');
    this.pending.set(requestId, { client, params, expiresAt: Date.now() + PENDING_TTL_MS, attempts: 0 });
    this.renderConsent(res, requestId, client, params.redirectUri, 200);
  }

  private renderConsent(res: Response, requestId: string, client: OAuthClientInformationFull, redirectUri: string, status: number, error?: string): void {
    const redirect = new URL(redirectUri);
    const name = escapeHtml(client.client_name ?? 'Unnamed client');
    const host = escapeHtml(redirect.host);
    // form-action must list the callback origin: browsers enforce it on the redirect that follows the form post.
    res
      .status(status)
      .set({
        'Content-Type': 'text/html; charset=utf-8',
        'Cache-Control': 'no-store',
        'Referrer-Policy': 'no-referrer',
        'X-Content-Type-Options': 'nosniff',
        'X-Frame-Options': 'DENY',
        'Content-Security-Policy': `default-src 'none'; style-src 'unsafe-inline'; form-action 'self' ${redirect.origin}; frame-ancestors 'none'; base-uri 'none'`,
      })
      .send(`<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>3alraf MCP sign-in</title>
<style>body{font:16px system-ui,sans-serif;background:#f6f6f7;color:#1c1c1e;margin:0;padding:16px}main{max-width:420px;margin:8vh auto;background:#fff;padding:24px;border-radius:12px;box-shadow:0 1px 4px #0002}h1{font-size:20px;margin:0 0 12px}label{display:block;margin:16px 0 4px}input[type=password]{width:100%;box-sizing:border-box;padding:10px;font-size:16px}.row{display:flex;gap:8px;margin-top:16px}button{flex:1;padding:10px;font-size:16px;cursor:pointer}.err{color:#b00020}code{word-break:break-all}</style></head>
<body><main><h1>Connect to the 3alraf store</h1>
<p><strong>${name}</strong> is asking to read store marketing data and create suggestions and drafts for review. It cannot approve, apply or publish anything.</p>
<p>After sign-in you will be sent to: <code>${host}</code></p>
${error ? `<p class="err">${escapeHtml(error)}</p>` : ''}
<form method="post" action="/oauth/consent"><input type="hidden" name="request_id" value="${requestId}">
<label for="password">Owner password</label><input id="password" name="password" type="password" autocomplete="current-password" autofocus>
<div class="row"><button name="decision" value="approve" type="submit">Allow</button><button name="decision" value="deny" type="submit">Deny</button></div></form>
</main></body></html>`);
  }

  /** POST /oauth/consent: the owner's decision on a pending authorization. */
  readonly consentHandler: RequestHandler = (req: Request, res: Response) => {
    const body = (req.body ?? {}) as Record<string, unknown>;
    const requestId = typeof body['request_id'] === 'string' ? body['request_id'] : '';
    const pending = this.pending.get(requestId);
    if (!pending || pending.expiresAt < Date.now()) {
      this.pending.delete(requestId);
      res.status(400).type('text/plain').send('This sign-in request has expired. Start again from Claude.');
      return;
    }
    const { client, params } = pending;
    const callback = new URL(params.redirectUri);
    if (params.state !== undefined) callback.searchParams.set('state', params.state);

    if (body['decision'] !== 'approve') {
      this.pending.delete(requestId);
      callback.searchParams.set('error', 'access_denied');
      res.redirect(302, callback.href);
      return;
    }

    const password = typeof body['password'] === 'string' ? body['password'] : '';
    if (!safeEqual(password, this.config.ownerPassword)) {
      pending.attempts += 1;
      this.logger.warn({ clientId: client.client_id, attempts: pending.attempts }, 'oauth consent: wrong password');
      if (pending.attempts >= MAX_PASSWORD_ATTEMPTS) {
        this.pending.delete(requestId);
        callback.searchParams.set('error', 'access_denied');
        res.redirect(302, callback.href);
        return;
      }
      this.renderConsent(res, requestId, client, params.redirectUri, 401, 'Wrong password.');
      return;
    }

    this.pending.delete(requestId);
    const code = newSecret('mcpac_');
    this.codes.set(tokenKey(code), {
      clientId: client.client_id,
      codeChallenge: params.codeChallenge,
      redirectUri: params.redirectUri,
      scopes: this.grantedScopes(params.scopes),
      resource: this.resolveResource(params.resource),
      expiresAt: Date.now() + CODE_TTL_MS,
    });
    this.logger.info({ clientId: client.client_id }, 'oauth consent granted');
    callback.searchParams.set('code', code);
    res.redirect(302, callback.href);
  };

  private liveCode(client: OAuthClientInformationFull, code: string): CodeRecord {
    const key = tokenKey(code);
    const record = this.codes.get(key);
    if (!record || record.clientId !== client.client_id) throw new InvalidGrantError('Invalid authorization code');
    if (record.expiresAt < Date.now()) {
      this.codes.delete(key);
      throw new InvalidGrantError('Authorization code expired');
    }
    return record;
  }

  async challengeForAuthorizationCode(client: OAuthClientInformationFull, authorizationCode: string): Promise<string> {
    return this.liveCode(client, authorizationCode).codeChallenge;
  }

  async exchangeAuthorizationCode(
    client: OAuthClientInformationFull,
    authorizationCode: string,
    _codeVerifier?: string,
    redirectUri?: string,
    resource?: URL,
  ): Promise<OAuthTokens> {
    const record = this.liveCode(client, authorizationCode);
    this.codes.delete(tokenKey(authorizationCode)); // single use, even if a later check fails
    if (redirectUri !== undefined && redirectUri !== record.redirectUri) throw new InvalidGrantError('redirect_uri mismatch');
    if (resource && resource.href !== record.resource) throw new InvalidTargetError('Unknown resource');
    return this.issueTokens(client.client_id, record.scopes, record.resource);
  }

  async exchangeRefreshToken(client: OAuthClientInformationFull, refreshToken: string, scopes?: string[], resource?: URL): Promise<OAuthTokens> {
    const key = tokenKey(refreshToken);
    const record = this.state.tokens[key];
    if (!record || record.kind !== 'refresh' || record.clientId !== client.client_id || record.expiresAt < Date.now()) {
      throw new InvalidGrantError('Invalid refresh token');
    }
    if (resource && resource.href !== record.resource) throw new InvalidTargetError('Unknown resource');
    delete this.state.tokens[key]; // rotation: the presented refresh token stops working now
    const narrowed = scopes?.length ? record.scopes.filter((s) => scopes.includes(s)) : record.scopes;
    return this.issueTokens(client.client_id, narrowed.length ? narrowed : record.scopes, record.resource);
  }

  private issueTokens(clientId: string, scopes: string[], resource: string): OAuthTokens {
    this.pruneTokens();
    const accessToken = newSecret('mcpat_');
    const refreshToken = newSecret('mcprt_');
    const now = Date.now();
    this.state.tokens[tokenKey(accessToken)] = { kind: 'access', clientId, scopes, resource, expiresAt: now + this.config.accessTtlSeconds * 1000 };
    this.state.tokens[tokenKey(refreshToken)] = { kind: 'refresh', clientId, scopes, resource, expiresAt: now + this.config.refreshTtlSeconds * 1000 };
    this.save();
    return {
      access_token: accessToken,
      token_type: 'Bearer',
      expires_in: this.config.accessTtlSeconds,
      refresh_token: refreshToken,
      scope: scopes.join(' '),
    };
  }

  async verifyAccessToken(token: string): Promise<AuthInfo> {
    const record = this.state.tokens[tokenKey(token)];
    if (!record || record.kind !== 'access' || record.expiresAt < Date.now()) {
      throw new InvalidTokenError('Invalid access token');
    }
    return {
      token,
      clientId: record.clientId,
      scopes: record.scopes,
      expiresAt: Math.floor(record.expiresAt / 1000),
      resource: new URL(record.resource),
    };
  }

  async revokeToken(client: OAuthClientInformationFull, request: OAuthTokenRevocationRequest): Promise<void> {
    const key = tokenKey(request.token);
    if (this.state.tokens[key]?.clientId === client.client_id) {
      delete this.state.tokens[key];
      this.save();
    }
  }

  private prunePending(): void {
    const now = Date.now();
    for (const [id, p] of this.pending) if (p.expiresAt < now) this.pending.delete(id);
    for (const [key, c] of this.codes) if (c.expiresAt < now) this.codes.delete(key);
  }

  private pruneTokens(): void {
    const now = Date.now();
    for (const [key, t] of Object.entries(this.state.tokens)) if (t.expiresAt < now) delete this.state.tokens[key];
  }

  private load(): void {
    const file = this.config.stateFile;
    if (!file || !existsSync(file)) return;
    try {
      const parsed = JSON.parse(readFileSync(file, 'utf8')) as Partial<PersistedState>;
      this.state = { clients: parsed.clients ?? {}, tokens: parsed.tokens ?? {} };
    } catch {
      this.logger.warn('oauth state file is unreadable; starting empty');
    }
  }

  private save(): void {
    const file = this.config.stateFile;
    if (!file) return;
    mkdirSync(dirname(file), { recursive: true, mode: 0o700 });
    const tmp = `${file}.${process.pid}.tmp`;
    writeFileSync(tmp, JSON.stringify(this.state), { mode: 0o600 });
    chmodSync(tmp, 0o600);
    renameSync(tmp, file);
  }
}
