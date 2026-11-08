<?php

namespace App\Services\Marketing\Suggestions\Types;

use App\Enums\Marketing\SuggestionTypeKey;
use App\Enums\ProductListType;
use App\Models\MarketingSuggestion;
use App\Models\MarketingSuggestionExecution;
use App\Models\Product;
use App\Models\Tag;
use App\Services\CatalogService;
use App\Services\GiftFinder\GiftFinderService;
use App\Services\Lists\ProductListService;
use App\Services\Marketing\Suggestions\ApplyResult;
use App\Services\ProductService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Product merchandising (MCP.md §17). Target: product (required). Real columns only:
 * - name / description → the storefront wording overlay `products.content` (CatalogService::updateContent; the catalog text stays untouched),
 * - tags_add / tags_remove → product tags (existing tags only, ProductService::syncTags),
 * - gift {suitable, recipients, occasions} → Gift Finder tags (ProductService::updateGiftTags),
 * - pin {list, position} / unpin [lists] → admin pins of the recommended / most_sold / most_reviewed lists (ProductListService) — "featured".
 * Not supported (no column): short description, badges. Rollback restores every touched value.
 */
class ProductSuggestionType extends AbstractSuggestionType
{
    protected array $targetModels = ['product' => Product::class];

    public function __construct(private CatalogService $catalog, private ProductService $products, private ProductListService $lists) {}

    public function key(): SuggestionTypeKey
    {
        return SuggestionTypeKey::PRODUCT;
    }

    public function targetRequired(?array $payload = null): bool
    {
        return true;
    }

    public function rules(?string $targetType = null): array
    {
        return $this->localeRules('name', 200) + $this->localeRules('description', 20000) + [
            'payload' => ['required', 'array'],
            'payload.tags_add' => ['nullable', 'array', 'max:20'], 'payload.tags_add.*' => ['integer', Rule::exists('tags', 'id')->whereNull('deleted_at')],
            'payload.tags_remove' => ['nullable', 'array', 'max:20'], 'payload.tags_remove.*' => ['integer'],
            'payload.gift' => ['nullable', 'array'], 'payload.gift.suitable' => ['required_with:payload.gift', 'boolean'],
            'payload.gift.recipients' => ['nullable', 'array'], 'payload.gift.recipients.*' => [Rule::in(GiftFinderService::RECIPIENTS)],
            'payload.gift.occasions' => ['nullable', 'array'], 'payload.gift.occasions.*' => [Rule::in(GiftFinderService::OCCASIONS)],
            'payload.pin' => ['nullable', 'array'], 'payload.pin.list' => ['required_with:payload.pin', Rule::in(ProductListType::values())],
            'payload.pin.position' => ['nullable', 'integer', 'min:1', 'max:100'],
            'payload.unpin' => ['nullable', 'array'], 'payload.unpin.*' => [Rule::in(ProductListType::values())],
        ];
    }

    public function normalize(array $payload, ?string $targetType = null): array
    {
        $out = array_filter(['name' => $this->plainLocales($payload['name'] ?? null), 'description' => $this->htmlLocales($payload['description'] ?? null),
            'tags_add' => $this->intList($payload['tags_add'] ?? []), 'tags_remove' => $this->intList($payload['tags_remove'] ?? [])], fn ($v) => $v !== []);
        if (is_array($payload['gift'] ?? null)) {
            $g = $payload['gift'];
            $r = array_values(array_unique(array_intersect((array) ($g['recipients'] ?? []), GiftFinderService::RECIPIENTS)));
            $o = array_values(array_unique(array_intersect((array) ($g['occasions'] ?? []), GiftFinderService::OCCASIONS)));
            sort($r);
            sort($o);
            $out['gift'] = ['suitable' => (bool) ($g['suitable'] ?? false), 'recipients' => $r, 'occasions' => $o];
        }
        if (is_array($payload['pin'] ?? null) && ($payload['pin']['list'] ?? null)) {
            $out['pin'] = ['list' => (string) $payload['pin']['list'], 'position' => isset($payload['pin']['position']) && $payload['pin']['position'] !== '' ? (int) $payload['pin']['position'] : null];
        }
        if ($u = array_values(array_unique(array_intersect((array) ($payload['unpin'] ?? []), ProductListType::values())))) {
            sort($u);
            $out['unpin'] = $u;
        }
        ksort($out);

        return $out;
    }

