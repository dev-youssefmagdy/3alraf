<?php

namespace App\Services\Marketing;

use App\DTOs\Marketing\MarketingQuery;
use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\Product;
use App\Models\StaticPage;
use App\Services\GiftFinder\GiftFinderInsights;
use App\Services\SeoSettingsService;
use App\Support\Marketing\MarketingResponse as R;
use App\Support\Marketing\StoreText;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

/**
 * Content reads of the Marketing internal API (P4): static pages, blog posts, SEO data with missing-meta flags, current theme tokens,
 * seasonal calendar. Store text = untrusted plain data (StoreText). The theme is read from resources/css/theme.css (Tailwind @theme tokens
 * compiled at build time; P5 adds versioned seasonal tokens).
 */
class MarketingContentService
{
    public const SEO_TYPES = ['product', 'category', 'brand', 'bundle', 'static_page', 'blog_post', 'site'];

    /** Recommended lengths (characters) used for the `short_*` / `long_*` flags. */
    public const META_TITLE_MAX = 60;

    public const META_DESCRIPTION_MIN = 50;

    public const META_DESCRIPTION_MAX = 160;

    public function __construct(private GiftFinderInsights $giftInsights, private SeoSettingsService $seo) {}

    private function url(string $route, array $params, ?string $locale = null): ?string
    {
        return rescue(fn () => route($route, ['locale' => $locale ?? (string) (setting('default_locale') ?: 'ar')] + $params), null, false);
    }

    // ---------------------------------------------------------------- pages / blog

    public function staticPages(MarketingQuery $q): LengthAwarePaginator
    {
        $withBody = (bool) $q->filter('include_content', false);
        $page = StaticPage::query()->orderBy('id')->paginate($q->perPage, ['*'], 'page', $q->page);
        $page->setCollection($page->getCollection()->map(fn (StaticPage $s) => [
            'id' => $s->id, 'slug' => $s->slug, 'title' => StoreText::names($s, 'title'), 'active' => (bool) $s->is_active,
            'excerpt' => StoreText::names($s, 'content', 300), 'content' => $withBody ? StoreText::rich($s, 'content', false) : null,
            'words' => collect(StoreText::locales())->mapWithKeys(fn ($l) => [$l => count(preg_split('/\s+/u', trim((string) StoreText::plain($s->getTranslation('content', $l, false))), -1, PREG_SPLIT_NO_EMPTY))])->all(),
            'url' => $this->url('storefront.pages.show', ['slug' => $s->slug]), 'updated_at' => $s->updated_at?->toIso8601String(),
        ]));

        return $page;
    }

    public function blogPosts(MarketingQuery $q): LengthAwarePaginator
    {
        $withBody = (bool) $q->filter('include_content', false);
        $qb = BlogPost::query()->orderByDesc('published_at')->orderByDesc('id');
        match ($q->filter('state')) {
            'live', 'active' => $qb->published(),
            'upcoming' => $qb->where('is_active', true)->where('published_at', '>', now()),
            'inactive' => $qb->where('is_active', false),
            default => null,
        };
        $page = $qb->paginate($q->perPage, ['*'], 'page', $q->page);
        $page->setCollection($page->getCollection()->map(fn (BlogPost $b) => [
            'id' => $b->id, 'slug' => $b->slug, 'title' => StoreText::names($b, 'title'), 'excerpt' => StoreText::names($b, 'excerpt', 500),
            'meta_description' => StoreText::names($b, 'meta_description'), 'content' => $withBody ? StoreText::rich($b, 'content', false) : null,
            'state' => ! $b->is_active ? 'draft' : ($b->published_at && $b->published_at->isFuture() ? 'scheduled' : 'published'),
            'published_at' => $b->published_at?->toIso8601String(), 'updated_at' => $b->updated_at?->toIso8601String(),
            'url' => $this->url('storefront.blog.show', ['slug' => $b->slug]),
        ]));

        return $page;
    }

    public function blogCategoriesWarning(): array
    {
        return R::warning('not_supported', 'The blog has no categories in this store (blog_posts has no category relation).', ['field' => 'blog_categories']);
    }

    // ---------------------------------------------------------------- SEO

