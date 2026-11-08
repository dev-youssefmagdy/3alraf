import { createHash, randomBytes } from 'node:crypto';
import { mkdtempSync, readFileSync, statSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { BEARER, MCP_HEADERS, OWNER_PASSWORD, connectClient, initializeBody, startTestServer, type TestServer } from './helpers.js';

const CALLBACK = 'https://claude.ai/api/mcp/auth_callback';

const form = (values: Record<string, string>) => ({
  method: 'POST',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  body: new URLSearchParams(values).toString(),
  redirect: 'manual' as const,
});

describe('OAuth for Claude connectors', () => {
  let s: TestServer;
  let dir: string;
  let stateFile: string;

  beforeAll(async () => {
    dir = mkdtempSync(join(tmpdir(), 'mcp-oauth-'));
    stateFile = join(dir, 'state.json');
    s = await startTestServer({ MCP_OAUTH_ENABLED: 'true', MCP_OAUTH_OWNER_PASSWORD: OWNER_PASSWORD, MCP_OAUTH_STATE_FILE: stateFile }, (port) => ({
      MCP_PUBLIC_URL: `http://127.0.0.1:${port}`,
      MCP_PORT: String(port),
    }));
  });
  afterAll(async () => {
    await s.close();
    rmSync(dir, { recursive: true, force: true });
  });

  const resource = () => `${s.url}/mcp`;

  async function register(): Promise<string> {
    const res = await fetch(`${s.url}/register`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ client_name: 'Claude', redirect_uris: [CALLBACK], token_endpoint_auth_method: 'none', grant_types: ['authorization_code', 'refresh_token'], response_types: ['code'] }),
    });
    expect(res.status).toBe(201);
    return ((await res.json()) as { client_id: string }).client_id;
  }

  const pkce = () => {
    const verifier = randomBytes(32).toString('base64url');
    return { verifier, challenge: createHash('sha256').update(verifier).digest('base64url') };
  };

  async function startAuthorize(clientId: string, challenge: string, extra: Record<string, string> = {}): Promise<{ status: number; html: string; requestId: string; headers: Headers }> {
    const params = new URLSearchParams({ client_id: clientId, redirect_uri: CALLBACK, response_type: 'code', code_challenge: challenge, code_challenge_method: 'S256', state: 'st4te', resource: resource(), ...extra });
    const res = await fetch(`${s.url}/authorize?${params}`, { redirect: 'manual' });
    const html = await res.text();
    return { status: res.status, html, requestId: /name="request_id" value="([^"]+)"/.exec(html)?.[1] ?? '', headers: res.headers };
  }

  async function approve(requestId: string): Promise<URL> {
    const res = await fetch(`${s.url}/oauth/consent`, form({ request_id: requestId, password: OWNER_PASSWORD, decision: 'approve' }));
    expect(res.status).toBe(302);
    return new URL(res.headers.get('location') as string);
  }

  const token = (values: Record<string, string>) => fetch(`${s.url}/token`, form(values));

  async function fullFlow() {
    const clientId = await register();
    const { verifier, challenge } = pkce();
    const { requestId } = await startAuthorize(clientId, challenge);
    const location = await approve(requestId);
    const res = await token({ grant_type: 'authorization_code', client_id: clientId, code: location.searchParams.get('code') as string, code_verifier: verifier, redirect_uri: CALLBACK, resource: resource() });
    expect(res.status).toBe(200);
    return { clientId, verifier, location, tokens: (await res.json()) as { access_token: string; refresh_token: string; expires_in: number; token_type: string } };
  }

  it('answers an unauthenticated call with 401 and a resource_metadata pointer', async () => {
    const res = await fetch(`${s.url}/mcp`, { method: 'POST', headers: MCP_HEADERS, body: JSON.stringify(initializeBody) });
    expect(res.status).toBe(401);
    expect(res.headers.get('www-authenticate')).toContain(`resource_metadata="${s.url}/.well-known/oauth-protected-resource/mcp"`);
  });

  it('serves protected-resource metadata whose resource equals the MCP URL', async () => {
    const meta = (await (await fetch(`${s.url}/.well-known/oauth-protected-resource/mcp`)).json()) as { resource: string; authorization_servers: string[] };
    expect(meta.resource).toBe(resource());
    expect(meta.authorization_servers[0]?.replace(/\/$/, '')).toBe(s.url);
  });

  it('advertises S256 PKCE and DCR', async () => {
    const meta = (await (await fetch(`${s.url}/.well-known/oauth-authorization-server`)).json()) as Record<string, unknown>;
    expect(meta['code_challenge_methods_supported']).toEqual(['S256']);
    expect(meta['registration_endpoint']).toBe(`${s.url}/register`);
    expect(meta['scopes_supported']).toContain('offline_access');
  });

  it('refuses to register a client with a non-allowlisted callback', async () => {
    const res = await fetch(`${s.url}/register`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ client_name: 'evil', redirect_uris: ['https://evil.example/cb'], token_endpoint_auth_method: 'none' }) });
    expect(res.status).toBe(400);
  });

  it('shows the callback host on the consent page and blocks framing', async () => {
    const clientId = await register();
    const { status, html, headers } = await startAuthorize(clientId, pkce().challenge);
    expect(status).toBe(200);
    expect(html).toContain('claude.ai');
    expect(headers.get('x-frame-options')).toBe('DENY');
    expect(headers.get('content-security-policy')).toContain("form-action 'self' https://claude.ai");
  });

  it('refuses a wrong password without issuing a code', async () => {
    const clientId = await register();
    const { requestId } = await startAuthorize(clientId, pkce().challenge);
    const res = await fetch(`${s.url}/oauth/consent`, form({ request_id: requestId, password: 'wrong-password-wrong', decision: 'approve' }));
    expect(res.status).toBe(401);
    expect(res.headers.get('location')).toBeNull();
  });

  it('redirects with access_denied on deny and keeps state', async () => {
    const clientId = await register();
    const { requestId } = await startAuthorize(clientId, pkce().challenge);
    const res = await fetch(`${s.url}/oauth/consent`, form({ request_id: requestId, decision: 'deny' }));
    const location = new URL(res.headers.get('location') as string);
    expect(location.searchParams.get('error')).toBe('access_denied');
    expect(location.searchParams.get('state')).toBe('st4te');
  });

  it('completes the flow; the access token works over MCP', async () => {
    const { tokens, location } = await fullFlow();
    expect(location.origin + location.pathname).toBe(CALLBACK);
    expect(location.searchParams.get('state')).toBe('st4te');
    expect(tokens.token_type.toLowerCase()).toBe('bearer');
    expect(tokens.expires_in).toBe(3600);
    const client = await connectClient(s.url, tokens.access_token);
    expect((await client.listTools()).tools.length).toBeGreaterThan(0);
    await client.close();
  });

  it('still accepts the static bearer alongside OAuth', async () => {
    const client = await connectClient(s.url, BEARER);
    await client.close();
  });

  it('rejects a wrong PKCE verifier', async () => {
    const clientId = await register();
    const { challenge } = pkce();
    const { requestId } = await startAuthorize(clientId, challenge);
    const location = await approve(requestId);
    const res = await token({ grant_type: 'authorization_code', client_id: clientId, code: location.searchParams.get('code') as string, code_verifier: pkce().verifier, redirect_uri: CALLBACK });
    expect(res.status).toBe(400);
  });

  it('makes codes single-use', async () => {
    const clientId = await register();
    const { verifier, challenge } = pkce();
    const { requestId } = await startAuthorize(clientId, challenge);
    const code = (await approve(requestId)).searchParams.get('code') as string;
    const values = { grant_type: 'authorization_code', client_id: clientId, code, code_verifier: verifier, redirect_uri: CALLBACK };
    expect((await token(values)).status).toBe(200);
    const replay = await token(values);
    expect(replay.status).toBe(400);
    expect(((await replay.json()) as { error: string }).error).toBe('invalid_grant');
  });

  it('refuses a token for another resource at authorize time', async () => {
    const clientId = await register();
    const res = await startAuthorize(clientId, pkce().challenge, { resource: 'https://other.example/mcp' });
    expect(res.status).toBe(302);
    expect(res.headers.get('location')).toContain('error=invalid_target');
  });

  it('rotates refresh tokens', async () => {
    const { clientId, tokens } = await fullFlow();
    const refreshed = await token({ grant_type: 'refresh_token', client_id: clientId, refresh_token: tokens.refresh_token });
    expect(refreshed.status).toBe(200);
    const next = (await refreshed.json()) as { access_token: string; refresh_token: string };
    expect(next.refresh_token).not.toBe(tokens.refresh_token);
    const replay = await token({ grant_type: 'refresh_token', client_id: clientId, refresh_token: tokens.refresh_token });
    expect(((await replay.json()) as { error: string }).error).toBe('invalid_grant');
  });

  it('does not accept a refresh token as an access token', async () => {
    const { tokens } = await fullFlow();
    const res = await fetch(`${s.url}/mcp`, { method: 'POST', headers: { ...MCP_HEADERS, Authorization: `Bearer ${tokens.refresh_token}` }, body: JSON.stringify(initializeBody) });
    expect(res.status).toBe(401);
  });

  it('stores only token hashes, in a 0600 file, and logs no secrets', async () => {
    const { tokens } = await fullFlow();
    const state = readFileSync(stateFile, 'utf8');
    expect(state).not.toContain(tokens.access_token);
    expect(state).not.toContain(tokens.refresh_token);
    expect(state).toContain(createHash('sha256').update(tokens.access_token).digest('hex'));
    expect(statSync(stateFile).mode & 0o777).toBe(0o600);
    expect(s.logs.output).not.toContain(tokens.access_token);
    expect(s.logs.output).not.toContain(OWNER_PASSWORD);
  });
});