    public function check(array $payload, ?Model $target, ?MarketingSuggestion $s = null): array
    {
        $errors = [];
        if ($payload === []) {
            $errors['payload'] = __('marketing.suggestions.errors.empty_change');
        }
        if (array_intersect($payload['tags_add'] ?? [], $payload['tags_remove'] ?? [])) {
            $errors['payload.tags_remove'] = __('marketing.suggestions.errors.tag_conflict');
        }
        if (isset($payload['pin']) && in_array($payload['pin']['list'], $payload['unpin'] ?? [], true)) {
            $errors['payload.unpin'] = __('marketing.suggestions.errors.pin_conflict');
        }
        $warnings = [];
        if ($target instanceof Product && (! $target->is_active || $target->price === null) && isset($payload['pin'])) {
            $warnings[] = __('marketing.suggestions.warnings.product_not_listed');
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /** Touched values only (so unrelated edits of the product never make the suggestion stale). */
    public function snapshot(?Model $target, array $payload): ?array
    {
        if (! $target instanceof Product) {
            return null;
        }
        $snap = [];
        foreach (['name', 'description'] as $f) {
            if (isset($payload[$f])) {
                foreach (array_keys($payload[$f]) as $l) {
                    $snap[$f][$l] = $this->effective($target, $f, $l);
                }
            }
        }
        if (isset($payload['tags_add']) || isset($payload['tags_remove'])) {
            $snap['tags'] = $this->tagIds($target);
        }
        if (isset($payload['gift'])) {
            $snap['gift'] = $this->gift($target);
        }
        foreach ($this->touchedLists($payload) as $list) {
            $snap['pins'][$list] = $this->lists->pinnedIds(ProductListType::from($list));
        }

        return $snap;
    }

    public function compare(MarketingSuggestion $s, ?Model $target): array
    {
        $p = (array) $s->payload;
        $rows = [];
        foreach (['name', 'description'] as $f) {
            foreach ((array) ($p[$f] ?? []) as $l => $v) {
                $rows[] = $this->row($f.'.'.$l, $target instanceof Product ? $this->effective($target, $f, $l) : null, $v, $this->label($f).' ('.$l.')') + ['html' => $f === 'description'];
            }
        }
        if (isset($p['tags_add']) || isset($p['tags_remove'])) {
            $cur = $target instanceof Product ? $this->tagIds($target) : [];
            $new = array_values(array_diff(array_unique(array_merge($cur, $p['tags_add'] ?? [])), $p['tags_remove'] ?? []));
            $rows[] = $this->row('tags', $this->tagNames($cur), $this->tagNames($new));
        }
        if (isset($p['gift'])) {
            $rows[] = $this->row('gift', $target instanceof Product ? $this->gift($target) : null, $p['gift']);
        }
        foreach ($this->touchedLists($p) as $list) {
            $pinned = $target instanceof Product ? $this->lists->pinnedIds(ProductListType::from($list)) : [];
            $pos = $target instanceof Product && ($i = array_search($target->id, $pinned, true)) !== false ? $i + 1 : null;
            $proposed = ($p['pin']['list'] ?? null) === $list ? ($p['pin']['position'] ?? __('marketing.suggestions.last')) : null;
            $rows[] = $this->row('pin.'.$list, $pos, $proposed, $this->label('pin').': '.ProductListType::from($list)->label());
        }

        return $rows;
    }

    public function apply(MarketingSuggestion $s, ?Model $target, array $options, ?int $adminId): ApplyResult
    {
        /** @var Product $target */
        $p = (array) $s->payload;
        $this->ensure($this->check($p, $target, $s));
        $before = $this->state($target, $p);

        if (isset($p['name']) || isset($p['description'])) {
            $content = (array) ($target->content ?? []);
            foreach (['name', 'description'] as $f) {
                foreach ((array) ($p[$f] ?? []) as $l => $v) {
                    $content[$f][$l] = $v;
                }
            }
            $this->catalog->updateContent($target, $content);
        }
        if (isset($p['tags_add']) || isset($p['tags_remove'])) {
            $cur = $this->tagIds($target);
            $this->products->syncTags($target, array_values(array_diff(array_unique(array_merge($cur, $p['tags_add'] ?? [])), $p['tags_remove'] ?? [])));
        }
        if (isset($p['gift'])) {
            $this->products->updateGiftTags($target, $p['gift']['suitable'], $p['gift']['recipients'], $p['gift']['occasions']);
        }
        foreach ($p['unpin'] ?? [] as $list) {
            $this->lists->unpin(ProductListType::from($list), $target->id);
        }
        if (isset($p['pin'])) {
            $this->lists->pin(ProductListType::from($p['pin']['list']), $target->id, $p['pin']['position']);
        }

        return new ApplyResult($before, $this->state($target->refresh(), $p), 'product', $target->id);
    }

    public function supportsRollback(): bool
    {
        return true;
    }

    public function rollback(MarketingSuggestion $s, MarketingSuggestionExecution $exec, ?int $adminId): array
    {
        $before = (array) $exec->before;
        $product = Product::query()->findOrFail($s->target_entity_id);
        if (array_key_exists('content', $before)) {
            $this->catalog->updateContent($product, $before['content'] ?: null);
        }
        if (array_key_exists('tags', $before)) {
            $this->products->syncTags($product, (array) $before['tags']);
        }
        if (array_key_exists('gift', $before)) {
            $g = (array) $before['gift'];
            $this->products->updateGiftTags($product, (bool) $g['suitable'], (array) $g['recipients'], (array) $g['occasions']);
        }
        foreach ((array) ($before['pins'] ?? []) as $list => $ids) {
            $this->lists->restore(ProductListType::from($list), (array) $ids);
        }

        return $this->state($product->refresh(), (array) $s->payload);
    }

    public function editFields(?string $targetType = null): array
    {
        return [
            $this->field('payload.name', 'locales-text'),
            $this->field('payload.description', 'locales-html'),
            $this->field('payload.tags_add', 'id-list', null, ['hint' => 'tag_ids']),
            $this->field('payload.tags_remove', 'id-list', null, ['hint' => 'tag_ids']),
            $this->field('payload.gift.suitable', 'bool'),
            $this->field('payload.gift.recipients', 'multi', null, ['options' => GiftFinderService::RECIPIENTS]),
            $this->field('payload.gift.occasions', 'multi', null, ['options' => GiftFinderService::OCCASIONS]),
            $this->field('payload.pin.list', 'select', null, ['options' => ProductListType::values(), 'nullable' => true]),
            $this->field('payload.pin.position', 'number'),
            $this->field('payload.unpin', 'multi', null, ['options' => ProductListType::values()]),
        ];
    }

    // ------------------------------------------------------------------

    private function effective(Product $p, string $field, string $locale): ?string
    {
        $o = ((array) ($p->content ?? []))[$field][$locale] ?? null;

        return is_string($o) && $o !== '' ? $o : ($p->getTranslation($field, $locale, false) ?: null);
    }

    private function tagIds(Product $p): array
    {
        $ids = $p->tags()->pluck('tags.id')->map(fn ($v) => (int) $v)->all();
        sort($ids);

        return $ids;
    }

    private function tagNames(array $ids): array
    {
        return Tag::query()->whereIn('id', $ids)->get()->map(fn ($t) => $t->name)->values()->all();
    }

    private function gift(Product $p): array
    {
        $r = array_values((array) $p->gift_recipients);
        $o = array_values((array) $p->gift_occasions);
        sort($r);
        sort($o);

        return ['suitable' => (bool) $p->gift_suitable, 'recipients' => $r, 'occasions' => $o];
    }

    private function touchedLists(array $p): array
    {
        $l = array_values(array_unique(array_merge(isset($p['pin']) ? [$p['pin']['list']] : [], $p['unpin'] ?? [])));
        sort($l);

        return $l;
    }

    /** Full before/after record of what this payload touches (rollback source). */
    private function state(Product $p, array $payload): array
    {
        $s = [];
        if (isset($payload['name']) || isset($payload['description'])) {
            $s['content'] = (array) ($p->content ?? []);
        }
        if (isset($payload['tags_add']) || isset($payload['tags_remove'])) {
            $s['tags'] = $this->tagIds($p);
        }
        if (isset($payload['gift'])) {
            $s['gift'] = $this->gift($p);
        }
        foreach ($this->touchedLists($payload) as $list) {
            $s['pins'][$list] = $this->lists->pinnedIds(ProductListType::from($list));
        }

        return $s;
    }
}
