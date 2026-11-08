<?php

namespace App\Services\Marketing;

use App\Models\SeoRedirect;
use Illuminate\Support\Facades\Cache;

/**
 * 301 redirects for changed storefront slugs (P5 SEO suggestions, MCP.md §19.3 "preserve existing URLs"). Paths are stored without the
 * `/{locale}` prefix for localized pages (product/x, category/x, brand/x, bundle/x, page/x, blog/x) and as-is for custom pages (/{slug}).
 * Chains are collapsed (A→B then B→C stores A→C and B→C); a path that becomes live again loses its redirect. Served by
 * App\Http\Middleware\SeoRedirects on storefront 404 responses only (no lookup on normal pages).
 */
class SeoRedirectService
{
    /** entity type => [path prefix, localized] */
    public const PATHS = [
        'product' => ['product/', true], 'category' => ['category/', true], 'brand' => ['brand/', true], 'bundle' => ['bundle/', true],
        'static_page' => ['page/', true], 'blog_post' => ['blog/', true], 'custom_page' => ['', false],
    ];

    public function pathFor(string $type, string $slug): string
    {
        return self::PATHS[$type][0].$slug;
    }

    /** Record old → new for an entity's slug change. Returns the redirect id. */
    public function record(string $type, int $id, string $oldSlug, string $newSlug, ?int $suggestionId = null, ?int $adminId = null): ?int
    {
        if ($oldSlug === $newSlug || ! isset(self::PATHS[$type])) {
            return null;
        }
        $from = $this->pathFor($type, $oldSlug);
        $to = $this->pathFor($type, $newSlug);
        $localized = self::PATHS[$type][1];
        SeoRedirect::query()->where('from_path', $to)->delete();                     // the new path is live again
        SeoRedirect::query()->where('to_path', $from)->where('localized', $localized)->update(['to_path' => $to]);   // collapse chains
        $r = SeoRedirect::query()->updateOrCreate(['from_path' => $from], ['to_path' => $to, 'localized' => $localized, 'entity_type' => $type,
            'entity_id' => $id, 'status_code' => 301, 'suggestion_id' => $suggestionId, 'created_by' => $adminId]);
        Cache::forget('seo_redirects.count');

        return $r->id;
    }

    /** Undo of a slug change (rollback): drop the redirect created for it and point older ones back. */
    public function undo(string $type, string $oldSlug, string $newSlug): void
    {
        $from = $this->pathFor($type, $oldSlug);
        $to = $this->pathFor($type, $newSlug);
        SeoRedirect::query()->where('from_path', $from)->delete();
        SeoRedirect::query()->where('to_path', $to)->where('entity_type', $type)->update(['to_path' => $from]);
        Cache::forget('seo_redirects.count');
    }

    /** Target URL path (with leading slash) for a 404 request path, or null. */
    public function resolve(string $path, ?string $locale): ?string
    {
        if ((int) Cache::remember('seo_redirects.count', 600, fn () => SeoRedirect::query()->count()) === 0) {
            return null;
        }
        $path = trim($path, '/');
        $r = null;
        if ($locale !== null && str_starts_with($path, $locale.'/')) {
            $r = SeoRedirect::query()->where('from_path', substr($path, strlen($locale) + 1))->where('localized', true)->first();
        }
        $r ??= SeoRedirect::query()->where('from_path', $path)->where('localized', false)->first();
        if (! $r) {
            return null;
        }
        $r->forceFill(['hits' => $r->hits + 1, 'last_hit_at' => now()])->saveQuietly();

        return '/'.($r->localized ? $locale.'/' : '').$r->to_path;
    }
}