    /** One locale's SEO flags for a title / description pair (description may be a body fallback when no meta field exists). */
    private function flags(?string $title, ?string $desc, bool $metaFields): array
    {
        $t = $title !== null ? trim((string) StoreText::plain($title)) : '';
        $d = $desc !== null ? trim((string) StoreText::plain($desc)) : '';

        return ['title' => $t !== '' ? $t : null, 'description' => $d !== '' ? mb_strimwidth($d, 0, 320, '…') : null,
            'missing_title' => $t === '', 'missing_description' => $d === '', 'long_title' => mb_strlen($t) > self::META_TITLE_MAX,
            'short_description' => $d !== '' && mb_strlen($d) < self::META_DESCRIPTION_MIN, 'long_description' => mb_strlen($d) > self::META_DESCRIPTION_MAX,
            'source' => $metaFields ? 'meta_fields' : 'content_fallback'];
    }

    private function entity(string $type, Model $m): array
    {
        $loc = [];
        foreach (StoreText::locales() as $l) {
            $g = fn ($a) => $m->getTranslation($a, $l, false);
            $loc[$l] = match ($type) {
                'product' => $this->flags($g('meta_title') ?? null, $g('meta_description'), true) + ['name' => StoreText::plain($g('name')), 'has_description' => trim((string) StoreText::plain($g('description'))) !== ''],
                'blog_post' => $this->flags($g('title'), $g('meta_description') ?: $g('excerpt'), true),
                'static_page' => $this->flags($g('title'), $g('content'), false),
                'category', 'bundle' => $this->flags($g('name'), $g('description'), false),
                'brand' => $this->flags($g('name'), null, false),
            };
        }
        $missing = collect($loc)->contains(fn ($x) => $x['missing_title'] || $x['missing_description']);
        $route = ['product' => 'storefront.product.show', 'category' => 'storefront.category.show', 'brand' => 'storefront.brand.show', 'bundle' => 'storefront.bundle.show',
            'static_page' => 'storefront.pages.show', 'blog_post' => 'storefront.blog.show'][$type];

        return ['entity_type' => $type, 'id' => $m->getKey(), 'slug' => $m->slug, 'active' => (bool) ($m->is_active ?? true),
            'meta_fields_supported' => in_array($type, ['product', 'blog_post'], true), 'has_missing_meta' => $missing,
            'image' => match ($type) {
                'product' => $m->images->first()?->url, 'category' => $m->image_url, 'brand' => $m->logo_url, 'bundle' => $m->imageUrl(), default => null
            },
            'urls' => collect(StoreText::locales())->mapWithKeys(fn ($l) => [$l => $this->url($route, ['slug' => $m->slug], $l)])->all(), 'locales' => $loc];
    }

    private function model(string $type): string
    {
        return ['product' => Product::class, 'category' => Category::class, 'brand' => Brand::class, 'bundle' => Bundle::class, 'static_page' => StaticPage::class, 'blog_post' => BlogPost::class][$type];
    }

    /** @return array{page:LengthAwarePaginator,warnings:list<array>} */
    public function seoData(MarketingQuery $q): array
    {
        $type = (string) $q->filter('entity_type', 'product');
        if ($type === 'site') {
            $s = $this->seo->get();
            $site = ['entity_type' => 'site', 'indexable' => $s['indexable'], 'locales' => collect(StoreText::locales())->mapWithKeys(fn ($l) => [$l => $this->flags(($s['home_title'][$l] ?? null) ?: null, ($s['home_description'][$l] ?? null) ?: null, true)
                + ['title_suffix' => ($s['title_suffix'][$l] ?? null) ?: null]])->all(), 'has_default_og_image' => $s['default_og_image'] !== ''];

            return ['page' => new Paginator([$site], 1, $q->perPage, 1), 'warnings' => []];
        }
        $class = $this->model($type);
        $qb = $class::query()->orderBy('id');
        if ($type === 'product') {
            $qb->with('images');
        }
        if ($id = $q->filter('id')) {
            $qb->whereKey($id);
        }
        if ((bool) $q->filter('missing_only', false)) {   // flags are computed in PHP (plain-text lengths), so filter the whole set
            $rows = $qb->get()->map(fn ($m) => $this->entity($type, $m))->filter(fn ($r) => $r['has_missing_meta'])->values();
            $page = new Paginator($rows->forPage($q->page, $q->perPage)->values()->all(), $rows->count(), $q->perPage, $q->page);
        } else {
            $page = $qb->paginate($q->perPage, ['*'], 'page', $q->page);
            $page->setCollection($page->getCollection()->map(fn ($m) => $this->entity($type, $m)));
        }
        $warnings = in_array($type, ['category', 'brand', 'bundle', 'static_page'], true)
            ? [R::warning('not_supported', "{$type} has no dedicated meta title/description fields: the page uses its name / description (flags computed on those).", ['field' => 'meta'])] : [];

        return ['page' => $page, 'warnings' => $warnings];
    }

