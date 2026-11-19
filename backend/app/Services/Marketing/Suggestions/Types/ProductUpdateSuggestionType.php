<?php

namespace App\Services\Marketing\Suggestions\Types;

use App\Enums\Marketing\SuggestionTypeKey;
use App\Models\Brand;
use App\Models\Category;
use App\Models\MarketingSuggestion;
use App\Models\MarketingSuggestionExecution;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Marketing\Suggestions\ApplyResult;
use App\Services\Marketing\Suggestions\SuggestionImages;
use App\Services\ProductService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Update an EXISTING product's catalog record (target: product, required). Any group, alone or together:
 * - core: catalog name / description (per locale; other locales kept), brand, categories (the whole set), status (active | hidden),
 * - pricing: price, sale_price (or remove_sale_price), and price / sale_price of existing variants. Cost is NOT editable: the listing cost
 *   is the weighted-average purchase cost (docs/CALCULATIONS.md), so the margin check uses the current cost. Selling below cost is refused.
 * - images: add by URL (downloaded once at create / edit time, SuggestionImages, SSRF-safe, sha256 in the payload), remove by image id,
 *   choose the main image (an existing id or the index of an added one).
 * Applied through ProductService (updateListing / addImages / detachImages / setMainImage), never raw writes. Removed images keep their
 * files, so rollback restores every touched value, re-attaches removed images and deletes the ones this suggestion added.
 * Unlike the `product` type (storefront wording overlay, tags, pins), this changes the catalog itself: approving needs ai_products.approve.
 */
class ProductUpdateSuggestionType extends AbstractSuggestionType
{
    public const MAX_IMAGES = 10;

    public const MAX_VARIANTS = 100;

    protected array $targetModels = ['product' => Product::class];

    public function __construct(private ProductService $products, private SuggestionImages $images) {}

    public function key(): SuggestionTypeKey
    {
        return SuggestionTypeKey::PRODUCT_UPDATE;
    }

    public function targetRequired(?array $payload = null): bool
    {
        return true;
    }

    /** Changes catalog prices / status / images: approve and apply also need this (same as new_product). */
    public function approvePermission(): ?string
    {
        return 'ai_products.approve';
    }

    public function rules(?string $targetType = null): array
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:999999999'];

