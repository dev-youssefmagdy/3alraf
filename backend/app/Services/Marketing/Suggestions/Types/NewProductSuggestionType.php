<?php

namespace App\Services\Marketing\Suggestions\Types;

use App\DTOs\AttributeData;
use App\DTOs\ProductData;
use App\DTOs\ProductVariantData;
use App\Enums\AttributeType;
use App\Enums\Marketing\SuggestionTypeKey;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\MarketingSuggestion;
use App\Models\MarketingSuggestionExecution;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tag;
use App\Services\AttributeService;
use App\Services\BarcodeService;
use App\Services\GiftFinder\GiftFinderService;
use App\Services\Marketing\Suggestions\ApplyResult;
use App\Services\Marketing\Suggestions\SuggestionException;
use App\Services\Marketing\Suggestions\SuggestionImages;
use App\Services\ProductService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * A whole NEW product proposed through MCP: wording, SEO, SKU / barcode, brand, categories, tags, prices, weight (grams), gift tags,
 * images (by URL) and variants (each with optional SKU / prices / weight / image).
 *
 * - Variant values are existing attribute value ids, or `ref`s of values proposed in the same suggestion when the store lacks them:
 *   `new_attributes` (a whole new attribute with its values) and `new_values` (extra values of an existing attribute). They are created
 *   by the same approval, right before the product, in the same transaction (all or nothing).
 *
 * - Images are downloaded once at create / edit time (SuggestionImages, SSRF-safe) and the payload keeps their sha256, so approval covers
 *   the exact pictures the reviewer saw. A URL that cannot be fetched or is not an image refuses the suggestion.
 * - Apply creates the product through ProductService as a DRAFT (never active): the owner checks it in the product form and clicks
 *   "Save & publish". Prices proposed by Claude therefore never go live without a person.
 * - Rollback soft-deletes the product, only while it is still a draft (a published product may already be sold), and removes the attributes /
 *   values it created unless something else uses them by then.
 */
class NewProductSuggestionType extends AbstractSuggestionType
{
    public const MAX_IMAGES = 10;

    public const MAX_VARIANTS = 100;

    public const MAX_NEW_ATTRIBUTES = 10;

    public const MAX_NEW_VALUES = 100;

    /** A reference to a proposed value: starts with a letter, so it never looks like an id. */
    public const REF = '/^[a-z][a-z0-9_-]{0,49}$/';

    private const SCALARS = ['slug', 'sku', 'barcode', 'brand_id', 'price', 'sale_price', 'cost', 'weight', 'min_stock_alert_qty'];

    public function __construct(private ProductService $products, private SuggestionImages $images, private AttributeService $attributes) {}

    public function key(): SuggestionTypeKey
    {
        return SuggestionTypeKey::NEW_PRODUCT;
    }

    /** Creates products / attributes in the catalog: approve and apply also need this (marketer-owner has it; the preset `marketer` role does not). */
    public function approvePermission(): ?string
    {
        return 'ai_products.approve';
    }

    public function rules(?string $targetType = null): array
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:999999999'];

