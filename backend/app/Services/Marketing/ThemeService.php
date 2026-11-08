<?php

namespace App\Services\Marketing;

use App\Models\MarketingSuggestion;
use App\Models\MarketingTheme;
use App\Support\Marketing\Contrast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Seasonal storefront themes (MCP.md §18, P5). The compiled Tailwind theme (resources/css/theme.css @theme) is never edited: the active
 * MarketingTheme is rendered by the storefront layout as an unlayered inline `<style>html:root{--color-…}</style>` (beats Tailwind's @layer theme
 * variables), cached until the next activation. Only the TOKENS below may be overridden. Preview: a signed storefront URL (+ session for 30 min)
 * shown only to admins holding ai_suggestions.view. Versions are kept: apply = new version + activate, revert = previous version (or defaults).
 */
class ThemeService
{
    public const CACHE_KEY = 'marketing.theme.active_css';

    public const SESSION_KEY = 'marketing_theme_preview';

    /** Overridable CSS variables (without the leading `--`) = the palette tokens of resources/css/theme.css. */
    public const TOKENS = [
        'color-brand-50', 'color-brand-100', 'color-brand-200', 'color-brand-300', 'color-brand-400', 'color-brand-500', 'color-brand-600', 'color-brand-700',
        'color-brand-800', 'color-brand-900', 'color-accent', 'color-accent-light',
        'color-slate-50', 'color-slate-100', 'color-slate-200', 'color-slate-300', 'color-slate-400', 'color-slate-500', 'color-slate-600', 'color-slate-700',
        'color-slate-800', 'color-slate-900', 'color-slate-950',
        'color-red-500', 'color-red-600', 'color-emerald-600', 'color-emerald-700', 'color-amber-500',
    ];

    /** Text / background pairs checked for WCAG contrast: key => [foreground token|#hex, background token|#hex, minimum ratio]. */
    public const PAIRS = [
        'button' => ['#ffffff', 'color-brand-600', 4.5],
        'button_hover' => ['#ffffff', 'color-brand-700', 4.5],
        'promo_bar' => ['#ffffff', 'color-brand-800', 4.5],
        'link' => ['color-brand-600', '#ffffff', 4.5],
        'body_text' => ['color-slate-800', 'color-slate-50', 4.5],
        'heading' => ['color-slate-900', '#ffffff', 4.5],
        'muted_text' => ['color-slate-500', '#ffffff', 4.5],
        'badge' => ['color-brand-700', 'color-brand-50', 4.5],
        'accent' => ['color-slate-900', 'color-accent', 3.0],
        'danger' => ['#ffffff', 'color-red-600', 4.5],
        'success' => ['#ffffff', 'color-emerald-600', 4.5],
    ];

    private ?array $defaults = null;

    /** Compiled default token values parsed from resources/css/theme.css (@theme). */
    public function defaults(): array
    {
        if ($this->defaults !== null) {
            return $this->defaults;
        }
        $css = is_file($f = resource_path('css/theme.css')) ? (string) file_get_contents($f) : '';
        $out = [];
        if (preg_match('/@theme\s*\{(.*?)\n\}/s', $css, $m)) {
            preg_match_all('/--([a-z0-9-]+)\s*:\s*([^;]+);/i', $m[1], $all, PREG_SET_ORDER);
            foreach ($all as $t) {
                if (in_array($t[1], self::TOKENS, true) && ($hex = Contrast::normalizeHex(trim($t[2])))) {
                    $out[$t[1]] = $hex;
                }
            }
        }

        return $this->defaults = $out;
    }

    public function active(): ?MarketingTheme
    {
        return MarketingTheme::query()->where('is_active', true)->latest('activated_at')->latest('id')->first();
    }

    /** Effective palette = compiled defaults overlaid with $tokens. */
    public function palette(array $tokens): array
    {
        return array_replace($this->defaults(), array_intersect_key($tokens, array_flip(self::TOKENS)));
    }

    /** @return list<array{pair:string,foreground:string,background:string,ratio:float,min:float,ok:bool}> */
    public function contrast(array $tokens): array
    {
        $pal = $this->palette($tokens);
        $val = fn (string $x) => str_starts_with($x, '#') ? $x : ($pal[$x] ?? '#000000');
        $out = [];
        foreach (self::PAIRS as $pair => [$fg, $bg, $min]) {
            $ratio = Contrast::ratio($val($fg), $val($bg));
            $out[] = ['pair' => $pair, 'foreground' => $val($fg), 'background' => $val($bg), 'ratio' => $ratio, 'min' => $min, 'ok' => $ratio >= $min];
        }

        return $out;
    }

    /** `html:root{--x:#..;}` for the given tokens (whitelisted names, normalised hex values only — nothing else can reach the page). */
    public function css(array $tokens): string
    {
        $decl = '';
        foreach ($tokens as $k => $v) {
            if (in_array($k, self::TOKENS, true) && ($hex = Contrast::normalizeHex($v))) {
                $decl .= "--{$k}:{$hex};";
            }
        }

        return $decl === '' ? '' : 'html:root{'.$decl.'}';
    }