        return $this->localeRules('name', 200) + $this->localeRules('description', 20000) + [
            'payload' => ['required', 'array'],
            'payload.brand_id' => ['nullable', 'integer', 'min:1'],
            'payload.category_ids' => ['nullable', 'array', 'min:1', 'max:20'], 'payload.category_ids.*' => ['integer', 'min:1'],
            'payload.status' => ['nullable', 'in:active,hidden'],
            'payload.price' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'payload.sale_price' => $money,
            'payload.remove_sale_price' => ['nullable', 'boolean'],
            'payload.variants' => ['nullable', 'array', 'max:'.self::MAX_VARIANTS],
            'payload.variants.*' => ['array'],
            'payload.variants.*.variant_id' => ['required', 'integer', 'min:1'],
            'payload.variants.*.price' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'payload.variants.*.sale_price' => $money,
            'payload.variants.*.remove_sale_price' => ['nullable', 'boolean'],
            'payload.images_add' => ['nullable', 'array', 'max:'.self::MAX_IMAGES],
            'payload.images_add.*' => ['array'],
            'payload.images_add.*.url' => ['required', 'string', 'max:2048', 'regex:#^https?://#i'],
            // filled by normalize() (staged download); accepted back unchanged when a reviewer edits the payload
            'payload.images_add.*.sha256' => ['nullable', 'string'], 'payload.images_add.*.mime' => ['nullable', 'string'], 'payload.images_add.*.width' => ['nullable', 'integer'],
            'payload.images_add.*.height' => ['nullable', 'integer'], 'payload.images_add.*.bytes' => ['nullable', 'integer'], 'payload.images_add.*.error' => ['nullable', 'string'],
            'payload.images_remove' => ['nullable', 'array', 'max:50'], 'payload.images_remove.*' => ['integer', 'min:1'],
            'payload.main_image_id' => ['nullable', 'integer', 'min:1'],
            'payload.main_image_index' => ['nullable', 'integer', 'min:0', 'max:'.(self::MAX_IMAGES - 1)],
        ];
    }

    public function normalize(array $payload, ?string $targetType = null): array
    {
        $int = fn ($v) => ($v === null || $v === '') ? null : (int) $v;
        $true = fn ($v) => filter_var($v, FILTER_VALIDATE_BOOL) ? true : null;

        $variants = [];
        foreach ((array) ($payload['variants'] ?? []) as $v) {
            $row = array_filter(['variant_id' => $int($v['variant_id'] ?? null), 'price' => $this->money($v['price'] ?? null),
                'sale_price' => $this->money($v['sale_price'] ?? null), 'remove_sale_price' => $true($v['remove_sale_price'] ?? null)], fn ($x) => $x !== null);
            if (isset($row['variant_id']) && count($row) > 1) {
                $variants[$row['variant_id']] = $row;   // one row per variant (last wins)
            }
        }
        ksort($variants);

        $images = [];
        foreach ((array) ($payload['images_add'] ?? []) as $img) {
            $url = is_array($img) ? trim((string) ($img['url'] ?? '')) : '';
            $url !== '' && $images[] = $this->images->stage($url);
        }

        $out = array_filter([
            'name' => $this->plainLocales($payload['name'] ?? null),
            'description' => $this->htmlLocales($payload['description'] ?? null),
            'brand_id' => $int($payload['brand_id'] ?? null),
            'category_ids' => $this->intList($payload['category_ids'] ?? []),
            'status' => in_array($payload['status'] ?? null, ['active', 'hidden'], true) ? $payload['status'] : null,
            'price' => $this->money($payload['price'] ?? null),
            'sale_price' => $this->money($payload['sale_price'] ?? null),
            'remove_sale_price' => $true($payload['remove_sale_price'] ?? null),
            'variants' => array_values($variants),
            'images_add' => $images,
            'images_remove' => $this->intList($payload['images_remove'] ?? []),
            'main_image_id' => $int($payload['main_image_id'] ?? null),
            'main_image_index' => $int($payload['main_image_index'] ?? null),
        ], fn ($v) => $v !== null && $v !== []);
        ksort($out);

        return $out;
    }

    public function check(array $payload, ?Model $target, ?MarketingSuggestion $s = null): array
    {
        $errors = [];
        $warnings = [];
        if ($payload === []) {
            $errors['payload'] = __('marketing.suggestions.errors.empty_change');
        }
        $product = $target instanceof Product ? $target : null;

        if (isset($payload['name']) && ! array_filter($payload['name'])) {
            $errors['payload.name'] = __('validation.required', ['attribute' => $this->label('name')]);
        }
        if ($product) {
            $overlay = (array) ($product->content ?? []);
            foreach (['name', 'description'] as $f) {
                foreach (array_keys((array) ($payload[$f] ?? [])) as $l) {
                    if (is_string($overlay[$f][$l] ?? null) && $overlay[$f][$l] !== '') {
                        $warnings[] = __('marketing.suggestions.warnings.product_wording_overlay', ['field' => $this->label($f), 'locale' => $l]);
                    }
                }
            }
        }

        // references
        if (isset($payload['brand_id']) && ! Brand::query()->whereKey($payload['brand_id'])->exists()) {
            $errors['payload.brand_id'] = __('validation.exists', ['attribute' => $this->label('brand_id')]);
        }
        $cats = (array) ($payload['category_ids'] ?? []);
        if ($cats && Category::query()->whereKey($cats)->count() !== count($cats)) {
            $errors['payload.category_ids'] = __('validation.exists', ['attribute' => $this->label('category_ids')]);
        }

        // variants: existing live variants of THIS product only
        $live = $product ? $product->variants()->get()->keyBy('id') : collect();
        foreach ((array) ($payload['variants'] ?? []) as $i => $v) {
            if (! $live->has($v['variant_id'])) {
                $errors["payload.variants.$i.variant_id"] = __('marketing.suggestions.errors.variant_not_of_product', ['id' => $v['variant_id']]);
            } elseif (isset($v['sale_price']) && ! empty($v['remove_sale_price'])) {
                $errors["payload.variants.$i.remove_sale_price"] = __('marketing.suggestions.errors.sale_price_conflict');
            }
        }

        // pricing
        if (isset($payload['sale_price']) && ! empty($payload['remove_sale_price'])) {
            $errors['payload.remove_sale_price'] = __('marketing.suggestions.errors.sale_price_conflict');
        }
        if ($product && ! $errors) {
            $this->checkPrices($payload, $product, $live, $errors, $warnings);
        }

        // status
        if (($payload['status'] ?? null) === 'active' && $product) {
            if ($product->is_draft) {
                $errors['payload.status'] = __('marketing.suggestions.errors.product_is_draft');
            } elseif ($this->newPrice($payload, $product) === null) {
                $errors['payload.status'] = __('marketing.suggestions.errors.product_without_price');
            } elseif (ProductService::isDraftSku($product->sku)) {
                $errors['payload.status'] = __('marketing.suggestions.errors.product_without_sku');
            }
        }

        // images
        $added = (array) ($payload['images_add'] ?? []);
        foreach ($added as $n => $img) {
            if (isset($img['error'])) {
                $errors["payload.images_add.$n.url"] = __('marketing.suggestions.errors.image_fetch', ['url' => Str::limit((string) $img['url'], 80), 'reason' => __('marketing.suggestions.image_errors.'.$img['error'])]);
            } elseif (! $this->images->exists($img)) {
                $errors["payload.images_add.$n.url"] = __('marketing.suggestions.errors.image_missing');
            }
        }
        $have = $product ? $product->images()->pluck('id')->map(fn ($v) => (int) $v)->all() : [];
        $remove = (array) ($payload['images_remove'] ?? []);
        if ($product && array_diff($remove, $have)) {
            $errors['payload.images_remove'] = __('marketing.suggestions.errors.image_not_of_product', ['ids' => implode(', ', array_diff($remove, $have))]);
        }
        if (isset($payload['main_image_id'], $payload['main_image_index'])) {
            $errors['payload.main_image_index'] = __('marketing.suggestions.errors.main_image_conflict');
        } elseif (isset($payload['main_image_id']) && $product && (! in_array($payload['main_image_id'], $have, true) || in_array($payload['main_image_id'], $remove, true))) {
            $errors['payload.main_image_id'] = __('marketing.suggestions.errors.image_not_of_product', ['ids' => $payload['main_image_id']]);
        } elseif (isset($payload['main_image_index']) && $payload['main_image_index'] >= count($added)) {
            $errors['payload.main_image_index'] = __('marketing.suggestions.errors.variant_image_index', ['n' => count($added)]);
        }
        if ($product && ($added || $remove) && count(array_diff($have, $remove)) + count($added) === 0) {
            $warnings[] = __('marketing.suggestions.warnings.product_no_images_left');
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /** Touched values only (unrelated edits of the product never make the suggestion stale). */
    public function snapshot(?Model $target, array $payload): ?array
    {
        if (! $target instanceof Product) {
            return null;
        }
        $snap = [];
        foreach (['name', 'description'] as $f) {
            foreach (array_keys((array) ($payload[$f] ?? [])) as $l) {
                $snap[$f][$l] = $target->getTranslation($f, $l, false) ?: null;
            }
        }
        isset($payload['brand_id']) && $snap['brand_id'] = $target->brand_id;
        isset($payload['status']) && $snap['is_active'] = (bool) $target->is_active;
        if ($this->touchesPrice($payload)) {
            $snap['price'] = $target->price;
            $snap['sale_price'] = $target->sale_price;
            $snap['variants'] = $target->variants()->get()->mapWithKeys(fn ($v) => [$v->id => [$v->price, $v->sale_price]])->all();
        }
        if (isset($payload['category_ids'])) {
            $snap['category_ids'] = $this->categoryIds($target);
        }
        if ($this->touchesImages($payload)) {
            $snap['images'] = $target->images()->pluck('id')->map(fn ($v) => (int) $v)->all();
        }

        return $snap;
    }

    public function compare(MarketingSuggestion $s, ?Model $target): array
    {
        $p = (array) $s->payload;
        $product = $target instanceof Product ? $target : null;
        $rows = [];
        foreach (['name', 'description'] as $f) {
            foreach ((array) ($p[$f] ?? []) as $l => $v) {
                $rows[] = $this->row($f.'.'.$l, $product ? ($product->getTranslation($f, $l, false) ?: null) : null, $v, $this->label($f).' ('.$l.')') + ['html' => $f === 'description'];
            }
        }
        if (isset($p['brand_id'])) {
            $rows[] = $this->row('brand_id', $product?->brand_id ? $this->brandLabel((int) $product->brand_id) : null, $this->brandLabel((int) $p['brand_id']));
        }
        if (isset($p['category_ids'])) {
            $rows[] = $this->row('category_ids', $product ? $this->categoryNames($this->categoryIds($product)) : null, $this->categoryNames($p['category_ids']));
        }
        if (isset($p['status'])) {
            $rows[] = $this->row('status', $product ? ($product->is_active ? 'active' : 'hidden') : null, $p['status']);
        }
        if (isset($p['price'])) {
            $rows[] = $this->row('price', $product?->price, $p['price']);
        }
        if (isset($p['sale_price']) || ! empty($p['remove_sale_price'])) {
            $rows[] = $this->row('sale_price', $product?->sale_price, $p['sale_price'] ?? null);
        }
        if (isset($p['variants']) && $product) {
            $live = $product->variants()->with('values')->get()->keyBy('id');
            foreach ($p['variants'] as $v) {
                $cur = $live->get($v['variant_id']);
                $name = $cur ? $cur->values->map(fn ($x) => $x->value)->implode(' / ') : '#'.$v['variant_id'];
                $rows[] = $this->row('variants.'.$v['variant_id'], $cur ? $this->priceLine($cur->price, $cur->sale_price) : null,
                    $this->priceLine($v['price'] ?? $cur?->price, ! empty($v['remove_sale_price']) ? null : ($v['sale_price'] ?? $cur?->sale_price)), $this->label('variants').': '.$name.' ('.($cur->sku ?? '').')');
            }
        }
        if ($this->touchesImages($p)) {
            $gallery = $product ? $product->images()->get() : collect();
            $current = $gallery->map(fn ($i) => ['src' => $i->thumb_url, 'url' => $i->url, 'size' => '#'.$i->id])->values()->all();
            $kept = $gallery->reject(fn ($i) => in_array($i->id, (array) ($p['images_remove'] ?? []), true))
                ->map(fn ($i) => ['id' => $i->id, 'src' => $i->thumb_url, 'url' => $i->url, 'size' => '#'.$i->id])->values()->all();
            $new = array_map(fn ($img) => isset($img['sha256'])
                ? ['src' => route('marketer.suggestions.image', ['sha' => $img['sha256']]), 'url' => $img['url'], 'size' => ($img['width'] ?? '?').'×'.($img['height'] ?? '?')]
                : ['src' => null, 'url' => $img['url'] ?? '', 'size' => __('marketing.suggestions.image_errors.'.($img['error'] ?? 'missing'))], (array) ($p['images_add'] ?? []));
            $proposed = [...$kept, ...$new];
            $main = isset($p['main_image_id']) ? array_search($p['main_image_id'], array_column($kept, 'id'), true)
                : (isset($p['main_image_index']) ? count($kept) + $p['main_image_index'] : false);
            if ($main !== false && isset($proposed[$main])) {
                array_unshift($proposed, ...array_splice($proposed, $main, 1));
            }
            $rows[] = $this->row('images', $current ?: null, array_map(fn ($i) => array_diff_key($i, ['id' => 1]), $proposed) ?: null) + ['images' => true];
        }

        return $rows;
    }

    public function apply(MarketingSuggestion $s, ?Model $target, array $options, ?int $adminId): ApplyResult
    {
        /** @var Product $target */
        $p = (array) $s->payload;
        $this->ensure($check = $this->check($p, $target, $s));
        $before = $this->state($target, $p);

        $attrs = [];
        foreach (['name', 'description'] as $f) {
            if (isset($p[$f])) {
                $attrs[$f] = array_merge($this->translations($target, $f), $p[$f]);   // only the proposed locales change
            }
        }
        isset($p['brand_id']) && $attrs['brand_id'] = $p['brand_id'];
        isset($p['status']) && $attrs['is_active'] = $p['status'] === 'active';
        isset($p['price']) && $attrs['price'] = $p['price'];
        if (isset($p['sale_price']) || ! empty($p['remove_sale_price'])) {
            $attrs['sale_price'] = $p['sale_price'] ?? null;
        }
        $variants = [];
        foreach ((array) ($p['variants'] ?? []) as $v) {
            $row = [];
            isset($v['price']) && $row['price'] = $v['price'];
            (isset($v['sale_price']) || ! empty($v['remove_sale_price'])) && $row['sale_price'] = $v['sale_price'] ?? null;
            $row && $variants[$v['variant_id']] = $row;
        }
        if ($attrs || isset($p['category_ids']) || $variants) {
            $target = $this->products->updateListing($target, $attrs, $p['category_ids'] ?? null, $variants);
        }

        $images = [];
        if ($this->touchesImages($p)) {
            if ($remove = (array) ($p['images_remove'] ?? [])) {
                $images['removed'] = $this->products->detachImages($target, $remove);   // files kept for rollback
            }
            $slug = $target->slug ?: 'product-'.$target->id;
            $files = array_map(fn ($img) => $this->images->toUploadedFile($img, $slug), (array) ($p['images_add'] ?? []));
            $images['added'] = $files ? $this->products->addImages($target, $files) : [];
            $main = $p['main_image_id'] ?? (isset($p['main_image_index']) ? ($images['added'][$p['main_image_index']] ?? null) : null);
            $main && $this->products->setMainImage($target, (int) $main);
        }

        $after = $this->state($target->refresh(), $p);
        $images && $after['image_changes'] = $images;

        return new ApplyResult($before, $after, 'product', $target->id, $check['warnings']);
    }

    public function supportsRollback(): bool
    {
        return true;
    }

    public function rollback(MarketingSuggestion $s, MarketingSuggestionExecution $exec, ?int $adminId): array
    {
        $before = (array) $exec->before;
        $after = (array) $exec->after;
        $product = Product::query()->findOrFail($s->target_entity_id);

        $attrs = array_intersect_key((array) ($before['attrs'] ?? []), array_flip(['name', 'description', 'brand_id', 'is_active', 'price', 'sale_price']));
        $variants = (array) ($before['variants'] ?? []);
        if ($attrs || array_key_exists('category_ids', $before) || $variants) {
            $product = $this->products->updateListing($product, $attrs, array_key_exists('category_ids', $before) ? (array) $before['category_ids'] : null, $variants);
        }

        $changes = (array) ($after['image_changes'] ?? []);
        if ($added = (array) ($changes['added'] ?? [])) {
            $this->products->removeImages($product, $added);
        }
        if ($removed = (array) ($changes['removed'] ?? [])) {
            $this->products->restoreImages($product, $removed);
        }
        if (isset($before['media_order'])) {
            $this->products->restoreMediaOrder($product, (array) $before['media_order']);
        }

        return $this->state($product->refresh(), (array) $s->payload);
    }

    public function editFields(?string $targetType = null): array
    {
        return [
            $this->field('payload.name', 'locales-text'),
            $this->field('payload.description', 'locales-html'),
            $this->field('payload.brand_id', 'number'),
            $this->field('payload.category_ids', 'id-list'),
            $this->field('payload.status', 'select', null, ['options' => ['active', 'hidden'], 'nullable' => true]),
            $this->field('payload.price', 'number', null, ['step' => '0.01']),
            $this->field('payload.sale_price', 'number', null, ['step' => '0.01']),
            $this->field('payload.remove_sale_price', 'bool'),
            $this->field('payload.variants', 'json', null, ['hint' => 'variant_prices_hint']),
            $this->field('payload.images_add', 'json', null, ['hint' => 'images_hint']),
            $this->field('payload.images_remove', 'id-list'),
            $this->field('payload.main_image_id', 'number'),
            $this->field('payload.main_image_index', 'number'),
        ];
    }

    // ------------------------------------------------------------------ helpers

    private function touchesPrice(array $p): bool
    {
        return isset($p['price']) || isset($p['sale_price']) || ! empty($p['remove_sale_price']) || isset($p['variants']);
    }

    private function touchesImages(array $p): bool
    {
        return isset($p['images_add']) || isset($p['images_remove']) || isset($p['main_image_id']) || isset($p['main_image_index']);
    }

    private function newPrice(array $p, Product $product): ?string
    {
        return $p['price'] ?? ($product->price === null ? null : (string) $product->price);
    }

    private function newSale(array $p, Product $product): ?string
    {
        return ! empty($p['remove_sale_price']) ? null : ($p['sale_price'] ?? ($product->sale_price === null ? null : (string) $product->sale_price));
    }

    /**
     * sale ≤ price and the margin of every sellable line the proposal changes: the product itself (simple) or each live variant (variable),
     * whose price falls back to the product's (same rule as ProductVariant::effectivePrice). Cost = variant cost, else product cost.
     */
    private function checkPrices(array $p, Product $product, $live, array &$errors, array &$warnings): void
    {
        if (! $this->touchesPrice($p)) {
            return;
        }
        $price = $this->newPrice($p, $product);
        $sale = $this->newSale($p, $product);
        if ($sale !== null && $price === null) {
            $errors['payload.price'] = __('validation.required_with', ['attribute' => $this->label('price'), 'values' => $this->label('sale_price')]);

            return;
        }
        if ($sale !== null && Money::cmp($sale, $price) > 0) {
            $errors['payload.sale_price'] = __('validation.lte.numeric', ['attribute' => $this->label('sale_price'), 'value' => $this->label('price')]);

            return;
        }
        $cost = $product->cost === null ? null : (string) $product->cost;
        if (! $product->isVariable() || $live->isEmpty()) {
            $this->checkMargin($price, $sale, $cost, 'payload', $errors, $warnings);

            return;
        }
        $rows = collect((array) ($p['variants'] ?? []))->keyBy('variant_id');
        $productTouched = isset($p['price']) || isset($p['sale_price']) || ! empty($p['remove_sale_price']);
        foreach ($live as $v) {
            $row = $rows->get($v->id);
            if (! $row && ! ($productTouched && $v->price === null)) {
                continue;   // this variant's selling price does not change
            }
            $i = $row ? array_search($v->id, array_column((array) $p['variants'], 'variant_id'), true) : null;
            $path = $row ? "payload.variants.$i" : 'payload';
            $vPrice = $row['price'] ?? ($v->price === null ? null : (string) $v->price);
            $vSale = ($row && ! empty($row['remove_sale_price'])) ? null : ($row['sale_price'] ?? ($v->sale_price === null ? null : (string) $v->sale_price));
            if ($vPrice === null) {   // inherits the product's price and (when it has none of its own) sale price
                $vPrice = $price;
                $vSale ??= $sale;
            }
            if ($vPrice === null) {
                continue;
            }
            if ($vSale !== null && Money::cmp($vSale, $vPrice) > 0) {
                $errors[$path.'.sale_price'] = __('validation.lte.numeric', ['attribute' => $this->label('sale_price'), 'value' => $this->label('price')]);

                continue;
            }
            $this->checkMargin($vPrice, $vSale, $v->cost === null ? $cost : (string) $v->cost, $path, $errors, $warnings);
        }
        $warnings = array_values(array_unique($warnings));
    }

    /** Selling below cost is refused; below the configured minimum margin, or with no cost, is a warning (same rule as new_product). */
    private function checkMargin(?string $price, ?string $sale, ?string $cost, string $path, array &$errors, array &$warnings): void
    {
        $sell = $sale ?? $price;
        if ($sell === null || Money::cmp($sell, '0') <= 0) {
            return;
        }
        if ($cost === null || Money::cmp($cost, '0') <= 0) {
            $warnings[] = __('marketing.suggestions.warnings.margin_unknown');

            return;
        }
        $margin = round(((float) $sell - (float) $cost) / (float) $sell * 100, 2);
        if ($margin < 0) {
            $errors[$path.'.'.($sale !== null ? 'sale_price' : 'price')] = __('marketing.suggestions.errors.negative_margin', ['margin' => $margin]);
        } elseif ($margin < (float) config('marketing.suggestions.min_margin_percent', 10)) {
            $warnings[] = __('marketing.suggestions.warnings.low_margin', ['margin' => $margin]);
        }
    }

    private function categoryIds(Product $p): array
    {
        $ids = $p->categories()->pluck('categories.id')->map(fn ($v) => (int) $v)->all();
        sort($ids);

        return $ids;
    }

    private function categoryNames(array $ids): array
    {
        return Category::query()->whereKey($ids)->get()->map(fn ($c) => $c->name.' #'.$c->id)->values()->all();
    }

    private function brandLabel(int $id): string
    {
        $b = Brand::query()->find($id);

        return $b ? $b->name.' #'.$id : '#'.$id;
    }

    private function priceLine(mixed $price, mixed $sale): string
    {
        return ($price === null ? '—' : (string) $price).($sale === null ? '' : ' → '.$sale);
    }

    /** Full before/after record of what this payload touches (rollback source). */
    private function state(Product $p, array $payload): array
    {
        $s = ['attrs' => []];
        foreach (['name', 'description'] as $f) {
            isset($payload[$f]) && $s['attrs'][$f] = $this->translations($p, $f);
        }
        isset($payload['brand_id']) && $s['attrs']['brand_id'] = $p->brand_id;
        isset($payload['status']) && $s['attrs']['is_active'] = (bool) $p->is_active;
        if (isset($payload['price']) || isset($payload['sale_price']) || ! empty($payload['remove_sale_price'])) {
            $s['attrs']['price'] = $p->price === null ? null : (string) $p->price;
            $s['attrs']['sale_price'] = $p->sale_price === null ? null : (string) $p->sale_price;
        }
        if (isset($payload['variants'])) {
            $ids = array_column((array) $payload['variants'], 'variant_id');
            $s['variants'] = ProductVariant::query()->where('product_id', $p->id)->whereKey($ids)->get()
                ->mapWithKeys(fn ($v) => [$v->id => ['price' => $v->price === null ? null : (string) $v->price, 'sale_price' => $v->sale_price === null ? null : (string) $v->sale_price]])->all();
        }
        isset($payload['category_ids']) && $s['category_ids'] = $this->categoryIds($p);
        if ($this->touchesImages($payload)) {
            $s['media_order'] = $this->products->mediaOrder($p);
        }

        return $s;
    }
}