        return $this->localeRules('name', 200, true) + $this->localeRules('description', 20000) + $this->localeRules('meta_title', 255) + $this->localeRules('meta_description', 500) + [
            'payload' => ['required', 'array'],
            'payload.slug' => ['nullable', 'string', 'max:190', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'payload.sku' => ['nullable', 'string', 'max:100'],
            'payload.barcode' => ['nullable', 'digits:13'],
            'payload.brand_id' => ['nullable', 'integer', 'min:1'],
            'payload.category_ids' => ['nullable', 'array', 'max:20'], 'payload.category_ids.*' => ['integer', 'min:1'],
            'payload.tag_ids' => ['nullable', 'array', 'max:30'], 'payload.tag_ids.*' => ['integer', 'min:1'],
            'payload.price' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'payload.sale_price' => $money,
            'payload.cost' => $money,
            'payload.weight' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'payload.min_stock_alert_qty' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'payload.gift' => ['nullable', 'array'],
            'payload.gift.suitable' => ['required_with:payload.gift', 'boolean'],
            'payload.gift.recipients' => ['nullable', 'array'], 'payload.gift.recipients.*' => [Rule::in(GiftFinderService::RECIPIENTS)],
            'payload.gift.occasions' => ['nullable', 'array'], 'payload.gift.occasions.*' => [Rule::in(GiftFinderService::OCCASIONS)],
            'payload.images' => ['nullable', 'array', 'max:'.self::MAX_IMAGES],
            'payload.images.*' => ['array'],
            'payload.images.*.url' => ['required', 'string', 'max:2048', 'regex:#^https?://#i'],
            // read-only details the store adds after downloading; accepted (and ignored) so an edited payload can be sent back as is
            'payload.images.*.sha256' => ['nullable', 'string'], 'payload.images.*.mime' => ['nullable', 'string'], 'payload.images.*.width' => ['nullable', 'integer'],
            'payload.images.*.height' => ['nullable', 'integer'], 'payload.images.*.bytes' => ['nullable', 'integer'], 'payload.images.*.error' => ['nullable', 'string'],
            'payload.variants' => ['nullable', 'array', 'max:'.self::MAX_VARIANTS],
            'payload.variants.*' => ['array'],
            'payload.variants.*.values' => ['required', 'array', 'min:1', 'max:10'],
            'payload.variants.*.values.*' => ['required', 'regex:/^([1-9][0-9]*|[a-z][a-z0-9_-]{0,49})$/i'],   // value id, or the ref of a proposed value
            'payload.variants.*.sku' => ['nullable', 'string', 'max:100'],
            'payload.variants.*.price' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'payload.variants.*.sale_price' => $money,
            'payload.variants.*.cost' => $money,
            'payload.variants.*.weight' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'payload.variants.*.min_stock_alert_qty' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'payload.variants.*.image' => ['nullable', 'integer', 'min:0', 'max:'.(self::MAX_IMAGES - 1)],
            'payload.variants.*.is_active' => ['nullable', 'boolean'],
            // attributes / values the store lacks, created by the same approval
            'payload.new_attributes' => ['nullable', 'array', 'max:'.self::MAX_NEW_ATTRIBUTES],
            'payload.new_attributes.*' => ['array'],
            'payload.new_attributes.*.ref' => ['required', 'string', 'regex:'.self::REF.'i'],
            'payload.new_attributes.*.name' => ['required', 'array'],
            'payload.new_attributes.*.code' => ['nullable', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'payload.new_attributes.*.type' => ['nullable', Rule::enum(AttributeType::class)],
            'payload.new_attributes.*.values' => ['required', 'array', 'min:1', 'max:'.self::MAX_NEW_VALUES],
            'payload.new_attributes.*.values.*' => ['array'],
            'payload.new_attributes.*.values.*.ref' => ['required', 'string', 'regex:'.self::REF.'i'],
            'payload.new_attributes.*.values.*.value' => ['required', 'array'],
            'payload.new_attributes.*.values.*.color_hex' => ['nullable', 'string', 'regex:/^#?[0-9a-fA-F]{6}$/'],
            'payload.new_values' => ['nullable', 'array', 'max:'.self::MAX_NEW_VALUES],
            'payload.new_values.*' => ['array'],
            'payload.new_values.*.attribute_id' => ['required', 'integer', 'min:1'],
            'payload.new_values.*.ref' => ['required', 'string', 'regex:'.self::REF.'i'],
            'payload.new_values.*.value' => ['required', 'array'],
            'payload.new_values.*.color_hex' => ['nullable', 'string', 'regex:/^#?[0-9a-fA-F]{6}$/'],
        ] + $this->proposedLocaleRules();
    }

    public function normalize(array $payload, ?string $targetType = null): array
    {
        $int = fn ($v) => ($v === null || $v === '') ? null : (int) $v;
        $str = fn ($v, int $max) => ($s = $this->plain($v, $max)) === null ? null : $s;

        $images = [];
        foreach (array_slice((array) ($payload['images'] ?? []), 0, self::MAX_IMAGES) as $img) {
            $url = is_array($img) ? trim((string) ($img['url'] ?? '')) : '';
            if ($url !== '' && ! in_array($url, array_column($images, 'url'), true)) {
                $images[] = $this->images->stage($url);
            }
        }

        $variants = [];
        foreach (array_slice((array) ($payload['variants'] ?? []), 0, self::MAX_VARIANTS) as $v) {
            $v = (array) $v;
            $variants[] = array_filter([
                'values' => $this->valueList($v['values'] ?? []),
                'sku' => $str($v['sku'] ?? null, 100),
                'price' => $this->money($v['price'] ?? null), 'sale_price' => $this->money($v['sale_price'] ?? null), 'cost' => $this->money($v['cost'] ?? null),
                'weight' => $int($v['weight'] ?? null), 'min_stock_alert_qty' => $int($v['min_stock_alert_qty'] ?? null),
                'image' => $int($v['image'] ?? null),
                'is_active' => isset($v['is_active']) ? filter_var($v['is_active'], FILTER_VALIDATE_BOOL) : true,
            ], fn ($x) => $x !== null && $x !== []);
        }

        $gift = null;
        if (is_array($payload['gift'] ?? null)) {
            $g = $payload['gift'];
            $gift = ['suitable' => filter_var($g['suitable'] ?? false, FILTER_VALIDATE_BOOL),
                'recipients' => array_values(array_unique(array_intersect((array) ($g['recipients'] ?? []), GiftFinderService::RECIPIENTS))),
                'occasions' => array_values(array_unique(array_intersect((array) ($g['occasions'] ?? []), GiftFinderService::OCCASIONS)))];
        }

        $slug = isset($payload['slug']) && $payload['slug'] !== '' ? Str::lower(trim((string) $payload['slug'])) : null;

        $newAttributes = [];
        foreach (array_slice((array) ($payload['new_attributes'] ?? []), 0, self::MAX_NEW_ATTRIBUTES) as $a) {
            $a = (array) $a;
            $name = $this->plainLocales($a['name'] ?? null);
            $type = AttributeType::tryFrom((string) ($a['type'] ?? ''))?->value ?? AttributeType::SELECT->value;
            $code = Str::lower(trim((string) ($a['code'] ?? ''))) ?: Str::slug((string) ($name['en'] ?? collect($name)->first() ?? ''), '_');
            $newAttributes[] = array_filter([
                'ref' => $this->ref($a['ref'] ?? null), 'name' => $name, 'code' => $code !== '' ? $code : null, 'type' => $type,
                'values' => array_map(fn ($v) => $this->newValue((array) $v, $type === 'color'), array_slice((array) ($a['values'] ?? []), 0, self::MAX_NEW_VALUES)),
            ], fn ($x) => $x !== null && $x !== []);
        }
        $newValues = [];
        foreach (array_slice((array) ($payload['new_values'] ?? []), 0, self::MAX_NEW_VALUES) as $v) {
            $v = (array) $v;
            $newValues[] = ['attribute_id' => (int) ($v['attribute_id'] ?? 0)] + $this->newValue($v, true);
        }

        return array_filter([
            'type' => $variants ? 'variable' : 'simple',
            'name' => $this->plainLocales($payload['name'] ?? null),
            'description' => $this->htmlLocales($payload['description'] ?? null),
            'meta_title' => $this->plainLocales($payload['meta_title'] ?? null),
            'meta_description' => $this->plainLocales($payload['meta_description'] ?? null),
            'slug' => $slug,
            'sku' => $str($payload['sku'] ?? null, 100),
            'barcode' => $variants ? null : $str($payload['barcode'] ?? null, 13),
            'brand_id' => $int($payload['brand_id'] ?? null),
            'category_ids' => $this->intList($payload['category_ids'] ?? []),
            'tag_ids' => $this->intList($payload['tag_ids'] ?? []),
            'price' => $this->money($payload['price'] ?? null),
            'sale_price' => $this->money($payload['sale_price'] ?? null),
            'cost' => $this->money($payload['cost'] ?? null),
            'weight' => $int($payload['weight'] ?? null),
            'min_stock_alert_qty' => $int($payload['min_stock_alert_qty'] ?? null),
            'gift' => $gift,
            'images' => $images,
            'new_attributes' => $newAttributes,
            'new_values' => $newValues,
            'variants' => $variants,
        ], fn ($v) => $v !== null && $v !== []);
    }

    public function check(array $payload, ?Model $target, ?MarketingSuggestion $s = null): array
    {
        $errors = [];
        $warnings = [];
        $req = fn (string $f) => __('validation.required', ['attribute' => $this->label($f)]);

        if (! ($payload['name'] ?? [])) {
            $errors['payload.name'] = $req('name');
        }

        // images: every URL must have been downloaded as a real image; a variant may point at one of them by index
        $images = (array) ($payload['images'] ?? []);
        foreach ($images as $n => $img) {
            if (isset($img['error'])) {
                $errors["payload.images.$n.url"] = __('marketing.suggestions.errors.image_fetch', ['url' => Str::limit((string) $img['url'], 80), 'reason' => __('marketing.suggestions.image_errors.'.$img['error'])]);
            } elseif (! $this->images->exists($img)) {
                $errors["payload.images.$n.url"] = __('marketing.suggestions.errors.image_missing');
            }
        }

        // references must exist (soft-deleted / draft attributes excluded)
        if (isset($payload['brand_id']) && ! Brand::query()->whereKey($payload['brand_id'])->exists()) {
            $errors['payload.brand_id'] = __('validation.exists', ['attribute' => $this->label('brand_id')]);
        }
        foreach (['category_ids' => Category::class, 'tag_ids' => Tag::class] as $f => $model) {
            $ids = (array) ($payload[$f] ?? []);
            if ($ids && $model::query()->whereKey($ids)->count() !== count($ids)) {
                $errors["payload.$f"] = __('validation.exists', ['attribute' => $this->label($f)]);
            }
        }

        // identifiers: unique across products AND variants (same rules as the product form)
        $skus = [];
        $mainSku = $payload['sku'] ?? null;
        if ($mainSku !== null) {
            if (ProductService::isDraftSku($mainSku)) {
                $errors['payload.sku'] = __('validation.not_regex', ['attribute' => $this->label('sku')]);
            } elseif ($this->skuTaken($mainSku)) {
                $errors['payload.sku'] = __('validation.unique', ['attribute' => $this->label('sku')]);
            }
            $skus[Str::lower($mainSku)] = true;
        }
        if (isset($payload['barcode'])) {
            if (! BarcodeService::isValidEan13($payload['barcode'])) {
                $errors['payload.barcode'] = __('admin.invalid_ean13');
            } elseif (Product::withTrashed()->where('barcode', $payload['barcode'])->exists() || ProductVariant::withTrashed()->where('barcode', $payload['barcode'])->exists()) {
                $errors['payload.barcode'] = __('validation.unique', ['attribute' => $this->label('barcode')]);
            }
        }
        if (isset($payload['slug']) && Product::withTrashed()->where('slug', $payload['slug'])->exists()) {
            $warnings[] = __('marketing.suggestions.warnings.slug_taken', ['slug' => $payload['slug']]);
        }

        // prices
        if (isset($payload['sale_price'])) {
            if (! isset($payload['price'])) {
                $errors['payload.price'] = __('validation.required_with', ['attribute' => $this->label('price'), 'values' => $this->label('sale_price')]);
            } elseif (Money::cmp($payload['sale_price'], $payload['price']) > 0) {
                $errors['payload.sale_price'] = __('validation.lte.numeric', ['attribute' => $this->label('sale_price'), 'value' => $this->label('price')]);
            }
        }
        $this->margin($payload['price'] ?? null, $payload['sale_price'] ?? null, $payload['cost'] ?? null, 'payload', $errors, $warnings);

        // attributes / values proposed with the product: ref => attribute key ("id:3" existing, "new:case_size" proposed)
        $refs = $this->checkNewAttributes($payload, $errors, $warnings);

        // variants
        $variants = (array) ($payload['variants'] ?? []);
        if ($variants) {
            $valueIds = array_values(array_unique(array_filter(array_merge(...array_map(fn ($v) => (array) ($v['values'] ?? []), $variants)), 'is_int')));
            $values = AttributeValue::query()->whereKey($valueIds)->whereHas('attribute', fn ($q) => $q->where('is_draft', false))->get(['id', 'attribute_id'])->keyBy('id');
            $axes = null;
            $combos = [];
            foreach ($variants as $n => $v) {
                $p = "payload.variants.$n";
                $ids = (array) ($v['values'] ?? []);
                $attrs = collect($ids)->map(fn ($x) => is_int($x) ? (isset($values[$x]) ? 'id:'.$values[$x]->attribute_id : null) : ($refs[$x] ?? null));
                if ($attrs->contains(null)) {
                    $unknown = collect($ids)->filter(fn ($x, $i) => $attrs[$i] === null)->implode(', ');
                    $errors["$p.values"] = __('marketing.suggestions.errors.variant_unknown_values', ['values' => $unknown]);

                    continue;
                }
                if ($attrs->count() !== $attrs->unique()->count()) {
                    $errors["$p.values"] = __('marketing.suggestions.errors.variant_one_value_per_attribute');
                }
                $set = $attrs->sort()->values()->all();
                $axes ??= $set;
                if ($set !== $axes) {
                    $errors["$p.values"] = __('marketing.suggestions.errors.variant_same_attributes');
                }
                $hash = implode('-', $ids);   // values are normalised (ids sorted, then refs sorted)
                if (isset($combos[$hash])) {
                    $errors["$p.values"] = __('admin.duplicate_variant');
                }
                $combos[$hash] = true;
                if (isset($v['sku'])) {
                    if (isset($skus[Str::lower($v['sku'])]) || $this->skuTaken($v['sku'])) {
                        $errors["$p.sku"] = __('validation.unique', ['attribute' => $this->label('sku')]);
                    }
                    $skus[Str::lower($v['sku'])] = true;
                }
                if (isset($v['image']) && ! isset($images[$v['image']])) {
                    $errors["$p.image"] = __('marketing.suggestions.errors.variant_image_index', ['n' => count($images)]);
                }
                $price = $v['price'] ?? $payload['price'] ?? null;
                if (isset($v['sale_price']) && $price !== null && Money::cmp($v['sale_price'], $price) > 0) {
                    $errors["$p.sale_price"] = __('validation.lte.numeric', ['attribute' => $this->label('sale_price'), 'value' => $this->label('price')]);
                }
                if (isset($v['price']) || isset($v['cost'])) {
                    $this->margin($price, $v['sale_price'] ?? null, $v['cost'] ?? $payload['cost'] ?? null, $p, $errors, $warnings);
                }
            }
            if (! isset($payload['price']) && collect($variants)->contains(fn ($v) => ! isset($v['price']))) {
                $warnings[] = __('marketing.suggestions.warnings.new_product_no_price');
            }
        } elseif (! isset($payload['price'])) {
            $warnings[] = __('marketing.suggestions.warnings.new_product_no_price');
        }

        // gentle reminders: the product is created as a draft, so none of these block it
        if (! isset($payload['sku'])) {
            $warnings[] = __('marketing.suggestions.warnings.new_product_no_sku');
        }
        if (! ($payload['category_ids'] ?? [])) {
            $warnings[] = __('marketing.suggestions.warnings.new_product_no_category');
        }
        if (! $images) {
            $warnings[] = __('marketing.suggestions.warnings.new_product_no_images');
        }
        if ($dup = $this->sameName((array) ($payload['name'] ?? []))) {
            $warnings[] = __('marketing.suggestions.warnings.new_product_duplicate', ['id' => $dup->id, 'sku' => $dup->sku]);
        }

        return ['errors' => $errors, 'warnings' => array_values(array_unique($warnings))];
    }

    public function snapshot(?Model $target, array $payload): ?array
    {
        return null;   // nothing exists yet
    }

    public function compare(MarketingSuggestion $s, ?Model $target): array
    {
        $p = (array) $s->payload;
        $rows = [];
        foreach (['type', 'name', 'description', 'meta_title', 'meta_description', ...self::SCALARS] as $f) {
            if (array_key_exists($f, $p)) {
                $row = $this->row($f, null, $f === 'brand_id' ? $this->brandLabel((int) $p[$f]) : $p[$f]);
                $f === 'description' && $row['html'] = true;
                $rows[] = $row;
            }
        }
        foreach (['category_ids' => Category::class, 'tag_ids' => Tag::class] as $f => $model) {
            if ($ids = (array) ($p[$f] ?? [])) {
                $rows[] = $this->row($f, null, $model::query()->whereKey($ids)->get()->map(fn ($m) => $m->name.' #'.$m->id)->all());
            }
        }
        if (isset($p['gift'])) {
            $rows[] = $this->row('gift', null, $p['gift']);
        }
        if ($p['images'] ?? []) {
            $rows[] = $this->row('images', null, array_map(fn ($img) => isset($img['sha256']) ? [
                'src' => $this->previewUrl($img), 'url' => $img['url'], 'size' => ($img['width'] ?? '?').'×'.($img['height'] ?? '?'),
            ] : ['src' => null, 'url' => $img['url'], 'size' => __('marketing.suggestions.image_errors.'.($img['error'] ?? 'missing'))], $p['images'])) + ['images' => true];
        }
        if ($p['new_attributes'] ?? []) {
            $rows[] = $this->row('new_attributes', null, array_map(fn ($a) => collect($a['name'] ?? [])->first().' ('.($a['code'] ?? '?').', '.($a['type'] ?? 'select').'): '
                .collect($a['values'] ?? [])->map(fn ($v) => collect($v['value'] ?? [])->first().($v['color_hex'] ?? null ? ' '.$v['color_hex'] : '').' ['.$v['ref'].']')->implode('، '), $p['new_attributes']));
        }
        if ($p['new_values'] ?? []) {
            $names = Attribute::query()->whereKey(array_column($p['new_values'], 'attribute_id'))->get()->mapWithKeys(fn ($a) => [$a->id => $a->name]);
            $rows[] = $this->row('new_values', null, array_map(fn ($v) => ($names[$v['attribute_id']] ?? '#'.$v['attribute_id']).' #'.$v['attribute_id'].': '
                .collect($v['value'] ?? [])->first().($v['color_hex'] ?? null ? ' '.$v['color_hex'] : '').' ['.$v['ref'].']', $p['new_values']));
        }
        if ($p['variants'] ?? []) {
            $rows[] = $this->row('variants', null, $this->variantLines($p));
        }

        return $rows;
    }

    public function apply(MarketingSuggestion $s, ?Model $target, array $options, ?int $adminId): ApplyResult
    {
        $p = (array) $s->payload;
        $this->ensure($check = $this->check($p, null, $s));
        $slug = Str::slug((string) (collect($p['name'] ?? [])->first() ?: 'product'));
        $files = array_map(fn ($img) => $this->images->toUploadedFile($img, $slug), (array) ($p['images'] ?? []));

        [$refIds, $createdAttributes, $createdValues] = $this->createAttributes($p);
        $variants = [];
        foreach ((array) ($p['variants'] ?? []) as $v) {
            $ids = array_map(fn ($x) => is_int($x) ? $x : $refIds[$x], (array) $v['values']);
            $variants[] = new ProductVariantData(null, $ids, $v['sku'] ?? null, $v['weight'] ?? null, (bool) ($v['is_active'] ?? true),
                isset($v['image']) ? ($files[$v['image']] ?? null) : null, $v['price'] ?? null, $v['sale_price'] ?? null, $v['cost'] ?? null, $v['min_stock_alert_qty'] ?? null);
        }
        $gift = (array) ($p['gift'] ?? []);

        $product = $this->products->create(new ProductData(
            type: $p['type'] ?? ($variants ? 'variable' : 'simple'),
            name: $p['name'], description: $p['description'] ?? [], metaTitle: $p['meta_title'] ?? [], metaDescription: $p['meta_description'] ?? [],
            slug: $p['slug'] ?? null, sku: $p['sku'] ?? null, barcode: $p['barcode'] ?? null, weight: $p['weight'] ?? null,
            brandId: $p['brand_id'] ?? null, categoryIds: $p['category_ids'] ?? [], isActive: false,
            images: $files, removeImageIds: [], variants: $variants, tagIds: $p['tag_ids'] ?? [],
            price: $p['price'] ?? null, salePrice: $p['sale_price'] ?? null, cost: $p['cost'] ?? null, minStockAlert: $p['min_stock_alert_qty'] ?? null,
            giftSuitable: (bool) ($gift['suitable'] ?? false), giftRecipients: $gift['recipients'] ?? [], giftOccasions: $gift['occasions'] ?? [],
            isDraft: true,
        ));

        return new ApplyResult(['created' => false], ['created' => true, 'product_id' => $product->id, 'sku' => $product->sku, 'draft' => true,
            'variants' => $product->variants()->count(), 'images' => $product->images()->count(),
            'created_attribute_ids' => $createdAttributes, 'created_value_ids' => $createdValues], 'product', $product->id,
            [...$check['warnings'], __('marketing.suggestions.warnings.new_product_is_draft')]);
    }

    public function supportsRollback(): bool
    {
        return true;
    }

    public function rollback(MarketingSuggestion $s, MarketingSuggestionExecution $exec, ?int $adminId): array
    {
        $id = (int) (((array) $exec->after)['product_id'] ?? 0);
        $product = Product::query()->find($id);
        if (! $product) {
            return ['deleted_product_id' => $id, 'already_gone' => true];
        }
        if (! $product->is_draft) {
            throw SuggestionException::make('new_product_published', 409, ['product_id' => $id]);   // may already be sold: hide it from the catalog instead
        }
        $this->products->delete($product);   // soft delete (images kept until retention:purge)

        // attributes / values this suggestion created: removed too, unless another live product started using them meanwhile
        $after = (array) $exec->after;
        $kept = [];
        foreach ((array) ($after['created_value_ids'] ?? []) as $vid) {
            ($v = AttributeValue::query()->find($vid)) && ! $this->attributes->removeValueIfUnused($v) && $kept[] = 'value#'.$vid;
        }
        foreach ((array) ($after['created_attribute_ids'] ?? []) as $aid) {
            if ($a = Attribute::query()->find($aid)) {
                try {
                    $this->attributes->delete($a);
                } catch (\Illuminate\Validation\ValidationException) {
                    $kept[] = 'attribute#'.$aid;
                }
            }
        }

        return ['deleted_product_id' => $id] + ($kept ? ['kept_in_use' => $kept] : []);
    }

    public function editFields(?string $targetType = null): array
    {
        return [
            $this->field('payload.name', 'locales-text'),
            $this->field('payload.description', 'locales-html'),
            $this->field('payload.meta_title', 'locales-text'),
            $this->field('payload.meta_description', 'locales-textarea'),
            $this->field('payload.slug', 'text', null, ['dir' => 'ltr']),
            $this->field('payload.sku', 'text', null, ['dir' => 'ltr']),
            $this->field('payload.barcode', 'text', null, ['dir' => 'ltr']),
            $this->field('payload.brand_id', 'number'),
            $this->field('payload.category_ids', 'id-list'),
            $this->field('payload.tag_ids', 'id-list'),
            $this->field('payload.price', 'number', null, ['step' => '0.01']),
            $this->field('payload.sale_price', 'number', null, ['step' => '0.01']),
            $this->field('payload.cost', 'number', null, ['step' => '0.01']),
            $this->field('payload.weight', 'number', null, ['step' => '1']),
            $this->field('payload.min_stock_alert_qty', 'number'),
            $this->field('payload.gift', 'json', null, ['hint' => 'gift_hint']),
            $this->field('payload.images', 'json', null, ['hint' => 'images_hint']),
            $this->field('payload.new_attributes', 'json', null, ['hint' => 'new_attributes_hint']),
            $this->field('payload.new_values', 'json', null, ['hint' => 'new_values_hint']),
            $this->field('payload.variants', 'json', null, ['hint' => 'variants_hint']),
        ];
    }

    // ------------------------------------------------------------------ helpers

    /** One explicit rule per store locale for the translated texts of proposed attributes / values (unknown locales are refused). */
    private function proposedLocaleRules(): array
    {
        $r = [];
        foreach ($this->locales() as $l) {
            foreach (['payload.new_attributes.*.name.', 'payload.new_attributes.*.values.*.value.', 'payload.new_values.*.value.'] as $prefix) {
                $r[$prefix.$l] = ['nullable', 'string', 'max:255'];
            }
        }

        return $r;
    }

    /** Variant values: ids (ints, sorted) then refs of proposed values (lowercase, sorted). */
    private function valueList(mixed $v): array
    {
        $ids = [];
        $refs = [];
        foreach ((array) $v as $x) {
            if (is_int($x) || (is_string($x) && ctype_digit(trim($x)))) {
                (int) $x > 0 && $ids[] = (int) $x;
            } elseif (($r = $this->ref($x)) !== null) {
                $refs[] = $r;
            }
        }
        $ids = array_values(array_unique($ids));
        $refs = array_values(array_unique($refs));
        sort($ids);
        sort($refs);

        return [...$ids, ...$refs];
    }

    private function ref(mixed $v): ?string
    {
        $r = is_string($v) ? Str::lower(trim($v)) : '';

        return preg_match(self::REF, $r) ? $r : null;
    }

    /** @return array{ref:?string,value:array,color_hex:?string} */
    private function newValue(array $v, bool $color): array
    {
        $hex = is_string($v['color_hex'] ?? null) && preg_match('/^#?([0-9a-f]{6})$/i', trim($v['color_hex']), $m) ? '#'.Str::lower($m[1]) : null;

        return ['ref' => $this->ref($v['ref'] ?? null), 'value' => $this->plainLocales($v['value'] ?? null), 'color_hex' => $color ? $hex : null];
    }

    /** Validate new_attributes / new_values; returns ref => attribute key for the variant checks. */
    private function checkNewAttributes(array $payload, array &$errors, array &$warnings): array
    {
        $refs = [];
        $seenRef = function (?string $ref, string $path) use (&$refs, &$errors): bool {
            if ($ref === null) {
                $errors[$path] = __('validation.regex', ['attribute' => 'ref']);

                return false;
            }
            if (isset($refs[$ref])) {
                $errors[$path] = __('marketing.suggestions.errors.ref_duplicate', ['ref' => $ref]);

                return false;
            }

            return true;
        };
        $texts = fn (array $value) => array_values(array_filter(array_map(fn ($t) => mb_strtolower(trim((string) $t)), $value)));

        $codes = [];
        foreach ((array) ($payload['new_attributes'] ?? []) as $n => $a) {
            $p = "payload.new_attributes.$n";
            if (! ($a['name'] ?? [])) {
                $errors["$p.name"] = __('validation.required', ['attribute' => $this->label('name')]);
            }
            $code = $a['code'] ?? null;
            if ($code === null) {
                $errors["$p.code"] = __('validation.required', ['attribute' => $this->label('code')]);
            } elseif (isset($codes[$code]) || Attribute::query()->where('code', $code)->exists()) {
                $errors["$p.code"] = __('marketing.suggestions.errors.attribute_code_taken', ['code' => $code]);
            }
            $codes[(string) $code] = true;
            if ($existing = $this->attributeNamed((array) ($a['name'] ?? []))) {
                $warnings[] = __('marketing.suggestions.warnings.attribute_exists', ['name' => $existing->name, 'id' => $existing->id]);
            }
            $key = 'new:'.($a['ref'] ?? $n);
            $seen = [];
            foreach ((array) ($a['values'] ?? []) as $m => $v) {
                $vp = "$p.values.$m";
                if (! ($v['value'] ?? [])) {
                    $errors["$vp.value"] = __('validation.required', ['attribute' => $this->label('value')]);
                }
                if (($a['type'] ?? null) === 'color' && ! isset($v['color_hex'])) {
                    $errors["$vp.color_hex"] = __('validation.required', ['attribute' => 'color_hex']);
                }
                foreach ($texts((array) ($v['value'] ?? [])) as $t) {
                    if (isset($seen[$t])) {
                        $errors["$vp.value"] = __('marketing.suggestions.errors.value_duplicate', ['value' => $t]);
                    }
                    $seen[$t] = true;
                }
                if ($seenRef($v['ref'] ?? null, "$vp.ref")) {
                    $refs[$v['ref']] = $key;
                }
            }
        }

        $attrs = Attribute::query()->whereKey(array_column((array) ($payload['new_values'] ?? []), 'attribute_id'))->with('values')->get()->keyBy('id');
        foreach ((array) ($payload['new_values'] ?? []) as $n => $v) {
            $p = "payload.new_values.$n";
            $attr = $attrs[$v['attribute_id'] ?? 0] ?? null;
            if (! $attr || $attr->is_draft) {
                $errors["$p.attribute_id"] = __('validation.exists', ['attribute' => $this->label('attribute_id')]);

                continue;
            }
            if (! ($v['value'] ?? [])) {
                $errors["$p.value"] = __('validation.required', ['attribute' => $this->label('value')]);
            }
            if ($attr->type === AttributeType::COLOR && ! isset($v['color_hex'])) {
                $errors["$p.color_hex"] = __('validation.required', ['attribute' => 'color_hex']);
            }
            $mine = $texts((array) ($v['value'] ?? []));
            $dup = $attr->values->first(fn ($e) => array_intersect($mine, $texts($e->getTranslations('value'))) !== []);
            if ($dup) {   // the store already has it: use its id instead of creating a twin
                $errors["$p.value"] = __('marketing.suggestions.errors.value_exists', ['value' => $dup->value, 'id' => $dup->id, 'attribute' => $attr->name]);
            }
            if ($seenRef($v['ref'] ?? null, "$p.ref")) {
                $refs[$v['ref']] = 'id:'.$attr->id;
            }
        }

        return $refs;
    }

    private function attributeNamed(array $names): ?Attribute
    {
        $names = array_values(array_filter(array_map(fn ($n) => mb_strtolower(trim((string) $n)), $names)));

        return $names ? Attribute::query()->where('is_draft', false)->get()
            ->first(fn (Attribute $a) => array_intersect($names, array_map(fn ($t) => mb_strtolower(trim((string) $t)), $a->getTranslations('name'))) !== []) : null;
    }

    /**
     * Create the proposed attributes / values (same transaction as the product).
     *
     * @return array{0: array<string,int>, 1: list<int>, 2: list<int>} ref => value id, created attribute ids, value ids added to existing attributes
     */
    private function createAttributes(array $p): array
    {
        $map = [];
        $attrIds = [];
        $valueIds = [];
        $sort = (int) Attribute::query()->max('sort_order');
        foreach ((array) ($p['new_attributes'] ?? []) as $a) {
            $rows = array_map(fn ($v, $i) => ['id' => null, 'value' => $v['value'], 'color_hex' => $v['color_hex'] ?? null, 'sort_order' => $i], $a['values'], array_keys($a['values']));
            $attr = $this->attributes->create(new AttributeData($a['name'], $a['code'], $a['type'], ++$sort, $rows, false));
            $created = $attr->values()->get()->values();
            foreach (array_values($a['values']) as $i => $v) {
                $map[$v['ref']] = $created[$i]->id;
            }
            $attrIds[] = $attr->id;
        }
        foreach ((array) ($p['new_values'] ?? []) as $v) {
            $value = $this->attributes->addValue(Attribute::query()->findOrFail($v['attribute_id']), $v['value'], $v['color_hex'] ?? null);
            $map[$v['ref']] = $value->id;
            $valueIds[] = $value->id;
        }

        return [$map, $attrIds, $valueIds];
    }

    private function skuTaken(string $sku): bool
    {
        return Product::withTrashed()->where('sku', $sku)->exists() || ProductVariant::withTrashed()->where('sku', $sku)->exists();
    }

    /** Selling below cost is refused; below the configured minimum margin, or with no cost, is a warning. */
    private function margin(?string $price, ?string $sale, ?string $cost, string $path, array &$errors, array &$warnings): void
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

    private function sameName(array $names): ?Product
    {
        $names = array_values(array_filter(array_map(fn ($n) => mb_strtolower(trim((string) $n)), $names)));
        if (! $names) {
            return null;
        }

        return Product::query()->where(function ($q) use ($names) {
            foreach ($this->locales() as $l) {
                $q->orWhereIn(\Illuminate\Support\Facades\DB::raw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"$l\"')))"), $names);
            }
        })->first(['id', 'sku']);
    }

