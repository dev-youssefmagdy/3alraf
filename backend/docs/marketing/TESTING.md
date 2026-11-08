# Marketing system — testing

## Run
| Part | Command | External calls |
|---|---|---|
| Laravel | `cd backend && php artisan test` (`--parallel` if available) | None. Meta uses `Http::fake` |
| MCP server | `cd mcp-server && npm test` | None. Laravel is mocked |
| n8n workflow | Editor "Run manually", or `n8n/n8n-cli.sh execute --id=<id>` with an alternate `N8N_RUNNERS_BROKER_PORT` | Calls the local Laravel |

Eight older Laravel tests were already failing before this work and are unchanged through P6 (`MARKETER_PLAN.md` status log). Compare against that baseline.

## Laravel test files (`backend/tests/Feature/`)
| File | Area |
|---|---|
| `MarketerPanelTest.php` | Panel pages and permissions |
| `MarketingApiTest.php` | Tokens, scopes, read endpoints |
| `MarketingSuggestionsApiTest.php` | Create, edit, submit; absent approve/apply routes |
| `MarketingSuggestionsEngineTest.php` | State machine, hashes, apply, rollback |
| `MetaAdsTest.php` | Drafts, approvals, PAUSED publishing (mocked) |
| `IntegrationApiTest.php` | HMAC API for n8n |
| `GiftFinderTest.php`, `GiftFinderBundlesTest.php`, `GiftExplainerTest.php` | Gift finder |
| `ProductVideoTest.php` | Product gallery videos |

## MCP test files (`mcp-server/tests/`)
`auth.test.ts`, `oauth.test.ts`, `tools.test.ts`, `tool-calls.test.ts`, `laravel-client.test.ts`, `config-logger.test.ts`.

## Manual and release checks
- Browser and customer-cycle results: `backend/docs/qa/`.
- Production acceptance list: section 14 of `implementation_steps_of_mcp.docx`.

## Not covered by automation
- A real Claude connection: needs public HTTPS (assumptions in `mcp-server/README.md`).
- Real Meta calls: every publish test is mocked.
- n8n 2.x: workflows have only run on 1.123.92.
- Successful MCP writes against a real Laravel: covered by mocks only.
