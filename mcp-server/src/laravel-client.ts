import type { Logger } from 'pino';
import { sanitize, scrubString } from './untrusted.js';

const API_PREFIX = '/api/internal/marketing';
const MAX_RESPONSE_BYTES = 8 * 1024 * 1024;
const MAX_RETRY_AFTER_MS = 5000;

export type QueryValue = string | number | boolean | undefined | null;
export type Query = Record<string, QueryValue>;

export interface LaravelSuccess {
  status: number;
  data: unknown;
  meta: unknown;
  warnings: unknown[];
}

export interface LaravelClientOptions {
  baseUrl: string;
  serviceToken: string;
  timeoutMs: number;
  getRetries: number;
  retryBaseMs: number;
  logger: Logger;
  fetchImpl?: typeof fetch;
}

/** Hints added to Laravel's own error codes so Claude knows what to do next. */
const HINTS: Record<string, string> = {
  unauthenticated: 'The MCP server service token was rejected by the store. The owner must issue a new token in the marketer panel.',
  forbidden_scope: 'The MCP server service token lacks the scope for this action. The owner must grant it in the marketer panel.',
  too_many_requests: 'The store API rate limit was hit. Wait a minute before retrying.',
  not_found: 'No record exists with that id.',
  validation_failed: 'The store refused the input. Fix the fields listed in `errors` and retry.',
  target_not_found: 'The target entity does not exist. Look up a valid id first.',
  duplicate_suggestion: 'An open suggestion with the same content already exists (see details.existing_id). Update that one instead.',
  recently_rejected: 'The same suggestion was rejected recently. Only re-create it with a force_reason explaining what changed.',
  not_owner: 'This record was created by another token and cannot be changed here.',
  not_editable: 'The current status does not allow edits. A person must act on it in the marketer panel.',
  frozen: 'This draft is frozen. A person must act on it in the marketer panel.',
  illegal_transition: 'This status change is not allowed from the current status.',
  not_configured: 'The Meta integration is not configured on the store yet.',
  connection_failed: 'The store could not reach Meta. Try again later.',
  meta_error: 'Meta refused the request.',
  timeout: 'The store API did not answer in time. Try again, or narrow the request.',
  network_error: 'The store API could not be reached.',
  invalid_response: 'The store API returned an unexpected response.',
  response_too_large: 'The response was too large. Use filters or a smaller per_page.',
  server_error: 'The store API failed with an internal error.',
};

export class LaravelError extends Error {
  readonly hint: string | undefined;

  constructor(
    readonly code: string,
    message: string,
    readonly status: number | null,
    readonly fieldErrors?: unknown,
    readonly details?: unknown,
  ) {
    super(message);
    this.name = 'LaravelError';
    this.hint = HINTS[code];
  }

  toToolPayload(): Record<string, unknown> {
    return {
      error: this.code,
      message: this.message,
      http_status: this.status,
      ...(this.hint ? { hint: this.hint } : {}),
      ...(this.fieldErrors ? { errors: this.fieldErrors } : {}),
      ...(this.details ? { details: this.details } : {}),
    };
  }
}

interface Envelope {
  success: boolean;
  data?: unknown;
  meta?: unknown;
  warnings?: unknown;
  error?: unknown;
  message?: unknown;
  errors?: unknown;
}

function isEnvelope(v: unknown): v is Envelope {
  return typeof v === 'object' && v !== null && typeof (v as Envelope).success === 'boolean';
}

const sleep = (ms: number): Promise<void> => new Promise((resolve) => setTimeout(resolve, ms));

export class LaravelClient {
  private readonly fetchImpl: typeof fetch;
  private readonly secrets: string[];

  constructor(private readonly options: LaravelClientOptions) {
    this.fetchImpl = options.fetchImpl ?? fetch;
    this.secrets = [options.serviceToken];
  }

  get knownSecrets(): readonly string[] {
    return this.secrets;
  }

  get(path: string, query?: Query): Promise<LaravelSuccess> {
    return this.request('GET', path, query, undefined);
  }

  post(path: string, body?: unknown): Promise<LaravelSuccess> {
    return this.request('POST', path, undefined, body ?? {});
  }

  patch(path: string, body: unknown): Promise<LaravelSuccess> {
    return this.request('PATCH', path, undefined, body);
  }

  /** Unauthenticated probe: a 401 from the API proves Laravel is up without spending or exposing the token. */
  async isReachable(timeoutMs = 3000): Promise<boolean> {
    try {
      const res = await this.fetchImpl(`${this.options.baseUrl}${API_PREFIX}/overview`, {
        method: 'GET',
        headers: { Accept: 'application/json' },
        signal: AbortSignal.timeout(timeoutMs),
        redirect: 'error',
      });
      return res.status === 401 || res.ok;
    } catch {
      return false;
    }
  }

