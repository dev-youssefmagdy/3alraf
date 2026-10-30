# WhatsApp Chat (Admin Inbox) — Implementation Plan

Status legend: `[ ]` todo · `[~]` in progress · `[x]` done. Each phase is executed by one agent, in order; an agent must tick its boxes and append notes to the **Progress log** at the bottom before finishing.

## 1. Goal

A realtime WhatsApp inbox inside both admin panels (central + tenant): list conversations, read history, **receive** and **send** every supported message type, with live updates over Laravel Reverb. Each owner (central, or one tenant) sees and uses **only its own WhatsApp session** — never another owner's.

## 2. Existing foundation (do not rebuild)

| Piece | Where |
|---|---|
| Node gateway (Baileys 6.7.24), one session per owner (`central`, `tenant-<n>`), encrypted auth store | `whatsapp-service/src/` |
| Node → Laravel HMAC callback (`status`, `qr` events only) | `whatsapp-service/src/events.js` → Laravel callback endpoint |
| Laravel → Node client (`sendText`, connect, status) | `backend/app/Services/Whatsapp/{NodeClient,WhatsappService}.php` |
| Session model, connection page, `SessionUpdated` broadcast | `backend/app/Models/WhatsappSession.php`, `Http/Controllers/Whatsapp/ConnectionController.php`, `app/Events/Whatsapp/` |
| Reverb channels `whatsapp.central`, `whatsapp.tenant.{id}` + permission `whatsapp.view` | `backend/routes/channels.php` |
| Tenancy: one shared DB, `tenant_id` column + global scope; tenant roles `t{id}:name` | see `backend/CLAUDE.md` / `AGENTS.md` |

Currently: sending text works; **receiving, storage, chat UI and non-text types do not exist.**

## 3. Scope

### Message types — receive AND send
text · image · video · audio / voice note (PTT) · document (any file) · sticker · location · live-location (receive) · contact card (vCard) · reply/quote · reaction · link preview (text with URL) · poll (receive; send simple poll) · message edit / delete-for-everyone (receive + send) · system/unsupported types stored as a placeholder (`type=unsupported`, never crash).

### Chat features
Conversation list (search, unread badge, last message, pinned/archived), message thread with infinite scroll, delivery state (pending → sent → delivered → read → failed), typing / online presence (receive), mark-as-read (sends read receipt), media preview/download, emoji, drag-drop + paste upload, voice-note recording in browser, per-conversation assignment to an admin (optional, phase 4), link a conversation to an existing Customer/Order by phone (read-only link, phase 4).

### Out of scope (explicit)
Group chat creation/admin, broadcast lists, status/stories, calls, bulk/marketing sends (existing throttled sender stays as is), multi-device history sync (`syncFullHistory` stays false), end-to-end of anything beyond what Baileys exposes.

## 4. Architecture

```
Phone ⇄ WhatsApp ⇄ Baileys (Node, per session)
                     │  HMAC POST (events: message.in, message.status, message.update, presence, media.ready)
                     ▼
              Laravel ingest endpoint ──► DB (conversations, messages, media on private disk)
                     │                          │
                     └─► broadcast (Reverb, private channel per owner) ──► Echo in admin UI
Admin UI ──► Laravel chat API ──► NodeClient ──► Node `POST /sessions/:id/messages` (typed payloads) ──► WhatsApp
```

Decisions:
1. **Node stays stateless about history.** It forwards events and serves media; Laravel is the system of record.
2. **Media**: Node downloads inbound media (size cap) and uploads it to Laravel in the ingest request (multipart) — or Laravel pulls it from a one-time Node endpoint; the agent picks the simpler of the two and documents it. Stored on a **private** disk under `whatsapp-media/{owner}/{yyyy}/{mm}/`, served only through an authorized controller (signed/permissioned, correct `Content-Type`, `nosniff`, `Content-Disposition`). Outbound uploads validated by real MIME sniffing, size caps per type (image 16 MB, video 64 MB, audio 16 MB, document 100 MB — configurable in `AuthSettings`/config).
3. **Idempotency**: `(owner_key, wa_message_id)` unique; ingest is safe to replay; Node retries never duplicate.
4. **Ordering/realtime**: broadcast after commit (`ShouldBroadcast` + `afterCommit`), payload carries ids + minimal render data (no media bytes). Channels reuse `whatsapp.central` / `whatsapp.tenant.{id}` pattern but get a separate chat channel `whatsapp-chat.*` gated by new permissions.
5. **Isolation**: every query scoped by owner (`tenant_id` null = central) via global scope + explicit `owner_key`; Node session id is always derived server-side from the authenticated owner, never from request input.
6. **Privacy**: message bodies never written to application logs/activity log; media filenames sanitized; retention command (configurable days) to purge old messages/media.
7. **Failure handling**: send is queued (`SendWhatsappMessage` job, per-owner serialized), message row created as `pending`, updated to `sent/failed`; UI shows retry on failed.