    public function activeCss(): string
    {
        return $this->activeCached()['css'];
    }

    /** @return array{css:string,color:?string} cached until the next activation (no query on storefront pages) */
    private function activeCached(): array
    {
        $v = Cache::rememberForever(self::CACHE_KEY, function () {
            $t = $this->active();

            return ['css' => $t ? $this->css((array) $t->tokens) : '', 'color' => $t ? ($this->palette((array) $t->tokens)['color-brand-600'] ?? null) : null];
        });

        return is_array($v) ? $v + ['css' => '', 'color' => null] : ['css' => '', 'color' => null];
    }

    /**
     * What the storefront layout injects: preview tokens of a seasonal_theme suggestion (authorised admin, signed URL or the preview
     * session) or the active theme. @return array{css:string,preview:?MarketingSuggestion,theme_color:?string}
     */
    public function storefront(Request $request): array
    {
        $preview = $this->previewSuggestion($request);
        if ($preview) {
            $tokens = (array) ($preview->payload['tokens'] ?? []);

            return ['css' => $this->css($tokens), 'preview' => $preview, 'theme_color' => $this->palette($tokens)['color-brand-600'] ?? null];
        }
        $a = $this->activeCached();

        return ['css' => $a['css'], 'preview' => null, 'theme_color' => $a['css'] !== '' ? $a['color'] : null];
    }

    public function previewUrl(MarketingSuggestion $s, string $locale): string
    {
        return URL::temporarySignedRoute('storefront.home', now()->addMinutes((int) config('marketing.suggestions.theme_preview_minutes', 60)),
            ['locale' => $locale, 'theme_preview' => $s->id]);
    }

    private function previewSuggestion(Request $request): ?MarketingSuggestion
    {
        $admin = auth('admin')->user();
        if (! $admin || ! $admin->can('ai_suggestions.view') || ! $request->hasSession()) {
            return null;
        }
        $session = $request->session();
        if ($request->query('theme_preview') === 'off') {
            $session->forget(self::SESSION_KEY);

            return null;
        }
        $id = null;
        if ($request->query->has('theme_preview') && $request->hasValidSignature()) {
            $id = (int) $request->query('theme_preview');
            $session->put(self::SESSION_KEY, ['id' => $id, 'until' => now()->addMinutes(30)->timestamp]);
        } elseif (($p = $session->get(self::SESSION_KEY)) && ($p['until'] ?? 0) > now()->timestamp) {
            $id = (int) $p['id'];
        }

        return $id ? MarketingSuggestion::query()->whereKey($id)->where('type', 'seasonal_theme')->first() : null;
    }

    /** Store a new version of `key` and make it the only active theme. @return array{before:?int,after:int} */
    public function activateNew(string $key, array $name, array $tokens, ?string $notes, ?int $suggestionId, ?int $adminId): array
    {
        $r = DB::transaction(function () use ($key, $name, $tokens, $notes, $suggestionId, $adminId) {
            $current = MarketingTheme::query()->where('is_active', true)->lockForUpdate()->first();
            $version = (int) MarketingTheme::query()->where('key', $key)->max('version') + 1;
            MarketingTheme::query()->where('is_active', true)->update(['is_active' => false, 'deactivated_at' => now()]);
            $theme = MarketingTheme::create(['key' => $key, 'name' => $name, 'version' => $version, 'tokens' => $tokens, 'notes' => $notes, 'is_active' => true,
                'suggestion_id' => $suggestionId, 'previous_theme_id' => $current?->id, 'created_by' => $adminId, 'activated_by' => $adminId, 'activated_at' => now()]);

            return ['before' => $current?->id, 'after' => $theme->id];
        });
        Cache::forget(self::CACHE_KEY);

        return $r;
    }

    /** Make an existing version the active one (null = no seasonal theme, compiled defaults). @return array{before:?int,after:?int} */
    public function activateVersion(?int $themeId, ?int $adminId): array
    {
        $r = DB::transaction(function () use ($themeId, $adminId) {
            $current = MarketingTheme::query()->where('is_active', true)->lockForUpdate()->first();
            MarketingTheme::query()->where('is_active', true)->update(['is_active' => false, 'deactivated_at' => now()]);
            if ($themeId) {
                MarketingTheme::query()->whereKey($themeId)->firstOrFail()->update(['is_active' => true, 'activated_at' => now(), 'activated_by' => $adminId, 'deactivated_at' => null]);
            }

            return ['before' => $current?->id, 'after' => $themeId];
        });
        Cache::forget(self::CACHE_KEY);

        return $r;
    }

    /** Revert: the active theme's previous version becomes active again (defaults when it has none). */
    public function revert(?int $adminId): array
    {
        $current = $this->active();

        return $this->activateVersion($current?->previous_theme_id, $adminId);
    }
}
