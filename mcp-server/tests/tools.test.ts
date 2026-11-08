import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import type { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { ALL_TOOLS } from '../src/tools/index.js';
import { BEARER, connectClient, startTestServer, type TestServer } from './helpers.js';

/** Any name that implies a privileged action. `approv` also covers approval; `appl` covers apply/applied/application. */
const FORBIDDEN = /approv|reject|appl(y|ied|ication)|roll_?back|publish|activat/i;

/** Names that match FORBIDDEN but do not perform the action. Additions here need a human reason. */
const ALLOWLIST: Record<string, string> = {
  get_suggestion_application_result: 'read-only report of what a person applied',
  submit_campaign_for_approval: 'hands the draft to a person; does not approve',
};

describe('tool registry', () => {
  let s: TestServer;
  let client: Client;
  beforeAll(async () => {
    s = await startTestServer();
    client = await connectClient(s.url, BEARER);
  });
  afterAll(async () => {
    await client.close();
    await s.close();
  });

  const list = async () => (await client.listTools()).tools;

  it('registers every declared tool over MCP, each with an input schema', async () => {
    const tools = await list();
    expect(tools.map((t) => t.name).sort()).toEqual(ALL_TOOLS.map((t) => t.name).sort());
    expect(new Set(tools.map((t) => t.name)).size).toBe(tools.length);
    expect(tools.length).toBe(60);
    for (const t of tools) expect(t.inputSchema.type).toBe('object');
  });

  it('exposes no forbidden action names beyond the explicit allowlist', async () => {
    const offenders = (await list()).map((t) => t.name).filter((n) => FORBIDDEN.test(n) && !(n in ALLOWLIST));
    expect(offenders).toEqual([]);
  });

  it('forbidden-name check actually catches the tools that must not exist', () => {
    for (const bad of ['approve_suggestion', 'reject_suggestion', 'apply_suggestion', 'rollback_suggestion', 'roll_back_x', 'publish_approved_campaign_as_paused', 'activate_campaign', 'deactivate_ad']) {
      expect(FORBIDDEN.test(bad)).toBe(true);
    }
  });

  it('keeps the allowlist minimal: each entry exists and needs to be there', async () => {
    const names = (await list()).map((t) => t.name);
    for (const allowed of Object.keys(ALLOWLIST)) {
      expect(names).toContain(allowed);
      expect(FORBIDDEN.test(allowed)).toBe(true);
    }
  });

  it('marks read tools read-only and write tools non-destructive', async () => {
    for (const t of await list()) {
      const declared = ALL_TOOLS.find((d) => d.name === t.name);
      expect(t.annotations?.readOnlyHint).toBe(declared?.kind === 'read');
      expect(t.annotations?.destructiveHint).toBe(false);
    }
  });

  it('accepts no raw URL, path, SQL or table parameter', async () => {
    const banned = new Set(['url', 'path', 'sql', 'query', 'table', 'endpoint', 'method', 'headers', 'command']);
    for (const t of await list()) {
      for (const key of Object.keys(t.inputSchema.properties ?? {})) expect(banned.has(key), `${t.name}.${key}`).toBe(false);
    }
  });

  it('rejects unknown top-level arguments in every schema', async () => {
    for (const t of await list()) {
      if (Object.keys(t.inputSchema.properties ?? {}).length) {
        expect((t.inputSchema as { additionalProperties?: unknown }).additionalProperties, t.name).toBe(false);
      }
    }
  });
});