## 5. Data model (migrations in `backend/database/migrations`)

- `whatsapp_conversations`: id, `tenant_id` (nullable, central), `owner_key` (generated: `COALESCE(tenant_id,0)`), `jid` (remote, normalized), `phone` (E.164 digits, nullable for non-phone ids), `name`, `push_name`, `avatar_path` (nullable), `last_message_id`, `last_message_at`, `last_message_preview`, `unread_count`, `is_archived`, `is_pinned`, `assigned_admin_id`/`assigned_guard` (nullable), `customer_id` (nullable), timestamps. Unique `(owner_key, jid)`. Index `(owner_key, last_message_at)`.
- `whatsapp_messages`: id, conversation_id, `tenant_id`, `owner_key`, `wa_message_id`, `direction` (in/out), `type` (enum above), `body` (text, nullable), `caption`, `payload` (json: location lat/lng/name, contact vcard, poll options, reaction emoji, etc.), `reply_to_id` (nullable self FK), `media_id` (nullable), `status` (pending/sent/delivered/read/failed), `error` (nullable), `sent_by_admin_id`/`sent_by_guard` (nullable), `wa_timestamp`, `edited_at`, `deleted_at` (revoked), timestamps. Unique `(owner_key, wa_message_id)`; index `(conversation_id, wa_timestamp, id)`.
- `whatsapp_media`: id, `tenant_id`, `owner_key`, `disk`, `path`, `mime`, `size`, `original_name`, `sha256`, `width`, `height`, `duration`, `thumbnail_path`, `status` (pending/ready/failed/expired).
- `whatsapp_reactions`: message_id, `reactor_jid`, emoji (unique per message+reactor).
- Permissions (seeded for central + tenant guards, system roles auto-granted, same pattern as `2026_11_29_100002_seed_whatsapp_permissions.php`): `whatsapp.chat.view`, `whatsapp.chat.send`, `whatsapp.chat.manage` (archive/assign/delete local), `whatsapp.chat.media`.

## 6. Phases

