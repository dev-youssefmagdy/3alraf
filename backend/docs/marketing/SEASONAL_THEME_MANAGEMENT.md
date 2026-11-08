# Seasonal theme management (P5, MCP.md §18)

## Design tokens
The storefront palette lives in `resources/css/theme.css` (Tailwind v4 `@theme`: `--color-brand-50…900`, `--color-accent`, `--color-accent-light`,
plum-tinted `--color-slate-50…950`, `--color-red-500/600`, `--color-emerald-600/700`, `--color-amber-500`, font). Tailwind v4 utilities read
those CSS variables (`bg-brand-600 { background-color: var(--color-brand-600) }`), so a runtime override of the variables recolours the whole
storefront without rebuilding CSS. Compiled CSS is **never edited**.

## Runtime override
- `marketing_themes`: key (family, e.g. `ramadan`), name {locale}, `version` (per key), `tokens` {css-variable: #rrggbb}, notes, is_active (at most
  one), suggestion_id, previous_theme_id, created_by, activated_by/at, deactivated_at.
- `<x-layouts.storefront>` asks `ThemeService::storefront()`; when a theme is active it prints
  `<style id="seasonal-theme">html:root{--color-brand-600:#…;…}</style>` (unlayered, so it beats Tailwind's `@layer theme`) and the
  `theme-color` meta. The CSS string is cached forever (`marketing.theme.active_css`) and cleared on every activation / revert.
- Only the whitelisted tokens (`ThemeService::TOKENS`) and normalised hex values reach the page (no CSS injection possible).

## Suggestion → preview → approve → apply → revert
1. Claude reads `GET /theme` (current tokens, active runtime override, `overridable_tokens`) + `seasonal-calendar`, then
   `POST /suggestions/seasonal_theme` (tokens + name + key + advisory design notes).
2. Panel **اقتراحات الألوان والمواسم**: swatches (proposed over current), sample button / badge / promo bar, WCAG contrast table, compare per token,
   edit individual colours.
3. **Preview on the store** (`GET /marketer/suggestions/{id}/preview`) redirects to a temporary signed storefront URL
   (`?theme_preview={id}&expires&signature`, 60 min). The preview is shown only when the visitor is an admin holding `ai_suggestions.view`
   (an unsigned / expired URL, a guest or an admin without the permission sees the live theme); it then sticks for 30 minutes in that admin's
   session (browse the store), shows an "admin preview" bar with an exit link (`?theme_preview=off`) and the page is `noindex,nofollow`.
4. Approve (`ai_suggestions.approve` + `ai_theme.approve`) → Apply: a new version of the key is stored and activated; the previous active
   theme id is kept (`previous_theme_id`, execution `before`).
5. Revert: theme page buttons (perm `ai_theme.approve`) — **Revert to previous** (the active version's `previous_theme_id`, or defaults),
   **Activate** any stored version, **Turn seasonal theme off** (compiled defaults). Suggestion rollback restores the theme that was active
   before that apply. Stale: if another theme was activated after the suggestion was made, apply asks for a re-snapshot.

## Contrast check (WCAG 2.x)
`ThemeService::PAIRS` (foreground / background / minimum): primary button white on brand-600 (4.5), hover white on brand-700 (4.5), promo bar
white on brand-800 (4.5), links brand-600 on white (4.5), body slate-800 on slate-50 (4.5), headings slate-900 on white (4.5), secondary text
slate-500 on white (4.5), badges brand-700 on brand-50 (4.5), accent slate-900 on accent (3.0), danger / success white on red-600 /
emerald-600 (4.5). Failing pairs are warnings (create response, detail page, approve log) — the reviewer decides; nothing is auto-corrected.
Typography / decorations are advisory (fonts are compiled; decorative assets would be uploaded by the owner).
