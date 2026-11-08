import { z } from 'zod';
import type { Logger } from 'pino';
import { LaravelError, type LaravelClient, type LaravelSuccess } from '../laravel-client.js';
import { sanitize, wrapUntrusted } from '../untrusted.js';

export interface ToolContext {
  laravel: LaravelClient;
  logger: Logger;
  panelUrl: string;
}

export interface ToolOutcome {
  result: LaravelSuccess;
  /** Server-authored text, placed outside the untrusted block. */
  notes?: string[];
  /** Server-authored fields merged next to `data`. */
  extra?: Record<string, unknown>;
}

export type ToolKind = 'read' | 'write';

export interface ToolDefinition<Shape extends z.ZodRawShape = z.ZodRawShape> {
  name: string;
  title: string;
  description: string;
  kind: ToolKind;
  input: Shape;
  run: (args: z.infer<z.ZodObject<Shape>>, ctx: ToolContext) => Promise<LaravelSuccess | ToolOutcome>;
}

/** Type-erased form kept in the registry. */
export interface AnyTool {
  name: string;
  title: string;
  description: string;
  kind: ToolKind;
  input: z.ZodRawShape;
  run: (args: Record<string, unknown>, ctx: ToolContext) => Promise<LaravelSuccess | ToolOutcome>;
}

export function defineTool<Shape extends z.ZodRawShape>(def: ToolDefinition<Shape>): AnyTool {
  return def as unknown as AnyTool;
}

export interface ToolResult {
  [key: string]: unknown;
  content: { type: 'text'; text: string }[];
  isError?: boolean;
}

function outcomeOf(value: LaravelSuccess | ToolOutcome): ToolOutcome {
  return 'result' in value ? value : { result: value };
}

/** Runs a tool and renders its result; every failure becomes a safe tool error rather than a thrown exception. */
export async function executeTool(tool: AnyTool, args: Record<string, unknown>, ctx: ToolContext): Promise<ToolResult> {
  const startedAt = Date.now();
  try {
    const { result, notes, extra } = outcomeOf(await tool.run(args, ctx));
    ctx.logger.info({ tool: tool.name, outcome: 'ok', status: result.status, durationMs: Date.now() - startedAt }, 'tool call');
    const payload = { trust: 'untrusted_store_data', data: result.data, meta: result.meta, warnings: result.warnings, ...extra };
    return { content: [{ type: 'text', text: wrapUntrusted(tool.name, payload, notes) }] };
  } catch (err) {
    const known = err instanceof LaravelError;
    ctx.logger.warn(
      { tool: tool.name, outcome: 'error', code: known ? err.code : 'internal_error', status: known ? err.status : null, durationMs: Date.now() - startedAt },
      'tool call',
    );
    const payload = known
      ? { trust: 'untrusted_store_data', ...err.toToolPayload() }
      : { trust: 'untrusted_store_data', error: 'internal_error', message: 'The MCP server failed to process this tool call.' };
    const clean = sanitize(payload, ctx.laravel.knownSecrets);
    return { isError: true, content: [{ type: 'text', text: wrapUntrusted(tool.name, clean) }] };
  }
}

/** The panel URL for a record. A URL supplied by the store is used only if it points at the panel itself. */
export function reviewUrl(ctx: ToolContext, path: string, fromStore?: unknown): string {
  if (typeof fromStore === 'string' && URL.canParse(fromStore) && new URL(fromStore).origin === new URL(ctx.panelUrl).origin) {
    return fromStore;
  }
  return `${ctx.panelUrl}${path}`;
}

export function dataField(result: LaravelSuccess, field: string): unknown {
  return typeof result.data === 'object' && result.data !== null ? (result.data as Record<string, unknown>)[field] : undefined;
}
