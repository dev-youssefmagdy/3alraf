import { describe, expect, it } from 'vitest';
import { ConfigError, loadConfig } from '../src/config.js';
import { createLogger } from '../src/logger.js';
import { sanitize, safeJson, scrubString, wrapUntrusted } from '../src/untrusted.js';
import { BEARER, LogSink, OWNER_PASSWORD, SERVICE_TOKEN, testEnv } from './helpers.js';

describe('config', () => {
  it('defaults to loopback', () => {
    const c = loadConfig(testEnv());
    expect(c.host).toBe('127.0.0.1');
    expect(c.mcpUrl.pathname).toBe('/mcp');
  });

  it('refuses to start with no authentication', () => {
    expect(() => loadConfig(testEnv({ MCP_BEARER_TOKEN: '' }))).toThrow(ConfigError);
  });

  it('refuses a short bearer and never echoes secret values', () => {
    try {
      loadConfig(testEnv({ MCP_BEARER_TOKEN: 'short-secret-value', LARAVEL_SERVICE_TOKEN: 'not-a-token-value' }));
      throw new Error('should have failed');
    } catch (e) {
      const message = (e as Error).message;
      expect(message).toContain('MCP_BEARER_TOKEN');
      expect(message).not.toContain('short-secret-value');
      expect(message).not.toContain('not-a-token-value');
    }
  });

  it('requires an owner password for OAuth and https for a public host', () => {
    expect(() => loadConfig(testEnv({ MCP_OAUTH_ENABLED: 'true' }))).toThrow(/OWNER_PASSWORD/);
    expect(() => loadConfig(testEnv({ MCP_OAUTH_ENABLED: 'true', MCP_OAUTH_OWNER_PASSWORD: OWNER_PASSWORD, MCP_PUBLIC_URL: 'http://mcp.example.com' }))).toThrow(/https/);
  });
});

describe('logger', () => {
  it('redacts authorization and token fields', () => {
    const sink = new LogSink();
    const log = createLogger('info', sink);
    log.info({ authorization: `Bearer ${BEARER}`, token: SERVICE_TOKEN, headers: { authorization: BEARER }, req: { headers: { authorization: BEARER } }, nested: { access_token: 'abc-secret', password: OWNER_PASSWORD } }, 'x');
    expect(sink.output).not.toContain(BEARER);
    expect(sink.output).not.toContain(SERVICE_TOKEN);
    expect(sink.output).not.toContain('abc-secret');
    expect(sink.output).not.toContain(OWNER_PASSWORD);
    expect(sink.output).toContain('[REDACTED]');
  });
});

describe('untrusted helpers', () => {
  it('escapes angle brackets in JSON and keeps it parseable', () => {
    const v = { a: '<b>"x"</b>', ar: 'نص عربي' };
    const json = safeJson(v);
    expect(json).not.toMatch(/[<>]/);
    expect(json).toContain('نص عربي');
    expect(JSON.parse(json)).toEqual(v);
  });

  it('places server notes before the block', () => {
    const text = wrapUntrusted('get_x', { a: 1 }, ['note']);
    expect(text.indexOf('note')).toBeLessThan(text.indexOf('<untrusted_store_data'));
  });

  it('scrubs token shapes and known secrets', () => {
    expect(scrubString(`a ${SERVICE_TOKEN} b`)).toBe('a [REDACTED] b');
    expect(scrubString('Bearer abcdefghijklmnopqrstuvwxyz')).toBe('[REDACTED]');
    expect(scrubString('known-secret here', ['known-secret'])).toBe('[REDACTED] here');
  });

  it('is case-insensitive on key names and bounded on depth', () => {
    expect(sanitize({ Authorization: 'x', API_KEY: 'y' })).toEqual({ Authorization: '[REDACTED]', API_KEY: '[REDACTED]' });
    let deep: unknown = 'v';
    for (let i = 0; i < 100; i++) deep = { deep };
    expect(JSON.stringify(sanitize(deep))).toContain('TRUNCATED');
  });
});