  private buildUrl(path: string, query?: Query): string {
    const url = new URL(`${this.options.baseUrl}${API_PREFIX}/${path}`);
    for (const [key, value] of Object.entries(query ?? {})) {
      if (value === undefined || value === null || value === '') continue;
      url.searchParams.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value));
    }
    return url.toString();
  }

  private async request(method: 'GET' | 'POST' | 'PATCH', path: string, query: Query | undefined, body: unknown): Promise<LaravelSuccess> {
    const url = this.buildUrl(path, query);
    const maxAttempts = method === 'GET' ? this.options.getRetries + 1 : 1;
    let lastError: LaravelError | undefined;

    for (let attempt = 1; attempt <= maxAttempts; attempt++) {
      let retryAfterMs: number | undefined;
      try {
        return await this.once(method, url, body);
      } catch (err) {
        const { error, retryable, retryAfter } = this.classify(err);
        lastError = error;
        retryAfterMs = retryAfter;
        this.options.logger.warn({ method, path, attempt, code: error.code, status: error.status }, 'laravel request failed');
        if (!retryable || attempt === maxAttempts) throw error;
      }
      await sleep(retryAfterMs ?? this.options.retryBaseMs * 2 ** (attempt - 1));
    }
    throw lastError ?? new LaravelError('network_error', 'Request failed', null);
  }

  private async once(method: string, url: string, body: unknown): Promise<LaravelSuccess> {
    const headers: Record<string, string> = {
      Accept: 'application/json',
      Authorization: `Bearer ${this.options.serviceToken}`,
    };
    const init: RequestInit = {
      method,
      headers,
      signal: AbortSignal.timeout(this.options.timeoutMs),
      redirect: 'error', // never follow a redirect with the service token
    };
    if (body !== undefined) {
      headers['Content-Type'] = 'application/json';
      init.body = JSON.stringify(body);
    }

    const res = await this.fetchImpl(url, init);
    const text = await res.text();
    if (Buffer.byteLength(text) > MAX_RESPONSE_BYTES) {
      throw new LaravelError('response_too_large', 'The store API response exceeded the size limit.', res.status);
    }

    let parsed: unknown;
    try {
      parsed = JSON.parse(text);
    } catch {
      throw this.nonEnvelopeError(res);
    }
    if (!isEnvelope(parsed)) throw this.nonEnvelopeError(res);

    if (parsed.success) {
      return {
        status: res.status,
        data: sanitize(parsed.data ?? null, this.secrets),
        meta: sanitize(parsed.meta ?? null, this.secrets),
        warnings: Array.isArray(parsed.warnings) ? (sanitize(parsed.warnings, this.secrets) as unknown[]) : [],
      };
    }

    const code = typeof parsed.error === 'string' ? parsed.error : `http_${res.status}`;
    const message = typeof parsed.message === 'string' ? scrubString(parsed.message, this.secrets) : 'The store API refused the request.';
    const err = new LaravelError(code, message, res.status, sanitize(parsed.errors ?? undefined, this.secrets), sanitize(parsed.data ?? undefined, this.secrets));
    if (res.status === 429) (err as LaravelError & { retryAfterMs?: number }).retryAfterMs = this.retryAfter(res);
    throw err;
  }

  private retryAfter(res: Response): number | undefined {
    const seconds = Number(res.headers.get('retry-after'));
    return Number.isFinite(seconds) && seconds > 0 ? seconds * 1000 : undefined;
  }

  /** A gateway page or PHP fatal: the body may be HTML, so it is never passed on. */
  private nonEnvelopeError(res: Response): LaravelError {
    if (res.status >= 500) return new LaravelError('server_error', `The store API answered HTTP ${res.status}.`, res.status);
    return new LaravelError('invalid_response', `The store API answered HTTP ${res.status} without the expected JSON envelope.`, res.status);
  }

  private classify(err: unknown): { error: LaravelError; retryable: boolean; retryAfter?: number } {
    if (err instanceof LaravelError) {
      // Envelope errors (including Meta's 502/503) are deliberate answers; only gateway failures and throttling are retried.
      if (err.status === 429) {
        const retryAfter = (err as LaravelError & { retryAfterMs?: number }).retryAfterMs;
        return retryAfter !== undefined && retryAfter > MAX_RETRY_AFTER_MS
          ? { error: err, retryable: false }
          : { error: err, retryable: true, ...(retryAfter !== undefined ? { retryAfter } : {}) };
      }
      return { error: err, retryable: err.code === 'server_error' && [502, 503, 504].includes(err.status ?? 0) };
    }
    const name = (err as Error | undefined)?.name;
    if (name === 'TimeoutError' || name === 'AbortError') {
      return { error: new LaravelError('timeout', 'The store API did not answer in time.', null), retryable: true };
    }
    return { error: new LaravelError('network_error', 'The store API could not be reached.', null), retryable: true };
  }
}
