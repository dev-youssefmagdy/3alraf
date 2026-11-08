<?php

namespace App\Services\Marketing\Suggestions;

use App\DTOs\BlogPostData;
use App\DTOs\BrandData;
use App\DTOs\BundleData;
use App\DTOs\CategoryData;
use App\DTOs\CustomPageData;
use App\DTOs\StaticPageData;
use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\CustomPage;
use App\Models\StaticPage;

/**
 * Builds the store's own DTOs from the CURRENT entity with a few fields overridden, so a suggestion changes exactly the proposed fields
 * through the regular service (CategoryService::update, BundleService::update, ...) and every other value (images, items, flags) is kept.
 */
final class EntityDtos
{
    private static function tr($m, string $attr): array
    {
        return array_filter($m->getTranslations($attr), fn ($v) => $v !== null && $v !== '');
    }

    public static function category(Category $c, array $o = []): CategoryData
    {
        return new CategoryData($o['name'] ?? self::tr($c, 'name'), $o['description'] ?? self::tr($c, 'description'), $c->parent_id ? (int) $c->parent_id : null,
            $o['slug'] ?? $c->slug, $c->icon, (int) $c->sort_order, (bool) $c->is_active, null, false);
    }

    public static function brand(Brand $b, array $o = []): BrandData
    {
        return new BrandData($o['name'] ?? self::tr($b, 'name'), $o['slug'] ?? $b->slug, (bool) $b->is_active, (int) $b->sort_order, null, false);
    }

    public static function staticPage(StaticPage $p, array $o = []): StaticPageData
    {
        return new StaticPageData($o['slug'] ?? $p->slug, $o['title'] ?? self::tr($p, 'title'), $o['content'] ?? self::tr($p, 'content'), (bool) ($o['is_active'] ?? $p->is_active));
    }

    public static function blogPost(BlogPost $p, array $o = []): BlogPostData
    {
        $published = array_key_exists('published_at', $o) ? $o['published_at'] : $p->published_at?->format('Y-m-d H:i:s');

        return new BlogPostData($o['slug'] ?? $p->slug, $o['title'] ?? self::tr($p, 'title'), $o['excerpt'] ?? self::tr($p, 'excerpt'), $o['content'] ?? self::tr($p, 'content'),
            $o['meta_description'] ?? self::tr($p, 'meta_description'), (bool) ($o['is_active'] ?? $p->is_active), $published);
    }

    public static function bundle(Bundle $b, array $o = []): BundleData
    {
        $items = $o['items'] ?? $b->items()->get()->map(fn ($i) => ['product_id' => (int) $i->product_id, 'variant_id' => (int) $i->product_variant_id, 'quantity' => (int) $i->quantity])->all();

        return new BundleData($o['name'] ?? self::tr($b, 'name'), $o['description'] ?? self::tr($b, 'description'), (string) ($o['price'] ?? $b->price),
            array_key_exists('starts_at', $o) ? $o['starts_at'] : $b->starts_at?->format('Y-m-d H:i:s'), array_key_exists('ends_at', $o) ? $o['ends_at'] : $b->ends_at?->format('Y-m-d H:i:s'),
            (int) ($o['sort_order'] ?? $b->sort_order), (bool) ($o['is_active'] ?? $b->is_active), $items, null, false, $o['slug'] ?? null);
    }

    public static function customPage(CustomPage $p, array $o = []): CustomPageData
    {
        return new CustomPageData($o['slug'] ?? $p->slug, $o['title'] ?? self::tr($p, 'title'), (array) $p->filters, $p->products()->pluck('products.id')->map(fn ($v) => (int) $v)->all(), (bool) $p->is_active);
    }
}
