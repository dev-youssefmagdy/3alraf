<?php

namespace App\Services\Storefront;

use App\DTOs\Storefront\CatalogFilter;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Services\Lists\ProductListResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Storefront listing / search over the active products (with a price), plus the facets that drive the filter sidebar. */
class CatalogSearch
{
    private const STOPWORDS = ['about', 'are', 'com', 'for', 'from', 'how', 'that', 'the', 'this', 'was', 'what', 'when', 'where', 'who', 'will', 'with', 'und', 'the', 'www'];

    public function __construct(private ProductListResolver $lists, private CardPresenter $cards) {}

    /** Base scope: sellable products in a category subtree (or everywhere) matching the text query. */
    public function scope(?Category $category = null, ?string $q = null): Builder
    {
        $query = Product::query()->where('is_active', true)->whereNotNull('price');
        if ($category) {
            $ids = Category::query()->where('is_active', true)->where(fn ($c) => $c->where('path', $category->path)->orWhere('path', 'like', $category->path.'/%'))->pluck('id');
            $query->whereHas('categories', fn ($c) => $c->whereIn('categories.id', $ids));
        }
        if ($q !== null) {
            $this->matchText($query, $q);
        }

        return $query;
    }

    /**
     * Name search on the generated `name_ar` / `name_en` columns (FULLTEXT, boolean-mode prefix per word). Words shorter than
     * innodb_ft_min_token_size or InnoDB stopwords cannot be matched by FULLTEXT, so those queries use LIKE on the same columns.
     * SKU / barcode match by prefix.
     */
    private function matchText(Builder $p, string $q): void
    {
        $q = mb_strtolower(trim($q));
        $words = preg_split('/[^\p{L}\p{N}]+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_slice($words, 0, 6);
        $esc = fn (string $v) => str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $v);
        $ft = $words && ! array_filter($words, fn ($w) => mb_strlen($w) < 3 || in_array($w, self::STOPWORDS, true));
        $p->where(function ($w) use ($q, $words, $esc, $ft) {
            if ($ft) {
                $boolean = implode(' ', array_map(fn ($x) => '+'.$x.'*', $words));
                $w->whereRaw('MATCH(name_ar) AGAINST (? IN BOOLEAN MODE)', [$boolean])->orWhereRaw('MATCH(name_en) AGAINST (? IN BOOLEAN MODE)', [$boolean]);
            } else {
                $w->where('name_ar', 'like', '%'.$esc($q).'%')->orWhere('name_en', 'like', '%'.$esc($q).'%');
            }
            $w->orWhere('sku', 'like', $esc($q).'%')->orWhere('barcode', 'like', $esc($q).'%');
        });
    }

    public function paginate(Builder $scope, CatalogFilter $f): LengthAwarePaginator
    {
        $q = clone $scope;
        if ($f->brands) {
            $q->whereIn('brand_id', $f->brands);
        }
        if ($f->values) { // OR within one attribute, AND across attributes
            $byAttr = DB::table('attribute_values')->whereIn('id', $f->values)->get(['id', 'attribute_id'])->groupBy('attribute_id');
            foreach ($byAttr as $ids) {
                $q->whereHas('variants.values', fn ($v) => $v->whereIn('attribute_values.id', $ids->pluck('id')->all()));
            }
        }
        if ($f->tags) {
            $q->whereHas('tags', fn ($t) => $t->whereIn('tags.id', $f->tags));
        }
        if ($f->priceMin !== null) {
            $q->whereRaw('COALESCE(sale_price, price) >= ?', [$f->priceMin]);
        }
        if ($f->priceMax !== null) {
            $q->whereRaw('COALESCE(sale_price, price) <= ?', [$f->priceMax]);
        }
        match ($f->sort) {
            'price_asc' => $q->orderByRaw('COALESCE(sale_price, price) asc'),
            'price_desc' => $q->orderByRaw('COALESCE(sale_price, price) desc'),
            'best_selling' => ($ids = $this->lists->soldRanking()) ? $q->orderByRaw('FIELD(id, '.implode(',', array_reverse($ids)).') DESC') : null,
            'top_rated' => ($ids = $this->lists->reviewRanking()) ? $q->orderByRaw('FIELD(id, '.implode(',', array_reverse($ids)).') DESC') : null,
            default => null,
        };
        $page = $q->with('images')->orderByDesc('id')->paginate($f->perPage)->withQueryString();
        $this->cards->decorate($page->getCollection());

        return $page;
    }

    /** Filter sidebar data computed over the unfiltered scope. */
    public function facets(Builder $scope): array
    {
        $productIds = (clone $scope)->pluck('id');
        if ($productIds->isEmpty()) {
            return ['brands' => collect(), 'tags' => collect(), 'attributes' => collect(), 'price' => ['min' => 0, 'max' => 0]];
        }
        $brandIds = DB::table('products')->whereIn('id', $productIds)->whereNotNull('brand_id')->pluck('brand_id');
        $valueRows = DB::table('product_variants as v')->join('product_variant_values as pvv', 'pvv.product_variant_id', '=', 'v.id')
            ->whereIn('v.product_id', $productIds)->whereNull('v.deleted_at')->where('v.is_active', true)->distinct()->pluck('pvv.attribute_value_id');
        $attributes = Attribute::query()->with(['values' => fn ($q) => $q->whereIn('id', $valueRows)->orderBy('sort_order')])->orderBy('sort_order')->get()->filter(fn ($a) => $a->values->isNotEmpty())->values();
        $price = (clone $scope)->selectRaw('MIN(COALESCE(sale_price, price)) mn, MAX(COALESCE(sale_price, price)) mx')->first();

        return [
            'tags' => Tag::query()->where('is_active', true)->whereIn('id', DB::table('product_tag')->whereIn('product_id', $productIds)->select('tag_id'))->orderBy('sort_order')->get(),
            'brands' => Brand::query()->whereIn('id', $brandIds)->where('is_active', true)->orderBy('sort_order')->get(),
            'attributes' => $attributes,
            'price' => ['min' => (int) floor((float) $price->mn), 'max' => (int) ceil((float) $price->mx)],
        ];
    }

    /** Header autocomplete: a few products + matching categories. */
    public function suggest(string $q): array
    {
        $items = $this->scope(null, $q)->with('images')->limit(6)->get();
        $this->cards->decorate($items);
        $needle = mb_strtolower($q);
        $cats = Category::query()->where('is_active', true)->get()->filter(fn ($c) => str_contains(mb_strtolower(implode(' ', $c->getTranslations('name'))), $needle))->take(3);

        return [
            'products' => $items->map(fn ($p) => ['name' => $p->name, 'image' => $p->primaryImageUrl(), 'price' => $p->display['price'],
                'url' => route('storefront.product.show', ['locale' => app()->getLocale(), 'slug' => $p->slug])])->values()->all(),
            'categories' => $cats->map(fn ($c) => ['name' => $c->name, 'url' => route('storefront.category.show', ['locale' => app()->getLocale(), 'slug' => $c->slug])])->values()->all(),
        ];
    }
}