### Phase 1 — Node gateway: receive + all outbound types  `[x]`
Files: `whatsapp-service/src/{session-manager,server,events,media}.js`, tests in `whatsapp-service/test/`.
- [x] Listen to `messages.upsert` (notify), `messages.update` (status/edit/delete), `message-receipt.update`, `messages.reaction`, `presence.update`, `contacts.upsert/update`; normalize each to a stable JSON envelope (type, jid, participant, id, timestamp, fromMe, body/caption, payload, quoted id) and emit through the existing HMAC callback (`events.js`; extend batching/retry, keep body out of logs).
- [x] Inbound media: `downloadMediaMessage` with size cap + timeout; hand off per decision 2 (§4). Thumbnails for image/video/sticker.
- [x] Extend `POST /sessions/:id/messages` to a typed payload: `text|image|video|audio|ptt|document|sticker|location|contact|poll|reaction|reply|edit|delete`, plus `POST /sessions/:id/read`, `POST /sessions/:id/presence`. Keep the **current text payload backwards compatible** (OTP path must not change or slow down: still instant, no `onWhatsApp` extra round-trips beyond today's).
- [x] Validate everything (jid/phone, mime, sizes, URL fetch only from Laravel-signed upload — no arbitrary remote URLs/SSRF), per-session send chain preserved.
- [x] Unit tests with a fake socket (`node --test`): each inbound type normalizes; each outbound type builds the right Baileys content; malformed input rejected.
**Done when:** `npm test` green; manual: connected central session receives a message and the callback receives the envelope.

### Phase 2 — Laravel: schema, ingest, domain models, broadcasting  `[x]`
Files: migrations, `app/Models/Whatsapp*`, `app/Services/Whatsapp/Chat/*`, `app/Http/Controllers/Whatsapp/IngestController` (or extend existing callback), `app/Events/Whatsapp/Chat*.php`, `routes/channels.php`, permissions migration.
- [x] Migrations + models (+ tenant global scope / owner scope trait, factories for tests).
- [x] Extend the existing HMAC callback to handle the new event types; verify signature + timestamp window exactly as today; resolve owner from session id (never from payload tenant fields).
- [x] `ChatIngestService`: upsert conversation, create message idempotently, attach media, update `last_message_*`/`unread_count`, apply status/receipt/reaction/edit/delete updates, handle unknown types as `unsupported`.
- [x] Broadcast events after commit: `ChatMessageReceived`, `ChatMessageUpdated` (status/edit/delete/reaction), `ChatConversationUpdated`, `ChatPresence` on private channels `whatsapp-chat.central` / `whatsapp-chat.tenant.{id}`; channel auth in `routes/channels.php` using the new permissions + tenant match.
- [x] Permissions migration + seeding + lang keys (ar/en).
- [x] Retention command `whatsapp:chat-prune` (config days) + scheduler entry.
**Done when:** Pest tests: ingest idempotency, cross-tenant isolation, channel authorization, each event type persisted; `php artisan migrate` clean.

### Phase 3 — Laravel: chat API, sending pipeline, media serving  `[x]`
Files: `app/Http/Controllers/Whatsapp/Chat*Controller.php`, `app/Http/Requests/Whatsapp/*`, `app/Jobs/SendWhatsappMessage.php`, `NodeClient` additions, routes in `central.php` + `tenant_admin.php`.
- [x] Endpoints (JSON, both panels, permission-gated, throttled): list conversations (cursor pagination, search, filters), get messages (cursor, both directions), send (all types, multipart for media), mark read, archive/pin, assign, delete-local, resend failed, start new conversation by phone (country + number via `AdminPhone`), media stream/download, upload-limits endpoint.
- [x] `ChatSendService` + queued job: create `pending` row immediately → broadcast → send via `NodeClient` typed call → update `sent/failed` → broadcast. Per-owner job serialization; no OTP-path interference (OTP keeps using `WhatsappService::sendText` synchronously).
- [x] Media: private disk storage, MIME sniffing, size/type limits, sanitized names, authorized streaming route with correct headers (see §4.2); virus-scan hook point (interface, no-op default).
- [x] Never log bodies; audit log only structural events (sent/deleted/assigned), no content.
**Done when:** Pest feature tests for every endpoint (authz per guard, tenant isolation, validation, each send type with `Http::fake`), plus job tests.

### Phase 4 — Admin UI (both panels) with Reverb realtime  `[x]`
Files: `resources/views/whatsapp/chat/*.blade.php`, `resources/js/whatsapp-chat/*`, Vite entry, nav item, lang keys. Follow the existing admin layout, components, RTL/Arabic, and Echo setup used by `connection.blade.php`.
- [x] Layout: left conversation list, right thread, mobile single-pane; RTL-correct.
- [x] Realtime via Echo on `whatsapp-chat.*`: new message, status ticks, edits/deletes/reactions, unread counters, typing indicator, optimistic send with rollback/retry; reconnect handling and gap-fill on reconnect (refetch since last id).
- [x] Renderers for every message type (image/video lightbox, audio player, voice-note waveform-lite, document card, sticker, map link for location, contact card, poll results, reply quote, reactions, deleted/edited markers, link preview).
- [x] Composer: text (Enter to send), emoji, attach (image/video/document), drag-drop + paste, voice-note recorder (MediaRecorder → ogg/opus where supported), location share, contact share, reply, react, delete-for-everyone, resend failed.
- [x] Conversation actions: search, archive, pin, mark unread, assign, new chat by phone; link to customer/orders by phone (read-only badge → customer page).
- [x] Gates: UI hides actions without permission; "WhatsApp not connected" empty state linking to the connection page.
- [x] XSS-safe rendering of all remote content (text only, escape + linkify safely; media via authorized URLs).
**Done when:** manual run in browser on central + a tenant host with two sessions proves isolation and realtime; Pest view/route smoke tests pass; no console errors.

### Phase 5 — Hardening, tests, docs  `[x]`
- [x] Security review pass (authz on every route/channel/media URL, SSRF, upload abuse, HMAC replay, log hygiene, tenant isolation) — fix findings.
- [x] Performance: indexes verified with `EXPLAIN`, N+1 check, pagination limits, broadcast payload size.
- [x] Full suite: `php artisan test` (note pre-existing unrelated failure `HardeningTest … Arabic store unavailable`), `npm test` in `whatsapp-service`, `npm run build`.
- [x] Docs: update `WHATSAPP_INTEGRATION.md` (chat section), add `docs/whatsapp-chat/OPERATIONS.md` (env vars, queue worker + Reverb + Node run commands, retention, troubleshooting), and tick this plan.
**Done when:** all green, docs written, plan fully ticked.

## 7. Cross-cutting rules for every agent
- Read `backend/CLAUDE.md` / `AGENTS.md` first; match surrounding code style, comment density, naming (Arabic + English lang files for every user-facing string).
- Never break the OTP path (`WhatsappService::sendText`, tests in `WhatsappAuthTest`).
- Never commit; never run destructive DB commands (`migrate:fresh`, truncates) on the dev database — use the testing DB (`php artisan test`).
- Do not touch unrelated modules. Prefer extending existing classes over new parallel ones.
- Finish by: tests for your phase green, plan checkboxes ticked, Progress log entry added (what was built, files, decisions, open issues for the next phase).

## 8. Progress log
_(agents append here)_

### Phase 1 — done (Node gateway)
**Files:** `whatsapp-service/src/{normalize,inbound,outbound,media}.js` (new); `events.js`, `server.js`, `session-manager.js`, `config.js` (edited); `test/chat.test.js` (new). `npm test`: 16/16 green. Not verified against a live phone (no real session in this env).

**Callback envelope (unchanged, HMAC `ts.body`, headers X-WA-Timestamp/X-WA-Signature):** `{"session_id":"central|tenant-N","type":"<event>","data":{...},"at":"ISO"}`.
NOTE: existing `WhatsappEventController` currently passes only `data` to `applyEvent` regardless of `type` and requires `data` to be an array/object; Phase 2 must branch on `type`. `status`/`qr` events are unchanged (`data` = session snapshot). Events are now delivered in order per session, 4 attempts, 4xx (except 408/429) not retried; re-signed per attempt. Replays/dupes possible, so dedupe on `(owner, wa id)`.

Events (`type` -> `data`). Common fields: `id` (WA message id), `jid` (chat jid; `...@s.whatsapp.net` or `...@lid`), `jid_alt` (or null), `phone` (digits or null for lid), `from_me` bool, `timestamp` (unix s), `push_name`. Groups/broadcast/status/newsletter are dropped in Node.
- `message.in` (also fromMe messages typed on the phone; only `notify` upserts): `{id,jid,jid_alt,phone,from_me,timestamp,push_name,type,body,caption,payload,quoted,media}`
  - `type`: text|image|video|audio|ptt|document|sticker|location|live_location|contact|poll|unsupported
  - `quoted`: `{id,participant}|null`; `media`: `{mime,size,file_name,width,height,duration,animated,thumbnail_b64}|null` (thumbnail = WA inline jpeg, base64, may be null)
  - `payload`: text link preview `{link:{url,title,description,thumbnail_b64}}`; location/live_location `{lat,lng,name,address}` (live: lat,lng only); contact `{contacts:[{name,vcard}]}`; poll `{options:[..],selectable_count}` (question in `body`); unsupported `{wa_type}`
- `media.ready` (after message.in, async): `{message_id,jid,from_me,status:"ready",token,mime,size,sha256}` or `{message_id,jid,from_me,status:"failed",reason:"too_large|download_timeout|download_failed"}`
- `message.reaction`: `{id,jid,from_me,timestamp,target_id,target_from_me,emoji}` (emoji `""` = removed; `from_me` = who reacted)
- `message.edit`: `{...common,target_id,target_from_me,body,edited_at}`; `message.delete` (revoke): `{...common,target_id,target_from_me}`
- `message.status`: `{id,jid,from_me:true,status:"pending|sent|delivered|read|failed"}` (only for our outgoing messages; `id` = wa_message_id)
- `presence`: `{jid,participant,state:"composing|recording|available|unavailable|paused",last_seen}`
- `contact.update`: `{contacts:[{jid,name,notify,verified_name}]}` (batches of 100)

**Typed send:** `POST /sessions/:id/messages` with a `type` field -> `{message_id,to_jid,timestamp}`. Without `type` the legacy `{to,text,humanize,min_gap_ms,max_gap_ms}` path is byte-for-byte the old code (onWhatsApp + same responses). Schema: `{to, type, text?, caption?, media?:{upload_id,file_name?,gif?}, location?:{lat,lng,name?,address?}, contacts?:[{name,phone|vcard}], poll?:{name,options[2..12],selectable_count?}, reaction?:{message_id,from_me?,participant?,emoji}, reply_to?:{id,from_me?,participant?,body?}, edit?:{message_id,text}, delete?:{message_id,from_me?}, humanize?}`; `type` in text|image|video|audio|ptt|document|sticker|location|contact|poll|reaction|reply|edit|delete. `to` = phone digits (onWhatsApp lookup) or a full jid (used as-is, no lookup; use for existing conversations). `reply_to` is accepted on any type; `type:"reply"` = text reply (or media reply with `media` + `media_type`). Errors: 422 invalid, 409 not connected, 404 upload missing/expired, 413 too large. Caps (bytes): image 16M, video 64M, audio/ptt 16M, sticker 2M, document 100M (config.caps). Node checks only mime family; real MIME sniffing stays in Laravel.
Other endpoints: `POST /sessions/:id/read {jid,keys:[{id,participant?}]}`; `POST /sessions/:id/presence {jid,state}`.

**Media hand-off (decision 4.2): Laravel PULLS, Node never fetches URLs.** Inbound: Node downloads (cap 100MB, 60s) into a private 0700 spool (`WA_SPOOL_DIR`, default tmp), emits `media.ready{token}`; Laravel does `GET /sessions/:id/media/:token` (bearer; body = bytes, headers content-type + `x-content-sha256`), stores it, then `DELETE` the same path. Token is session-bound, 32-hex, TTL 15 min (`WA_MEDIA_TTL_MS`), in-memory index (lost on Node restart -> Laravel should treat a missing token as expired). Outbound: Laravel PUSHES raw bytes `POST /sessions/:id/uploads?name=<file>` with `Content-Type: <mime>` -> 201 `{upload_id,size,sha256,mime}`, then references `media.upload_id` in the typed send; entry is deleted after a successful send (else TTL).

**Open issues / for Phase 2-3:** (1) controller needs `type` routing + must tolerate `data` for non-status events; (2) `@lid` chats: `phone` may be null, key conversations by `jid`, merge via `jid_alt`; (3) poll votes, live-location updates and view-once media are not surfaced (view-once is unwrapped and treated as normal media); (4) no history sync, so a Node outage loses inbound messages sent in the meantime beyond WA's own offline delivery; (5) `message.status` `failed` is rare; Laravel sets failed from send errors; (6) `NodeClient` needs `sendTyped/upload/pullMedia/deleteMedia/markRead/presence` methods (Phase 3); (7) live phone test not done.

### Phase 2 — done (Laravel schema, ingest, broadcasting)
**Tests:** `tests/Feature/WhatsappChatIngestTest.php` (16) + WhatsappAuth + role/permission/access filters green (60). Pint run.
**Migrations (applied with `php artisan migrate`):** `2026_12_01_100001_create_whatsapp_chat_tables` (conversations, media, messages, reactions; `owner_key` is a plain column set by the models, not generated, because MySQL forbids a stored generated column over a CASCADE-FK column) and `2026_12_01_100002_seed_whatsapp_chat_permissions` (re-runs PermissionSeeder). NOTE: the dev DB had empty, migration-less leftover tables `whatsapp_conversations/messages/message_attachments` (an old prototype); the migration drops those five names only if they hold zero rows, else throws. (`whatsapp_media`/`whatsapp_reactions` may also have been dropped by me while recovering; all were empty/orphaned.)
**Models:** `WhatsappConversation`, `WhatsappMessage` (`TYPES`, `STATUS_RANK`, `toChat()`), `WhatsappMedia`, `WhatsappReaction` (no tenant column; reach via message); trait `Concerns/BelongsToWhatsappOwner` (BelongsToTenant + owner_key on save + `scopeOwnedBy($tenantId)` which drops global scopes and filters `owner_key`). **Always query with `->ownedBy($tenantId)`** (central host has no tenant scope). Revoked message = `deleted_at` set (plain column, tombstone, body/caption/payload nulled). Factories for conversation/message/media.
**Service API:** `App\Services\Whatsapp\Chat\ChatIngestService::handle(?int $tenantId, string $type, array $data)` (also public `messageIn`, `mediaReady`). `WhatsappService::ownerOf($sessionId): ?['tenant'=>?int]` (new; `applyEvent` uses it). `WhatsappEventController` branches on `type`: `status`/`qr`/missing -> `applyEvent` (unchanged OTP-safe path); everything else -> ingest with owner from session id; unknown types return 200 and are ignored. `NodeClient::pullMedia($sid,$token): ?array` (null on 404 = expired) and `deleteMedia`. Job `PullWhatsappMedia(tenantId, mediaId, token)` (3 tries): GET, sha256 check, finfo MIME sniff, store at `whatsapp-media/{central|tenant-N}/Y/m/<random>.<ext>` on disk `config('whatsapp_chat.disk')` (default `private`), then DELETE; statuses ready/failed/expired. Thumbnails (inline WA jpeg) stored at message.in as `thumbnail_path`. `config/whatsapp_chat.php`: `disk`, `retention_days` (env WHATSAPP_CHAT_RETENTION_DAYS, 365; 0 = off), `max_media_bytes`. Command `whatsapp:chat-prune {--days=} {--dry-run}` scheduled daily 04:20.
**Behavior:** idempotent on `(owner_key, wa_message_id)`; conversations keyed `(owner_key, jid)`, `jid_alt` adopts an existing conversation; groups/non-user jids rejected; reply_to resolved by quoted id within the conversation; status only moves forward and only for `out` rows, `failed` only from `pending`; edits write `body` (text) or `caption` (media); `last_message_*` only moves forward; unread +1 for non-fromMe only; reactor `me` = our account; contact.update only renames existing conversations.
**Broadcasts** (private channels `whatsapp-chat.central` / `whatsapp-chat.tenant.{id}`, `ShouldBroadcast` + after-commit; base `Events/Whatsapp/ChatBroadcast`): `chat.message.received` `{message, conversation}`; `chat.message.updated` `{message}` (status/edit/delete/reaction/media); `chat.conversation.updated` `{conversation}`; `chat.presence` `{jid,participant,state,last_seen}`. `message` = `WhatsappMessage::toChat()` (id, conversation_id, wa_message_id, direction, type, status, error, body, caption, payload, reply_to_id, wa_timestamp, edited_at, deleted_at, media{id,mime,size,width,height,duration,status,name}, reactions[{reactor_jid,emoji}]); `conversation` = `toChat()` (id,jid,phone,name,last_message_*,unread_count,is_archived,is_pinned). Classes: ChatMessageReceived, ChatMessageUpdated, ChatConversationUpdated, ChatPresence. Channel auth in `routes/channels.php`: active admin on the right guard with `whatsapp.chat.view`; tenant id must match.
**Permissions:** config module `whatsapp.chat` -> `whatsapp.chat.view|send|manage|media` for both guards (auto-granted to super-admin / tenant-owner). Lang: `whatsapp.chat.{types,status,deleted,edited}` en+ar, `access.actions.manage|media`.
**Open issues for Phase 3:** (1) outgoing echo race: Phase 3 creates the `pending` row with null `wa_message_id`, sets it from the Node send response; if Node's fromMe `message.in` echo lands first it inserts a duplicate -> reconcile (match pending out row by conversation + type + body/time, or lock) in `messageIn`. (2) Module label for `whatsapp.chat` has no `access.modules` entry (dotted key); add a label in the roles UI if it groups by module. (3) media is pulled into memory (cap 100MB); stream to a temp file if memory is a concern. (4) Media serve controller must use `WhatsappMedia::ownedBy()`; no route exists yet. (5) `unread_count` reset / mark-read not implemented (Phase 3 endpoint). (6) Poll votes/live-location updates not ingested (Node does not emit). (7) Queue worker must run for broadcasts and media pulls (tests use sync).

### Phase 3 — done (chat API, sending pipeline, media serving)
**Tests:** `tests/Feature/WhatsappChatApiTest.php` (24, green) + WhatsappAuth/ingest/role/access filters (84 green). Pint run (it also restyled `ConnectionController` and `AppServiceProvider`, no logic change).
**Files:** `Http/Controllers/Whatsapp/{ChatConversationController,ChatMessageController,ChatMediaController}.php`, `Concerns/ResolvesChatOwner.php`, `Http/Requests/Whatsapp/SendChatMessageRequest.php`, `Services/Whatsapp/Chat/{ChatSendService,MediaScanner,NullMediaScanner}.php` (scanner bound in AppServiceProvider), `Jobs/SendWhatsappMessage.php`, `NodeClient` (+`sendTyped/upload/markRead/presence`), `ChatIngestService::adoptPending`, `config/whatsapp_chat.php` (+`limits`,`mimes`,`inline_mimes`,`max_text`), routes in `central.php` + `tenant_admin.php`, lang `whatsapp.chat.{errors,fields}` en/ar, `access.modules.whatsapp|whatsapp_chat` (roles form now looks up the module label with `.` replaced by `_`).
**Decisions:** owner always from route-name prefix (`central.` => null, else `current_tenant()->id`); session id = `WhatsappSession::idFor($owner)`, request `session_id`/`tenant_id` ignored. Send = pending row (wa id null) + `ChatMessageReceived` broadcast + queued `SendWhatsappMessage` (WithoutOverlapping per owner, no retry on Node errors, row -> `failed` with Node error text in `error`, never the body). `to` is the phone digits for the first message of a number-started chat (Node does the lookup), then the conversation jid; Node's `to_jid` replaces a provisional jid (merges into an existing conversation if one exists). **Echo race fixed both ways:** `messageIn` (from_me) adopts the oldest pending id-less out row of the same conversation/type/body/caption created in the last 5 min (sets wa id, status sent, broadcasts `chat.message.updated`, no new row); if instead the echo created its own row first, `ChatSendService::finalize` merges it into the pending row (re-points last_message/replies/reactions, deletes the dup). Status never regresses. React/edit/delete-for-everyone are synchronous Node calls (no queue). Mark-read resets `unread_count`, broadcasts `chat.conversation.updated`, then tells Node `/read` with the latest incoming wa ids (Node failure is non-fatal, `node_synced:false`). Message order/cursors use message `id` (arrival order). Audit log `whatsapp-chat` records ids/types only.
**Common:** all URLs below are under `/admin/whatsapp/chat/` on the central host (route prefix `central.whatsapp.chat.`) and on the tenant host (`tenant.whatsapp.chat.`). JSON; send `Accept: application/json` + CSRF (`X-CSRF-TOKEN`) like other admin AJAX. Errors: `{"success":false,"code":"...","message":"..."}`; validation 422 = Laravel `{message,errors}`; 401 unauth, 403 missing permission, 404 other owner's/unknown id, 429 throttle. Permissions: view=`whatsapp.chat.view`, send, manage, media (`.media` also needs view). Conversation JSON = `{id,jid,phone,name,last_message_id,last_message_at,last_message_preview,unread_count,is_archived,is_pinned,assigned_admin_id,assigned_guard,customer_id}`. Message JSON = Phase 2 `toChat()` + `sent_by_admin_id,sent_by_guard,reply_to:{id,direction,type,text}|null`; media bytes are at `GET media/{media.id}`.
**Routes (name suffix after `whatsapp.chat.`):**
- `limits` GET `limits` (view, 120/m) -> `{data:{limits:{image,video,audio,ptt,sticker,document bytes},mimes:{type:[mime]},max_text,connected}}`
- `conversations.index` GET `conversations?q=&archived=0|1&unread=1&pinned=1&mine=1&limit=1-100&cursor=` (view) -> `{data:[conv],next_cursor,has_more,unread_total}`; sorted pinned, last_message_at desc; conversations with no message are hidden; archived hidden unless `archived=1`.
- `conversations.store` POST `conversations` `{country:"EG",phone:"0100..."}` (send, 30/m) -> 201 new / 200 existing `{data:conv}`; 422 `invalid_phone`. Nothing is sent.
- `conversations.show` GET `conversations/{id}` (view) -> `{data:conv}`
- `conversations.update` PATCH `conversations/{id}` `{is_archived?,is_pinned?}` (manage) -> `{data:conv}` (+ broadcast)
- `conversations.assign` PUT `conversations/{id}/assign` `{admin_id:int|null}` (manage) -> `{data:conv}`; 422 `invalid_assignee` (other tenant/inactive/no chat.view).
- `conversations.destroy` DELETE `conversations/{id}` (manage) -> `{success:true,message}`; local only, deletes media files.
- `conversations.read` POST `conversations/{id}/read` (view) -> `{data:conv,node_synced:bool}`
- `conversations.presence` POST `conversations/{id}/presence` `{state:composing|recording|paused|available}` (send, 60/m) -> `{success:true}`; 409 `not_connected`.
- `messages.index` GET `conversations/{conversation}/messages?limit=1-100(40)&before=<id>&after=<id>` (view) -> `{data:[message oldest->newest],has_more_before,has_more_after}`. No cursor = newest page; `before` = older page (infinite scroll); `after` = gap-fill on reconnect.
- `messages.store` POST `conversations/{conversation}/messages` (send, 60/m) -> 202 `{data:message(status pending)}`; JSON or multipart. Body: `type` (text|image|video|audio|ptt|document|sticker|location|contact|poll) + `text` (text) | `file`+`caption?` (media; multipart) | `location:{lat,lng,name?,address?}` | `contacts:[{name,phone}]` (1-10) | `poll:{name,options[2-12],selectable_count?}`; `reply_to_id?` (message id in same conversation). 409 `not_connected`, 422 validation (file type is sniffed server-side, size cap per type from `limits`). The final status arrives via `chat.message.updated` (sent/failed/...).
- `messages.resend` POST `messages/{id}/resend` (send, 30/m) -> 202 `{data:message}`; 422 `not_resendable` (only own failed), 409 `not_connected`.
- `messages.react` POST `messages/{id}/react` `{emoji:""|"👍"}` (send) -> `{data:message}`; 422 `not_sent_yet`; 502/409 `wa_error`.
- `messages.update` PUT `messages/{id}` `{text}` (send, 30/m) edit own text msg within 15 min -> `{data:message}`; 422 `not_editable`; 502 `wa_error`.
- `messages.destroy` DELETE `messages/{id}` (send, 30/m) delete for everyone, own sent msgs only -> `{data:message}` (tombstone: body/caption/payload null, deleted_at set); 422 `not_deletable`; 502 `wa_error`.
- `media.show` GET `media/{id}?thumb=1&download=1` (view+media, 240/m) streams from the private disk: sniffed Content-Type, `X-Content-Type-Options: nosniff`, CSP sandbox, inline only for safe types (image/video/audio/pdf) else attachment, `Accept-Ranges`, single Range -> 206 / 416. `thumb=1` serves the inline WA thumbnail (works while media is still pending). 404 if not ready / missing / other owner.
Broadcast contract unchanged from Phase 2 (a send emits `chat.message.received` for the pending row, then `chat.message.updated` for status changes; the UI should match the optimistic row by the `id` returned from `messages.store`, not by wa id).
**Open issues for Phase 4/5:** (1) the Phase 4 UI needs a page route (`whatsapp.chat.index` view) + nav item; none exists yet. (2) `toChat()` issues one reactions query per message (N+1 on the thread, <=100 rows) - Phase 5 perf. (3) Echo adoption is heuristic (same text within 5 min): a phone-typed identical text while an admin message is pending could swap ids; harmless but noted. (4) outgoing media thumbnails/dimensions are not generated (UI should render from `media.mime` via `media.show`). (5) Reactions/edits/deletes are not queued, so they block the request up to Node's per-session chain wait (timeout 120s). (6) Queue worker required for sends. (7) No per-conversation send lock beyond the per-owner job mutex.

### Phase 4 — done (admin chat UI, both panels)
**Open the page:** `/admin/whatsapp/chat` (central: `central.whatsapp.chat.index`; tenant host: `tenant.whatsapp.chat.index`), sidebar item "WhatsApp Inbox" gated by `whatsapp.chat.view` (central: top-level; tenant: Sales group). Needs queue worker + Reverb (without a Reverb key the UI falls back to 15s polling gap-fill).
**Files:** `Http/Controllers/Whatsapp/ChatPageController.php`, `resources/views/whatsapp/chat/index.blade.php`, `resources/js/whatsapp-chat/{index,render,api,util}.js` (lazy chunk imported from `app.js` only when `#wa-chat` exists; vanilla DOM, no new framework, all remote text via textContent/createTextNode, safe http(s)-only linkify, media only via `media.show` URLs), routes `whatsapp.chat.index` in `central.php`/`tenant_admin.php`, nav in `components/layouts/admin.blade.php` (connection item now has an explicit active pattern), lang `whatsapp.chat.title` + `whatsapp.chat.ui.*` en/ar, `tests/Feature/WhatsappChatPageTest.php` (3, green).
**Built:** two-pane + mobile single pane (logical start/end classes for RTL); list with search/filters (all/unread/mine/archived)/pin/unread badges; renderers for text(+link preview), image(lightbox), video, audio, voice-note waveform-lite, document, sticker, location, live location, contact, poll, reply quote, reactions, edited/deleted; composer: Enter-send, emoji, attach, drag-drop, paste, MediaRecorder voice note (ogg/opus else webm), location, contact, reply, react, edit, delete-for-everyone, resend; optimistic send with failed/retry; Echo events, ticks, typing, reconnect gap-fill (`after=`), not-connected banner/empty state; archive/assign/delete-local/new chat by phone; customer badge when `customer_id` set (tenant, needs customers.view).
**Verified:** `npm run build` ok; Whatsapp/Role/Access Pest suites green; page route renders and is authorized per guard. **NOT verified:** no browser was driven, so layout, RTL look, Echo realtime, recorder, and two-session isolation are untested in a real browser (JS only compiled by Vite).
**Open issues for Phase 5:** (1) "mark unread" has no backend endpoint, so it is not in the UI. (2) Poll sending UI not built (API supports it); polls show no vote results (Node does not emit). (3) `customer_id` is never populated by ingest, so the customer badge rarely shows; needs a phone->customer lookup. (4) Emoji picker is a small built-in set. (5) Broadcast conversation payload omits assigned_*/customer_id (client merges). (6) Needs a real-browser manual pass on central + tenant host with two sessions.


### Phase 5 — done (hardening, tests, docs)
**Security review findings/fixes:** authz (route middleware per action, owner from route name, channels by guard+tenant), `ownedBy()` on every query, media route (nosniff/CSP sandbox/inline allowlist), upload MIME sniff + size caps, token regex before Node pull, JS (textContent only, http(s)-only links), HMAC 5-min window with idempotent ingest, no body logging: reviewed, no exploitable issue found. Echo adoption stays heuristic (scoped to owner+conversation+type+text, 5 min, pending id-less rows only) - documented.
**Perf:** thread loading eager-loads reactions (N+1 gone; `toChat()` uses the loaded relation); new migration `2026_12_02_100001_add_whatsapp_messages_thread_index` (conversation_id,id) because EXPLAIN showed a filesort; conversation list filesorts a small per-owner set (acceptable); page limit capped 100; media pulls now stream to a temp file (`NodeClient::pullMedia` returns `path`, hashing/sniffing/storing from the file, temp unlinked).
**Phase-4 gaps:** conversation ingest links `customer_id` for tenant owners by exact, unambiguous same-tenant phone match (country code + national number); broadcast conversation payload now includes assigned_admin_id/assigned_guard/customer_id. Not done: mark unread, poll-sending UI (listed as limitations).
**Docs:** WHATSAPP_INTEGRATION.md chat section, docs/whatsapp-chat/OPERATIONS.md.
**NOT verified:** no real WhatsApp session, no browser pass, Reverb realtime untested end to end.
