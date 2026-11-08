import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { BEARER, MCP_HEADERS, SERVICE_TOKEN, initializeBody, startTestServer, type TestServer } from './helpers.js';

describe('HTTP auth and limits', () => {
  let s: TestServer;
  beforeAll(async () => {
    s = await startTestServer({ MCP_BODY_LIMIT: '2kb' });
  });
  afterAll(() => s.close());

  const post = (headers: Record<string, string>, path = '/mcp', body: unknown = initializeBody) =>
    fetch(`${s.url}${path}`, { method: 'POST', headers: { ...MCP_HEADERS, ...headers }, body: JSON.stringify(body) });

  it('rejects a request with no bearer and sends a challenge', async () => {
    const res = await post({});
    expect(res.status).toBe(401);
    expect(res.headers.get('www-authenticate')).toMatch(/^Bearer /);
  });

  it('rejects a wrong bearer', async () => {
    const res = await post({ Authorization: 'Bearer wrong-token-wrong-token-wrong-token-00' });
    expect(res.status).toBe(401);
    expect(res.headers.get('www-authenticate')).toContain('invalid_token');
  });

  it('rejects a bearer of a different length and a non-bearer scheme', async () => {
    expect((await post({ Authorization: 'Bearer x' })).status).toBe(401);
    expect((await post({ Authorization: `Basic ${BEARER}` })).status).toBe(401);
  });

  it('never accepts the token in the query string', async () => {
    const res = await post({}, `/mcp?token=${BEARER}&access_token=${BEARER}`);
    expect(res.status).toBe(401);
  });

  it('rejects the Laravel service token as an MCP credential', async () => {
    expect((await post({ Authorization: `Bearer ${SERVICE_TOKEN}` })).status).toBe(401);
  });

  it('accepts the correct bearer', async () => {
    const res = await post({ Authorization: `Bearer ${BEARER}` });
    expect(res.status).toBe(200);
    const body = (await res.json()) as { result: { serverInfo: { name: string } } };
    expect(body.result.serverInfo.name).toBe('3alraf-marketing');
  });

  it('answers 405 on GET and DELETE after auth', async () => {
    const headers = { Authorization: `Bearer ${BEARER}` };
    expect((await fetch(`${s.url}/mcp`, { headers })).status).toBe(405);
    expect((await fetch(`${s.url}/mcp`, { method: 'DELETE', headers })).status).toBe(405);
    expect((await fetch(`${s.url}/mcp`)).status).toBe(401);
  });

  it('enforces the body limit', async () => {
    const res = await post({ Authorization: `Bearer ${BEARER}` }, '/mcp', { ...initializeBody, padding: 'x'.repeat(4096) });
    expect(res.status).toBe(413);
  });

  it('rejects an unexpected Host header', async () => {
    const http = await import('node:http');
    const { hostname, port } = new URL(s.url);
    const status = await new Promise<number>((resolve, reject) => {
      http
        .request({ hostname, port, path: '/health', headers: { Host: 'evil.example' } }, (r) => {
          r.resume();
          resolve(r.statusCode ?? 0);
        })
        .on('error', reject)
        .end();
    });
    expect(status).toBe(403);
  });
});

describe('/health', () => {
  it('reports Laravel reachable from an unauthenticated probe and exposes no secrets', async () => {
    const s = await startTestServer();
    s.laravelMock.reply({ status: 401, json: { success: false, error: 'unauthenticated' } });
    const res = await fetch(`${s.url}/health`);
    const text = await res.text();
    expect(res.status).toBe(200);
    expect(JSON.parse(text)).toMatchObject({ status: 'ok', laravel: { reachable: true } });
    expect(text).not.toContain(SERVICE_TOKEN);
    expect(text).not.toContain(BEARER);
    expect(s.laravelMock.last.headers['authorization']).toBeUndefined();
    await s.close();
  });

  it('reports degraded when Laravel is down', async () => {
    const s = await startTestServer();
    s.laravelMock.reply(new TypeError('fetch failed'));
    const res = await fetch(`${s.url}/health`);
    expect(res.status).toBe(503);
    expect(await res.json()).toMatchObject({ status: 'degraded', laravel: { reachable: false } });
    await s.close();
  });
});

describe('per-client rate limit', () => {
  it('returns 429 past the limit', async () => {
    const s = await startTestServer({ MCP_RATE_LIMIT_PER_MINUTE: '2' });
    const send = () => fetch(`${s.url}/mcp`, { method: 'POST', headers: { ...MCP_HEADERS, Authorization: `Bearer ${BEARER}` }, body: JSON.stringify(initializeBody) });
    expect((await send()).status).toBe(200);
    expect((await send()).status).toBe(200);
    expect((await send()).status).toBe(429);
    await s.close();
  });
});
