<?php

namespace App\Services\Marketing\Suggestions\Types;

use App\Enums\Marketing\SuggestionTypeKey;
use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\CustomPage;
use App\Models\MarketingSuggestion;
use App\Models\MarketingSuggestionExecution;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StaticPage;
use App\Services\BlogPostService;
use App\Services\BrandService;
use App\Services\BundleService;
use App\Services\CatalogService;
use App\Services\CategoryService;
use App\Services\CustomPageService;
use App\Services\Marketing\SeoMetaService;
use App\Services\Marketing\SeoRedirectService;
use App\Services\Marketing\Suggestions\ApplyResult;
use App\Services\Marketing\Suggestions\EntityDtos;
use App\Services\Marketing\Suggestions\SuggestionException;
use App\Services\ProductService;
use App\Services\SeoSettingsService;
use App\Services\StaticPageService;
use App\Support\Marketing\InternalLink;
use App\Support\ReservedSlugs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SEO (MCP.md §19). One suggestion per entity. Applied fields: meta_title, meta_description (per locale) and slug; the rest (`notes`: h1,
 * content / image-alt / structured-data / internal-link recommendations, keywords, risks, expected benefit) is advisory and shown to the reviewer.
 * Field mapping (docs/marketing/SEO_MODULE.md):
 *   product     meta → storefront wording overlay products.content.meta_* (CatalogService) · slug → ProductService::changeSlug
 *   blog_post   meta_description → blog_posts.meta_description, meta_title → seo_metas (BlogPostService / SeoMetaService) · slug → BlogPostService
 *   category, brand, bundle, static_page, custom_page   meta → seo_metas (SeoMetaService) · slug → their own service
 *   site        home title / description → setting `seo` (SeoSettingsService) · no slug
 * A slug change stores a 301 (SeoRedirectService), duplicate slugs are refused, reserved custom-page slugs too. Rollback restores both.
 */
class SeoSuggestionType extends AbstractSuggestionType
{
    public const TITLE_MAX = 120;

    public const DESCRIPTION_MAX = 320;

    protected array $targetModels = ['product' => Product::class, 'category' => Category::class, 'brand' => Brand::class, 'bundle' => Bundle::class,
        'static_page' => StaticPage::class, 'blog_post' => BlogPost::class, 'custom_page' => CustomPage::class, 'site' => Setting::class];

    public function __construct(private SeoMetaService $metas, private SeoRedirectService $redirects, private SeoSettingsService $seo, private CatalogService $catalog,
        private ProductService $products, private CategoryService $categories, private BrandService $brands, private BundleService $bundles,
        private StaticPageService $pages, private BlogPostService $posts, private CustomPageService $customPages) {}

    public function key(): SuggestionTypeKey
    {
        return SuggestionTypeKey::SEO;
    }

    public function targetRequired(?array $payload = null): bool
    {
        return true;
    }

    public function approvePermission(): ?string
    {
        return 'ai_seo.approve';
    }

    public function resolveTarget(?string $type, ?int $id): ?Model
    {
        if ($type === 'site') {
            return (new Setting)->forceFill(['key' => 'seo']);
        }

        return parent::resolveTarget($type, $id);
    }