    // ---------------------------------------------------------------- theme / calendar

    /** Current storefront colour / font tokens (Tailwind v4 @theme block of resources/css/theme.css). */
    public function theme(): array
    {
        $file = resource_path('css/theme.css');
        $css = is_file($file) ? (string) file_get_contents($file) : '';
        $tokens = [];
        if (preg_match('/@theme\s*\{(.*?)\n\}/s', $css, $m)) {
            preg_match_all('/--([a-z0-9-]+)\s*:\s*([^;]+);/i', $m[1], $all, PREG_SET_ORDER);
            foreach ($all as $t) {
                $tokens[$t[1]] = trim($t[2]);
            }
        }
        $group = fn (string $prefix) => collect($tokens)->filter(fn ($v, $k) => str_starts_with($k, $prefix))->mapWithKeys(fn ($v, $k) => [substr($k, strlen($prefix)) => $v])->all();

        $active = app(ThemeService::class)->active();   // P5: seasonal theme = runtime CSS-variable overrides of these tokens

        return ['data' => [
            'source' => 'resources/css/theme.css', 'applied_at' => 'build (npm run build)',
            'runtime_overrides' => $active ? ['theme_id' => $active->id, 'key' => $active->key, 'version' => $active->version, 'name' => StoreText::names($active, 'name'),
                'tokens' => (array) $active->tokens, 'activated_at' => $active->activated_at?->toIso8601String()] : null,
            'overridable_tokens' => ThemeService::TOKENS,
            'brand' => $group('color-brand-'), 'accent' => ['accent' => $tokens['color-accent'] ?? null, 'accent-light' => $tokens['color-accent-light'] ?? null],
            'neutral' => $group('color-slate-'), 'status' => array_filter(['danger' => $tokens['color-red-600'] ?? null, 'success' => $tokens['color-emerald-600'] ?? null, 'warning' => $tokens['color-amber-500'] ?? null]),
            'font' => ['sans' => $tokens['font-sans'] ?? null], 'primary_action' => $tokens['color-brand-600'] ?? null, 'tokens' => $tokens,
        ], 'warnings' => $tokens ? [] : [R::warning('missing_data', 'Theme tokens could not be read.', ['field' => 'theme'])]];
    }

    public function seasonalCalendar(int $days): array
    {
        $label = fn ($k) => ['ar' => __('gift_finder.occasions.'.$k, [], 'ar'), 'en' => __('gift_finder.occasions.'.$k, [], 'en')];
        $upcoming = array_map(fn ($o) => $o + ['label' => $label($o['key'])], $this->giftInsights->upcomingOccasions($days));
        $configured = collect((array) config('gift_finder.occasion_calendar'))->map(fn ($def, $k) => ['key' => $k, 'label' => $label($k),
            'calendar' => isset($def['hijri']) ? 'hijri' : 'gregorian', 'rule' => isset($def['hijri']) ? ['month' => $def['hijri'][0], 'day' => $def['hijri'][1]] : ['month_day' => $def['gregorian'] ?? null]])->values()->all();

        return ['data' => ['today' => now()->toDateString(), 'days' => $days, 'upcoming' => $upcoming, 'configured' => $configured],
            'warnings' => [R::warning('approximate_dates', 'Hijri (lunar) occasions depend on the moon sighting and can be 1-2 days off (approximate=true).')]];
    }
}
