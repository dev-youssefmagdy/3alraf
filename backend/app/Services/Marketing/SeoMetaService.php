<?php

namespace App\Services\Marketing;

use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\CustomPage;
use App\Models\SeoMeta;
use App\Models\StaticPage;
use Illuminate\Http\Request;

/**
 * Meta title / description for storefront entities without own meta columns (P5 SEO suggestions): category, brand, bundle, static page,
 * custom page, and the blog post title (blog posts have their own meta_description column). Read by the storefront layout for the current
 * route (one indexed query on those pages only); written only by the SEO suggestion handler.
 */
class SeoMetaService
{
    /** storefront route name => [entity type, model] */
    public const ROUTES = [
        'storefront.category.show' => ['category', Category::class],
        'storefront.brand.show' => ['brand', Brand::class],
        'storefront.bundle.show' => ['bundle', Bundle::class],
        'storefront.pages.show' => ['static_page', StaticPage::class],
        'storefront.blog.show' => ['blog_post', BlogPost::class],
        'storefront.custom-page' => ['custom_page', CustomPage::class],
    ];

    private array $memo = [];

    public function get(string $type, int $id): ?SeoMeta
    {
        return SeoMeta::query()->where('entity_type', $type)->where('entity_id', $id)->first();
    }

    /** @return array{meta_title:array,meta_description:array} current values per locale (empty arrays when none) */
    public function values(string $type, int $id): array
    {
        $m = $this->get($type, $id);

        return ['meta_title' => $m ? array_filter($m->getTranslations('meta_title')) : [], 'meta_description' => $m ? array_filter($m->getTranslations('meta_description')) : []];
    }

    /** Replace the stored values (empty arrays on both = row removed). Returns the previous values. */
    public function put(string $type, int $id, array $metaTitle, array $metaDescription, ?int $suggestionId = null, ?int $adminId = null): array
    {
        $before = $this->values($type, $id);
        $metaTitle = array_filter($metaTitle, fn ($v) => is_string($v) && trim($v) !== '');
        $metaDescription = array_filter($metaDescription, fn ($v) => is_string($v) && trim($v) !== '');
        if (! $metaTitle && ! $metaDescription) {
            SeoMeta::query()->where('entity_type', $type)->where('entity_id', $id)->delete();
        } else {
            SeoMeta::query()->updateOrCreate(['entity_type' => $type, 'entity_id' => $id],
                ['meta_title' => $metaTitle ?: null, 'meta_description' => $metaDescription ?: null, 'suggestion_id' => $suggestionId, 'updated_by' => $adminId]);
        }
        unset($this->memo[$type.':'.$id]);

        return $before;
    }

    /** Override for the current storefront page: ['title' => ?string, 'description' => ?string] in $locale. */
    public function forRequest(Request $request, string $locale): array
    {
        $none = ['title' => null, 'description' => null];
        $name = $request->route()?->getName();
        if (! $name || ! isset(self::ROUTES[$name]) || ! ($slug = $request->route('slug'))) {
            return $none;
        }
        [$type, $model] = self::ROUTES[$name];
        $key = $type.':'.$slug.':'.$locale;
        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }
        $m = SeoMeta::query()->where('entity_type', $type)->whereIn('entity_id', $model::query()->select('id')->where('slug', (string) $slug))->first();

        return $this->memo[$key] = $m ? ['title' => $m->getTranslation('meta_title', $locale, false) ?: null, 'description' => $m->getTranslation('meta_description', $locale, false) ?: null] : $none;
    }
}
