import { Writable } from 'node:stream';
import type { AddressInfo } from 'node:net';
import type { Server } from 'node:http';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StreamableHTTPClientTransport } from '@modelcontextprotocol/sdk/client/streamableHttp.js';
import { createApp, type BuiltApp } from '../src/app.js';
import { loadConfig, type Config } from '../src/config.js';
import { createLogger } from '../src/logger.js';

export const SERVICE_TOKEN = 'mkt_testprefix0001.abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUV';
export const BEARER = 'test-bearer-token-that-is-long-enough-000';
export const OWNER_PASSWORD = 'correct-horse-battery-staple';

export function testEnv(extra: Record<string, string> = {}): Record<string, string> {
  return {
    MCP_BEARER_TOKEN: BEARER,
    LARAVEL_BASE_URL: 'http://laravel.test',
    LARAVEL_SERVICE_TOKEN: SERVICE_TOKEN,
    LARAVEL_GET_RETRIES: '2',
    LARAVEL_RETRY_BASE_MS: '0',
    MARKETER_PANEL_URL: 'https://panel.test',
    LOG_LEVEL: 'info',
    ...extra,
  };
}

export interface RecordedCall {
  method: string;
  url: URL;
  headers: Record<string, string>;
  body: unknown;
}

export type MockReply = { status?: number; json?: unknown; text?: string; headers?: Record<string, string> } | Error;

export const ok = (data: unknown, warnings: unknown[] = []): MockReply => ({
  json: { success: true, data, meta: { currency: 'EGP', generated_at: '2026-11-08T10:00:00+02:00' }, warnings },
});

export const fail = (status: number, error: string, message: string, extra: Record<string, unknown> = {}): MockReply => ({
  status,
  json: { success: false, error, message, data: null, meta: {}, warnings: [], ...extra },
});

export class MockLaravel {
  readonly calls: RecordedCall[] = [];
  private readonly queue: MockReply[] = [];
  fallback: MockReply = ok({});

  reply(...replies: MockReply[]): this {
    this.queue.push(...replies);
    return this;
  }

  readonly fetch = (async (input: string | URL | Request, init?: RequestInit): Promise<Response> => {
    const url = new URL(String(input));
    const headers = Object.fromEntries(new Headers(init?.headers).entries());
    this.calls.push({ method: init?.method ?? 'GET', url, headers, body: init?.body ? JSON.parse(String(init.body)) : undefined });
    const reply = this.queue.shift() ?? this.fallback;
    if (reply instanceof Error) throw reply;
    return new Response(reply.text ?? JSON.stringify(reply.json), { status: reply.status ?? 200, headers: reply.headers ?? {} });
  }) as typeof fetch;

  get last(): RecordedCall {
    const call = this.calls.at(-1);
    if (!call) throw new Error('no laravel call recorded');
    return call;
  }
}

export class LogSink extends Writable {
  output = '';
  override _write(chunk: Buffer, _enc: BufferEncoding, cb: () => void): void {
    this.output += chunk.toString();
    cb();
  }
}

export interface TestServer extends BuiltApp {
  url: string;
  laravelMock: MockLaravel;
  logs: LogSink;
  config: Config;
  close: () => Promise<void>;
}

/** Binds to a loopback ephemeral port. Laravel is always the in-memory mock. */
export async function startTestServer(env: Record<string, string> = {}, publicUrlFor?: (port: number) => Record<string, string>): Promise<TestServer> {
  const laravelMock = new MockLaravel();
  const logs = new LogSink();
  let server: Server;

  if (publicUrlFor) {
    // OAuth needs its public URL (and so its port) at configuration time: reserve the port first.
    const probe = (await import('node:http')).createServer();
    await new Promise<void>((r) => probe.listen(0, '127.0.0.1', r));
    const port = (probe.address() as AddressInfo).port;
    await new Promise((r) => probe.close(r));
    const config = loadConfig(testEnv({ ...env, ...publicUrlFor(port) }));
    const built = createApp({ config, logger: createLogger('info', logs), fetchImpl: laravelMock.fetch });
    server = await new Promise<Server>((resolve) => {
      const s = built.app.listen(port, '127.0.0.1', () => resolve(s));
    });
    return finish(server, built, laravelMock, logs, config);
  }

  const config = loadConfig(testEnv(env));
  const built = createApp({ config, logger: createLogger('info', logs), fetchImpl: laravelMock.fetch });
  server = await new Promise<Server>((resolve) => {
    const s = built.app.listen(0, '127.0.0.1', () => resolve(s));
  });
  return finish(server, built, laravelMock, logs, config);
}

function finish(server: Server, built: BuiltApp, laravelMock: MockLaravel, logs: LogSink, config: Config): TestServer {
  const { port } = server.address() as AddressInfo;
  return {
    ...built,
    url: `http://127.0.0.1:${port}`,
    laravelMock,
    logs,
    config,
    close: async () => {
      server.closeAllConnections();
      await new Promise((r) => server.close(r));
    },
  };
}

export async function connectClient(baseUrl: string, token = BEARER): Promise<Client> {
  const client = new Client({ name: 'test-client', version: '1.0.0' });
  const transport = new StreamableHTTPClientTransport(new URL('/mcp', baseUrl), {
    requestInit: { headers: { Authorization: `Bearer ${token}` } },
  });
  await client.connect(transport);
  return client;
}

export function textOf(result: unknown): string {
  const content = (result as { content: { type: string; text: string }[] }).content;
  return content.map((c) => c.text).join('\n');
}

/** The JSON inside the untrusted block of a tool result. */
export function blockJson(result: unknown): Record<string, unknown> {
  const match = /<untrusted_store_data tool="[a-z_]+">\n([\s\S]*)\n<\/untrusted_store_data>/.exec(textOf(result));
  if (!match?.[1]) throw new Error('no untrusted block in result');
  return JSON.parse(match[1]) as Record<string, unknown>;
}

export const initializeBody = {
  jsonrpc: '2.0',
  id: 1,
  method: 'initialize',
  params: { protocolVersion: '2025-06-18', capabilities: {}, clientInfo: { name: 'raw', version: '1' } },
};

export const MCP_HEADERS = { 'Content-Type': 'application/json', Accept: 'application/json, text/event-stream' };