    public function rules(?string $targetType = null): array
    {
        $r = $this->localeRules('meta_title', self::TITLE_MAX) + $this->localeRules('meta_description', self::DESCRIPTION_MAX) + $this->localeRules('notes.h1', 200) + [
            'payload' => ['required', 'array'],
            'payload.notes' => ['nullable', 'array'],
            'payload.notes.keywords' => ['nullable', 'array', 'max:30'], 'payload.notes.keywords.*' => ['string', 'max:80'],
            'payload.notes.internal_links' => ['nullable', 'array', 'max:20'],
            'payload.notes.internal_links.*' => ['string', 'max:500', fn ($a, $v, $fail) => InternalLink::isInternal($v) ? null : $fail(__('marketing.suggestions.errors.internal_link'))],
        ];
        foreach (['content_improvements', 'image_alt', 'structured_data', 'risks', 'expected_benefit'] as $k) {
            $r['payload.notes.'.$k] = ['nullable', 'string', 'max:3000'];
        }
        if ($targetType !== 'site') {
            $r['payload.slug'] = ['nullable', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'];
        }

        return $r;
    }

    public function normalize(array $payload, ?string $targetType = null): array
    {
        $notes = (array) ($payload['notes'] ?? []);
        $n = array_filter([
            'h1' => $this->plainLocales($notes['h1'] ?? null),
            'keywords' => array_values(array_unique(array_filter(array_map(fn ($k) => $this->plain($k, 80), (array) ($notes['keywords'] ?? []))))),
            'internal_links' => array_values(array_unique(array_filter(array_map(fn ($u) => InternalLink::normalize(is_string($u) ? $u : null), (array) ($notes['internal_links'] ?? []))))),
        ] + collect(['content_improvements', 'image_alt', 'structured_data', 'risks', 'expected_benefit'])->mapWithKeys(fn ($k) => [$k => $this->plain($notes[$k] ?? null, 3000)])->all(),
            fn ($v) => $v !== null && $v !== []);
        ksort($n);
        $slug = $targetType === 'site' ? null : (is_string($payload['slug'] ?? null) && trim($payload['slug']) !== '' ? strtolower(trim($payload['slug'])) : null);

        return array_filter(['meta_title' => $this->plainLocales($payload['meta_title'] ?? null), 'meta_description' => $this->plainLocales($payload['meta_description'] ?? null),
            'slug' => $slug, 'notes' => $n], fn ($v) => $v !== null && $v !== []);
    }

    public function check(array $payload, ?Model $target, ?MarketingSuggestion $s = null): array
    {
        $errors = [];
        $warnings = [];
        if (! isset($payload['meta_title']) && ! isset($payload['meta_description']) && ! isset($payload['slug'])) {
            $errors['payload'] = __('marketing.suggestions.errors.empty_change');
        }
        foreach ((array) ($payload['meta_title'] ?? []) as $l => $v) {
            mb_strlen($v) > 60 && $warnings[] = __('marketing.suggestions.warnings.long_title', ['locale' => $l, 'n' => mb_strlen($v)]);
        }
        foreach ((array) ($payload['meta_description'] ?? []) as $l => $v) {
            mb_strlen($v) > 160 && $warnings[] = __('marketing.suggestions.warnings.long_description', ['locale' => $l, 'n' => mb_strlen($v)]);
            mb_strlen($v) < 50 && $warnings[] = __('marketing.suggestions.warnings.short_description', ['locale' => $l, 'n' => mb_strlen($v)]);
        }
        $type = $this->typeOf($target);
        if (isset($payload['slug']) && $target && $type !== 'site' && $payload['slug'] !== $target->slug) {
            $class = $this->targetModels[$type];
            $q = $class::query()->where('slug', $payload['slug'])->where('id', '!=', $target->getKey());
            if (in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
                $q->withTrashed();
            }
            if ($q->exists()) {
                $errors['payload.slug'] = __('marketing.suggestions.errors.slug_taken');
            } elseif ($type === 'custom_page' && ReservedSlugs::isReserved($payload['slug'])) {
                $errors['payload.slug'] = __('marketing.suggestions.errors.slug_reserved');
            } else {
                $warnings[] = __('marketing.suggestions.warnings.slug_redirect', ['from' => $target->slug, 'to' => $payload['slug']]);
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    public function snapshot(?Model $target, array $payload): ?array
    {
        if (! $target) {
            return null;
        }
        $cur = $this->current($target);
        $snap = [];
        foreach (['meta_title', 'meta_description'] as $f) {
            foreach (array_keys((array) ($payload[$f] ?? [])) as $l) {
                $snap[$f][$l] = $cur[$f][$l] ?? null;
            }
        }
        if (isset($payload['slug'])) {
            $snap['slug'] = $cur['slug'];
        }

        return $snap;
    }

    public function compare(MarketingSuggestion $s, ?Model $target): array
    {
        $p = (array) $s->payload;
        $cur = $target ? $this->current($target) : ['meta_title' => [], 'meta_description' => [], 'slug' => null];
        $rows = [];
        foreach (['meta_title', 'meta_description'] as $f) {
            foreach ($this->locales() as $l) {
                if (isset($p[$f][$l]) || isset($cur[$f][$l])) {
                    $rows[] = $this->row($f.'.'.$l, $cur[$f][$l] ?? null, $p[$f][$l] ?? null, $this->label($f).' ('.$l.')');
                }
            }
        }
        if ($this->typeOf($target) !== 'site') {
            $rows[] = $this->row('slug', $cur['slug'], $p['slug'] ?? null);
        }

        return $rows;
    }

    public function apply(MarketingSuggestion $s, ?Model $target, array $options, ?int $adminId): ApplyResult
    {
        $p = (array) $s->payload;
        $this->ensure($this->check($p, $target, $s));
        $type = $this->typeOf($target);
        $before = $this->current($target);
        $title = array_replace($before['meta_title'], (array) ($p['meta_title'] ?? []));
        $desc = array_replace($before['meta_description'], (array) ($p['meta_description'] ?? []));
        $this->writeMeta($type, $target, $title, $desc, $s->id, $adminId);
        $redirect = null;
        if (isset($p['slug']) && $type !== 'site' && $p['slug'] !== $target->slug) {
            $old = $target->slug;
            $new = $this->writeSlug($type, $target, $p['slug']);
            if ($new !== $p['slug']) {
                throw SuggestionException::validation(['payload.slug' => __('marketing.suggestions.errors.slug_taken')]);
            }
            $redirect = $this->redirects->record($type, (int) $target->getKey(), $old, $new, $s->id, $adminId);
        }
        $fresh = $type === 'site' ? $target : $target->fresh();
        $after = $this->current($fresh) + ['redirect_id' => $redirect];

        return new ApplyResult($before, $after, $type === 'site' ? 'site' : $type, $type === 'site' ? null : (int) $target->getKey());
    }

    public function supportsRollback(): bool
    {
        return true;
    }

    public function rollback(MarketingSuggestion $s, MarketingSuggestionExecution $exec, ?int $adminId): array
    {
        $before = (array) $exec->before;
        $target = $this->resolveTarget($s->target_entity_type, $s->target_entity_id) ?? throw SuggestionException::make('target_not_found', 409);
        $type = $this->typeOf($target);
        $this->writeMeta($type, $target, (array) ($before['meta_title'] ?? []), (array) ($before['meta_description'] ?? []), $s->id, $adminId);
        if ($type !== 'site' && ($before['slug'] ?? null) && $before['slug'] !== $target->slug) {
            $current = $target->slug;
            $this->writeSlug($type, $target, $before['slug']);
            $this->redirects->undo($type, $before['slug'], $current);
        }

        return $this->current($type === 'site' ? $target : $target->fresh());
    }

    public function editFields(?string $targetType = null): array
    {
        $f = [$this->field('payload.meta_title', 'locales-text'), $this->field('payload.meta_description', 'locales-textarea')];
        if ($targetType !== 'site') {
            $f[] = $this->field('payload.slug', 'text', null, ['dir' => 'ltr']);
        }

        return array_merge($f, [
            $this->field('payload.notes.h1', 'locales-text'),
            $this->field('payload.notes.keywords', 'list'),
            $this->field('payload.notes.internal_links', 'list', null, ['dir' => 'ltr']),
            $this->field('payload.notes.content_improvements', 'textarea'),
            $this->field('payload.notes.image_alt', 'textarea'),
            $this->field('payload.notes.structured_data', 'textarea'),
            $this->field('payload.notes.risks', 'textarea'),
            $this->field('payload.notes.expected_benefit', 'textarea'),
        ]);
    }

    // ------------------------------------------------------------------

    private function typeOf(?Model $m): ?string
    {
        if (! $m) {
            return null;
        }
        foreach ($this->targetModels as $t => $class) {
            if ($m instanceof $class) {
                return $t;
            }
        }

        return null;
    }

    /** Effective current SEO values of the entity (what the storefront renders from). */
    public function current(Model $m): array
    {
        $type = $this->typeOf($m);
        if ($type === 'site') {
            $s = $this->seo->get();

            return ['meta_title' => array_filter((array) $s['home_title']), 'meta_description' => array_filter((array) $s['home_description']), 'slug' => null];
        }
        if ($type === 'product') {
            $o = (array) ($m->content ?? []);
            $get = fn ($f) => collect($this->locales())->mapWithKeys(fn ($l) => [$l => (($o[$f][$l] ?? '') ?: $m->getTranslation($f, $l, false)) ?: null])->filter()->all();

            return ['meta_title' => $get('meta_title'), 'meta_description' => $get('meta_description'), 'slug' => $m->slug];
        }
        $stored = $this->metas->values($type, (int) $m->getKey());
        if ($type === 'blog_post') {
            $stored['meta_description'] = $this->translations($m, 'meta_description');
        }

        return $stored + ['slug' => $m->slug];
    }

    private function writeMeta(string $type, Model $m, array $title, array $desc, ?int $suggestionId, ?int $adminId): void
    {
        $title = array_filter($title, fn ($v) => is_string($v) && $v !== '');
        $desc = array_filter($desc, fn ($v) => is_string($v) && $v !== '');
        switch ($type) {
            case 'site':
                $this->seo->update(array_replace($this->seo->get(), ['home_title' => $title, 'home_description' => $desc]));
                break;
            case 'product':
                /** @var Product $m */
                $content = (array) ($m->content ?? []);
                $content['meta_title'] = $title;
                $content['meta_description'] = $desc;
                $this->catalog->updateContent($m, array_filter($content, fn ($v) => $v !== [] && $v !== null));
                break;
            case 'blog_post':
                /** @var BlogPost $m */
                if ($desc != $this->translations($m, 'meta_description')) {
                    $this->posts->update($m, EntityDtos::blogPost($m, ['meta_description' => $desc]));
                }
                $this->metas->put($type, (int) $m->getKey(), $title, [], $suggestionId, $adminId);
                break;
            default:
                $this->metas->put($type, (int) $m->getKey(), $title, $desc, $suggestionId, $adminId);
        }
    }

    /** Change the slug through the entity's service; returns the slug actually stored. */
    private function writeSlug(string $type, Model $m, string $slug): string
    {
        match ($type) {
            'product' => $this->products->changeSlug($m, $slug),
            'category' => $this->categories->update($m, EntityDtos::category($m, ['slug' => $slug])),
            'brand' => $this->brands->update($m, EntityDtos::brand($m, ['slug' => $slug])),
            'bundle' => $this->bundles->update($m, EntityDtos::bundle($m, ['slug' => $slug])),
            'static_page' => $this->pages->update($m, EntityDtos::staticPage($m, ['slug' => $slug])),
            'blog_post' => $this->posts->update($m, EntityDtos::blogPost($m, ['slug' => $slug])),
            'custom_page' => $this->customPages->update($m, EntityDtos::customPage($m, ['slug' => $slug])),
        };

        return (string) $m->fresh()->slug;
    }
}
