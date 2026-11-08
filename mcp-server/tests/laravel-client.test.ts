import { describe, expect, it } from 'vitest';
import { LaravelClient, LaravelError } from '../src/laravel-client.js';
import { createLogger } from '../src/logger.js';
import { LogSink, MockLaravel, SERVICE_TOKEN, fail, ok } from './helpers.js';

function build(retries = 2) {
  const mock = new MockLaravel();
  const logs = new LogSink();
  const client = new LaravelClient({
    baseUrl: 'http://laravel.test',
    serviceToken: SERVICE_TOKEN,
    timeoutMs: 100,
    getRetries: retries,
    retryBaseMs: 0,
    logger: createLogger('debug', logs),
    fetchImpl: mock.fetch,
  });
  return { client, mock, logs };
}

const thrown = async (p: Promise<unknown>): Promise<LaravelError> => {
  try {
    await p;
  } catch (e) {
    return e as LaravelError;
  }
  throw new Error('expected rejection');
};

describe('LaravelClient', () => {
  it('parses the success envelope', async () => {
    const { client, mock } = build();
    mock.reply(ok({ a: 1 }, [{ code: 'w' }]));
    await expect(client.get('overview')).resolves.toMatchObject({ status: 200, data: { a: 1 }, warnings: [{ code: 'w' }] });
  });

  it('skips empty query values', async () => {
    const { client, mock } = build();
    await client.get('products', { a: undefined, b: null, c: '', d: 0 });
    expect(Object.fromEntries(mock.last.url.searchParams)).toEqual({ d: '0' });
  });

  it('refuses a non-envelope 200', async () => {
    const { client, mock } = build();
    mock.reply({ json: { foo: 'bar' } });
    expect((await thrown(client.get('x'))).code).toBe('invalid_response');
  });

  it('does not pass on an HTML error page', async () => {
    const { client, mock } = build(0);
    mock.reply({ status: 500, text: '<html>stack trace with DB_PASSWORD=hunter2</html>' });
    const err = await thrown(client.get('x'));
    expect(err.code).toBe('server_error');
    expect(JSON.stringify(err.toToolPayload())).not.toContain('hunter2');
  });

  it('retries GET on gateway errors, then succeeds', async () => {
    const { client, mock } = build();
    mock.reply({ status: 502, text: 'bad gateway' }, { status: 504, text: 'timeout' }, ok({ done: true }));
    await expect(client.get('x')).resolves.toMatchObject({ data: { done: true } });
    expect(mock.calls).toHaveLength(3);
  });

  it('retries GET on network errors a limited number of times', async () => {
    const { client, mock } = build(2);
    mock.reply(new TypeError('a'), new TypeError('b'), new TypeError('c'), ok({}));
    expect((await thrown(client.get('x'))).code).toBe('network_error');
    expect(mock.calls).toHaveLength(3);
  });

  it('retries a 429 honouring a short Retry-After', async () => {
    const { client, mock } = build();
    mock.reply({ status: 429, json: { success: false, error: 'too_many_requests', message: 'slow' }, headers: { 'retry-after': '0' } }, ok({}));
    await client.get('x');
    expect(mock.calls).toHaveLength(2);
  });

  it('does not wait out a long Retry-After', async () => {
    const { client, mock } = build();
    mock.reply({ status: 429, json: { success: false, error: 'too_many_requests', message: 'slow' }, headers: { 'retry-after': '60' } });
    expect((await thrown(client.get('x'))).code).toBe('too_many_requests');
    expect(mock.calls).toHaveLength(1);
  });

  it('never retries POST or PATCH', async () => {
    const { client, mock } = build();
    mock.reply(new TypeError('net'));
    await thrown(client.post('suggestions/seo', {}));
    expect(mock.calls).toHaveLength(1);
    mock.reply({ status: 503, text: 'unavailable' });
    await thrown(client.patch('suggestions/1', {}));
    expect(mock.calls).toHaveLength(2);
  });

  it('does not retry a deliberate envelope 503 such as Meta not_configured', async () => {
    const { client, mock } = build();
    mock.reply(fail(503, 'not_configured', 'Meta not configured'));
    expect((await thrown(client.get('meta/account'))).code).toBe('not_configured');
    expect(mock.calls).toHaveLength(1);
  });

  it('maps a timeout', async () => {
    const { client, mock } = build(0);
    mock.reply(Object.assign(new Error('timed out'), { name: 'TimeoutError' }));
    const err = await thrown(client.get('x'));
    expect(err.code).toBe('timeout');
    expect(err.status).toBeNull();
  });

  it('scrubs the token from store messages and redacts secrets in data', async () => {
    const { client, mock } = build(0);
    mock.reply(fail(422, 'validation_failed', `bad token ${SERVICE_TOKEN}`, { errors: { a: [SERVICE_TOKEN] } }));
    expect(JSON.stringify((await thrown(client.get('x'))).toToolPayload())).not.toContain(SERVICE_TOKEN);
    mock.reply(ok({ password: 'p', nested: { client_secret: 's' }, ok: 'fine' }));
    await expect(client.get('x')).resolves.toMatchObject({ data: { password: '[REDACTED]', nested: { client_secret: '[REDACTED]' }, ok: 'fine' } });
  });

  it('does not follow redirects and does not log the token or query', async () => {
    const { client, mock, logs } = build(0);
    mock.reply(new TypeError('unexpected redirect'));
    await thrown(client.get('products', { search: 'private-search-term' }));
    expect(logs.output).not.toContain(SERVICE_TOKEN);
    expect(logs.output).not.toContain('private-search-term');
  });
});
