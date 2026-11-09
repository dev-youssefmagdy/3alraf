# MCP tools

The 60 tools exposed to Claude. The source of truth is `mcp-server/src/tools/`.

- Full tool list, auth, security and deployment: `mcp-server/README.md`.
- HTTP contract of the suggestion tools: [MCP_SUGGESTION_TOOLS.md](MCP_SUGGESTION_TOOLS.md).
- Underlying endpoints and scopes: [API_DOCUMENTATION.md](API_DOCUMENTATION.md).

| Group | Count | Scope |
|---|---:|---|
| Store and analytics reads | 26 | `marketing:read` |
| Suggestion reads | 6 | `marketing:read` |
| Suggestion creation (one per type) | 10 | `suggestions:write:{type}` (one per type) |
| Suggestion management (`update_suggestion_draft`, `submit_suggestion_for_review`) | 2 | `suggestions:write:{type}` of that suggestion |
| Media library reads | 3 | `marketing:read` |
| Meta reads and draft reads | 10 | `meta:read` |
| Meta drafts (`create_campaign_draft`, `update_campaign_draft`, `submit_campaign_for_approval`) | 3 | `meta:draft` |

## Deliberately missing
Approve, reject, apply, roll back, publish, activate. `publish_approved_campaign_as_paused` from `MCP.md` was not built on purpose: publishing is a button in `/marketer/meta-ads`. Tools that create or submit return a `review_url` for a person.

## When changing tools
- A new tool needs a strict Zod schema and a matching Laravel endpoint with a scope.
- `tests/tools.test.ts` fails if a tool name implies approve, reject, apply, roll back, publish or activate.
- Meta enums in `src/tools/schemas.ts` copy `config/meta.php`. Change both together.