    private function brandLabel(int $id): string
    {
        $b = Brand::query()->find($id);

        return $b ? $b->name.' #'.$id : '#'.$id;
    }

    /** "Black / 42 mm · SKU W-1 · 1500.00 · image 2" per variant, for the review page. */
    private function variantLines(array $p): array
    {
        $ids = array_filter(array_merge(...array_map(fn ($v) => (array) ($v['values'] ?? []), (array) $p['variants'])), 'is_int');
        $names = AttributeValue::withTrashed()->whereKey($ids)->get()->mapWithKeys(fn ($v) => [$v->id => $v->value])->all();
        foreach ([...array_merge(...array_map(fn ($a) => (array) ($a['values'] ?? []), (array) ($p['new_attributes'] ?? []))), ...(array) ($p['new_values'] ?? [])] as $v) {
            isset($v['ref']) && $names[$v['ref']] = collect($v['value'] ?? [])->first().' ('.__('marketing.suggestions.fields.new').')';
        }

        return array_map(fn ($v) => implode(' · ', array_filter([
            collect((array) ($v['values'] ?? []))->map(fn ($id) => $names[$id] ?? '#'.$id)->implode(' / '),
            isset($v['sku']) ? $this->label('sku').' '.$v['sku'] : null,
            isset($v['price']) ? $this->label('price').' '.$v['price'] : null,
            isset($v['sale_price']) ? $this->label('sale_price').' '.$v['sale_price'] : null,
            isset($v['cost']) ? $this->label('cost').' '.$v['cost'] : null,
            isset($v['weight']) ? $v['weight'].' g' : null,
            isset($v['image']) ? $this->label('image').' '.($v['image'] + 1) : null,
            ($v['is_active'] ?? true) ? null : $this->label('inactive'),
        ])), (array) $p['variants']);
    }

    private function previewUrl(array $img): string
    {
        return route('marketer.suggestions.image', ['sha' => $img['sha256']]);   // marketer-authenticated, never public
    }
}
