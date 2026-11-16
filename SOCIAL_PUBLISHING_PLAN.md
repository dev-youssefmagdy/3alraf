# 3alraf — Let Claude post to Facebook, Instagram and TikTok

Status: **plan + external setup steps. Nothing built yet.**
Rule (same as Meta ads): **Claude drafts → a person approves → Laravel publishes.** Claude never publishes directly; a wrong caption, price or
image on a public page cannot be taken back.

---

## 1. What already exists in the project

| Piece | Where | Used for |
|---|---|---|
| MCP server | `mcp-server/` | Claude creates social-content suggestions (`create_social_content_suggestion`, media tools). It cannot approve or publish. |
| Suggestions + approval engine | `/marketer/suggestions` | Review, edit, approve (marketer-owner only), apply. |
| Private social-media library | `/marketer/media` | Images / videos per product, versions, approval, **signed public URLs** (Instagram needs a public media URL). |
| Meta Graph client | `App\Services\Marketing\Meta\*` | Ads today (always PAUSED). Tokens stay in Laravel, never reach Claude. |
| n8n | `n8n/` on 127.0.0.1:5678 | Signed calls to the shop's integration API. |
| Health page | `/admin/health` | Will also check the publishing tokens. |

## 2. Target flow

1. **Draft** — Claude (MCP connector) writes posts on request ("5 Ramadan posts for perfumes") or on a schedule: caption AR/EN, hashtags,
   product, image/video from the library, platforms, proposed time. Saved as a pending suggestion.
2. **Review** — a marketer edits in `/marketer`; the **marketer-owner approves**.
3. **Schedule** — choose platforms and time (now or later).
4. **Publish** — a Laravel queue job posts at that time:
   - Facebook Page: Graph API page post (photo / video / link).
   - Instagram: create media container from the public URL → publish (photo, carousel, reel).
   - TikTok: Content Posting API (see §4.3).
5. **Track** — post IDs, permalinks, errors and an audit log in the panel; `/admin/health` warns when a token expires or is revoked.

Where n8n fits (optional): a **weekly content plan** (n8n asks Claude via the Claude API for drafts from the season calendar and best sellers,
saves them through the shop API as pending suggestions) and **notifications** ("5 posts waiting for approval", "post failed").
Publishing itself stays in Laravel (tokens, approval check and audit already live there).

## 3. Build phases

| Phase | Work | Result |
|---|---|---|
| 1 | Facebook + Instagram publishing from approved library items: schedule, queue job, status + log, health check, permissions (`social.publish` owner only) | Posts go out from the panel |
| 2 | MCP tools `get_publishing_calendar`, `propose_post` (propose only, never publish) | Claude plans posts end to end |
| 3 | TikTok "send to drafts" (inbox), then direct posting after TikTok's audit | TikTok covered |
| 4 | n8n weekly planning + approval / failure notifications | Hands-off drafting |

Start with **Phase 1** after the Meta setup in §4.1–4.2 is done.

---

## 4. External setup (owner steps)

### 4.1 Meta app (Facebook + Instagram)
1. Instagram must be a **Business** account linked to the **3alraf** Facebook Page
   (Page → Settings → Linked accounts → Instagram).
2. Go to **developers.facebook.com → My Apps** and open the app used for ads, or **Create App → Business**.
3. Add the products **Facebook Login for Business** and **Instagram** (Instagram API with Facebook Login).
4. Request these permissions (App Review → Permissions and features):
   - `pages_show_list`, `pages_read_engagement`, `pages_manage_posts`
   - `instagram_basic`, `instagram_content_publish`
   - `business_management`
5. **App review:** not needed while the app is in development mode and only your own Page/Instagram are used by the app's admins.
   If other people or businesses will use it, submit these permissions for review (screencast of the approve → publish flow).

### 4.2 Long-lived token (system user)
1. **business.facebook.com → Business settings → Users → System users → Add** (role: Admin), e.g. `3alraf-publisher`.
2. **Assign assets** to the system user: the 3alraf **Page** (full control) and the **Instagram account**.
3. **Generate new token** → choose the app → tick the permissions from §4.1 → expiry **Never**.
4. Note these IDs:
   - Page ID: Page → About → Page transparency, or Business settings → Pages.
   - Instagram user ID: Graph API Explorer → `GET /{page-id}?fields=instagram_business_account`.
5. Enter them in **Admin → Settings → System settings → Meta** (`meta_access_token`, `meta_page_id`, `meta_instagram_user_id`).
   The token is stored encrypted and never shown again.
6. Check **`/admin/health`** → Meta Ads should be "Working".

Notes: Instagram allows a limited number of API-published posts per account per 24 hours (enough for a shop).
Images must be JPEG/PNG reachable by a public HTTPS URL (the library's signed links are). Reels need MP4 (H.264/AAC).

### 4.3 TikTok
1. **developers.tiktok.com** → log in with the 3alraf TikTok (Business) account → **Manage apps → Connect an app**.
2. Add the product **Content Posting API**; scopes `video.upload` (send to drafts) and `video.publish` (direct post).
3. Redirect URI: `https://3alraf.com/marketer/social/tiktok/callback` (will be created in Phase 3).
4. **Until TikTok audits the app**, direct posts can only be private (`SELF_ONLY`). Start with **send to drafts**: the video lands in the
   TikTok app inbox and you tap Publish there. Apply for the audit later to allow public direct posts.
5. Keep the Client key and Client secret; they go into System settings when Phase 3 is built.

### 4.4 Claude connector (to draft posts from Claude)
1. The MCP server must be reachable at `https://mcp.3alraf.com` (HTTPS, nginx, systemd): see `mcp-server/deploy/`.
2. Create a marketing API token in **`/marketer/api-tokens`** with `marketing:read` and the `suggestions:write:social_content` /
   `suggestions:write:media_asset` scopes.
3. In Claude: **Settings → Connectors → Add custom connector** → `https://mcp.3alraf.com/mcp` → "Register automatically".

### 4.5 n8n (optional, Phase 4)
1. n8n on its own subdomain (`n8n/setup-local-sudo.sh` locally, `backend/deploy/nginx/n8n.conf` in production).
2. A Claude API key (console.anthropic.com) stored as an n8n credential, for the weekly drafting workflow.
3. An integration client in **`/marketer/integration-clients`** for n8n's signed calls.

---

## 5. Option without new code (today)
Claude writes the posts (captions + hashtags) in chat; you schedule them yourself in
**Meta Business Suite → Planner** (Facebook + Instagram) and **TikTok Studio**. Free and quick, but manual.

## 6. Safety rules (for the build)
- No MCP tool can publish, approve or delete a post; the API returns a review link only.
- Publishing requires an approval bound to the exact content (caption + media hash); editing after approval needs a new approval.
- Idempotent publish (no double posts on retry); every call and result is in the audit log, without tokens.
- Deleting or editing a live post is done by a person on the platform, not by Claude.
