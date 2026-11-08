<?php

namespace App\Services\Marketing\Suggestions\Types;

use App\DTOs\FlashSaleData;
use App\Enums\FlashDiscountType;
use App\Enums\Marketing\SuggestionTypeKey;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\MarketingSuggestion;
use App\Models\MarketingSuggestionExecution;
use App\Services\FlashSaleService;
use App\Services\Marketing\Suggestions\ApplyResult;
use App\Services\Orders\PriceResolver;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Offers = flash sales (MCP.md §17, docs/CALCULATIONS.md "Flash sale"). No target = NEW flash sale, created INACTIVE unless the apply option
 * `activate` is ticked (it then runs from starts_at, i.e. scheduled). Target flash sale = update name / window / items (FlashSaleService keeps
 * `sold`). Guard: flash price (PriceResolver::flashPrice semantics) below the item's cost ⇒ refused (negative margin); unknown cost / no real
 * discount / the product already in another overlapping active flash sale ⇒ warnings.
 */
class OfferSuggestionType extends AbstractSuggestionType
{
    private const FIELDS = ['name', 'starts_at', 'ends_at', 'items'];

    protected array $targetModels = ['flash_sale' => FlashSale::class];

    public function __construct(private FlashSaleService $sales, private PriceResolver $prices) {}

    public function key(): SuggestionTypeKey
    {
        return SuggestionTypeKey::OFFER;
    }

    public function applyOptions(): array
    {
        return ['activate' => ['kind' => 'bool', 'label' => 'activate_offer']];
    }

    public function rules(?string $targetType = null): array
    {
        return $this->localeRules('name', 200) + [
            'payload' => ['required', 'array'],
            'payload.starts_at' => ['nullable', 'date'],
            'payload.ends_at' => ['nullable', 'date', 'after:payload.starts_at'],
            'payload.items' => ['nullable', 'array', 'min:1', 'max:100'],
            'payload.items.*.product_id' => ['required', 'integer', 'min:1'],
            'payload.items.*.variant_id' => ['nullable', 'integer', 'min:0'],
            'payload.items.*.discount_type' => ['required', Rule::in(FlashDiscountType::values())],
            'payload.items.*.value' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'payload.items.*.qty_limit' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function normalize(array $payload, ?string $targetType = null): array
    {
        $items = [];
        foreach ((array) ($payload['items'] ?? []) as $i) {
            $k = (int) ($i['product_id'] ?? 0).'-'.(int) ($i['variant_id'] ?? 0);
            $items[$k] = ['product_id' => (int) ($i['product_id'] ?? 0), 'variant_id' => (int) ($i['variant_id'] ?? 0), 'discount_type' => (string) ($i['discount_type'] ?? 'percent'),
                'value' => $this->money($i['value'] ?? 0), 'qty_limit' => isset($i['qty_limit']) && $i['qty_limit'] !== '' ? (int) $i['qty_limit'] : null];
        }
        ksort($items);

        return array_filter(['name' => $this->plainLocales($payload['name'] ?? null), 'starts_at' => $this->dt($payload['starts_at'] ?? null),
            'ends_at' => $this->dt($payload['ends_at'] ?? null), 'items' => array_values($items)], fn ($v) => $v !== null && $v !== []);
    }

    public function check(array $payload, ?Model $target, ?MarketingSuggestion $s = null): array
    {
        $errors = [];
        $warnings = [];
        $e = array_replace($target instanceof FlashSale ? $this->current($target) : [], $payload);
        if (! $target) {
            foreach (['name', 'starts_at', 'ends_at', 'items'] as $f) {
                if (! ($payload[$f] ?? null)) {
                    $errors['payload.'.$f] = __('validation.required', ['attribute' => $this->label($f)]);
                }
            }
        } elseif ($payload === []) {
            $errors['payload'] = __('marketing.suggestions.errors.empty_change');
        }
        if (($e['starts_at'] ?? null) && ($e['ends_at'] ?? null) && $e['ends_at'] <= $e['starts_at']) {
            $errors['payload.ends_at'] = __('validation.after', ['attribute' => $this->label('ends_at'), 'date' => $this->label('starts_at')]);
        }
        if (($e['ends_at'] ?? null) && $e['ends_at'] < now()->format('Y-m-d H:i:s')) {
            $errors['payload.ends_at'] = __('marketing.suggestions.errors.in_past');
        }
        foreach ((array) ($e['items'] ?? []) as $n => $i) {
            $item = $this->prices->item($i['product_id'], $i['variant_id']);
            if (! $item) {
                $errors["payload.items.$n.product_id"] = __('ops.invalid_item');

                continue;
            }
            if ($i['discount_type'] === 'percent' && (float) $i['value'] > 100) {
                $errors["payload.items.$n.value"] = __('validation.max.numeric', ['attribute' => $this->label('value'), 'max' => 100]);

                continue;
            }
            $flash = $this->flashPrice($i, $item['price']);
            if (Money::cmp($flash, $item['price']) >= 0) {
                $warnings[] = __('marketing.suggestions.warnings.no_discount', ['item' => $item['name']]);
            }
            if (Money::cmp($item['cost'], '0') <= 0) {
                $warnings[] = __('marketing.suggestions.warnings.item_cost_unknown', ['item' => $item['name']]);
            } elseif (Money::cmp($flash, $item['cost']) < 0) {
                $errors["payload.items.$n.value"] = __('marketing.suggestions.errors.flash_below_cost', ['item' => $item['name'], 'price' => $flash, 'cost' => $item['cost']]);
            }
            if (($e['starts_at'] ?? null) && ($e['ends_at'] ?? null)) {
                $overlap = FlashSaleItem::query()->where('product_id', $i['product_id'])->whereIn('product_variant_id', array_unique([0, $i['variant_id']]))
                    ->whereHas('flashSale', fn ($q) => $q->where('is_active', true)->where('starts_at', '<', $e['ends_at'])->where('ends_at', '>', $e['starts_at'])
                        ->when($target, fn ($w) => $w->where('id', '!=', $target->getKey())))->exists();
                $overlap && $warnings[] = __('marketing.suggestions.warnings.flash_overlap', ['item' => $item['name']]);
            }
        }

        return ['errors' => $errors, 'warnings' => array_values(array_unique($warnings))];
    }

    /** Same semantics as PriceResolver::flashPrice (percent off the regular price, or a fixed price; never above regular). */
    private function flashPrice(array $i, string $regular): string
    {
        $p = $i['discount_type'] === FlashDiscountType::PERCENT->value ? Money::sub($regular, Money::percent($regular, (string) $i['value'])) : Money::round((string) $i['value']);

        return Money::min($p, $regular);
    }

    public function snapshot(?Model $target, array $payload): ?array
    {
        return $target instanceof FlashSale ? array_intersect_key($this->current($target), $payload) : null;
    }

    public function compare(MarketingSuggestion $s, ?Model $target): array
    {
        $cur = $target instanceof FlashSale ? $this->current($target) : [];
        $rows = collect(self::FIELDS)->filter(fn ($f) => array_key_exists($f, (array) $s->payload))->map(fn ($f) => $this->row($f, $cur[$f] ?? null, $s->payload[$f]))->values()->all();
        foreach ((array) ($s->payload['items'] ?? []) as $i) {
            if ($item = $this->prices->item($i['product_id'], $i['variant_id'])) {
                $flash = $this->flashPrice($i, $item['price']);
                $margin = Money::cmp($item['cost'], '0') > 0 && (float) $flash > 0 ? round(((float) $flash - (float) $item['cost']) / (float) $flash * 100, 2).'%' : '—';
                $rows[] = $this->row('price.'.$i['product_id'].'-'.$i['variant_id'], $item['price'], $flash.' ('.__('marketing.suggestions.fields.margin').' '.$margin.')', $item['name']);
            }
        }

        return $rows;
    }

    public function apply(MarketingSuggestion $s, ?Model $target, array $options, ?int $adminId): ApplyResult
    {
        $p = (array) $s->payload;
        $this->ensure($check = $this->check($p, $target, $s));
        $activate = (bool) ($options['activate'] ?? false);
        if ($target instanceof FlashSale) {
            $before = $this->current($target);
            $e = array_replace($before, array_intersect_key($p, array_flip(self::FIELDS)));
            $this->sales->update($target, new FlashSaleData($e['name'], $e['starts_at'], $e['ends_at'], $activate || $before['is_active'], $e['items']));

            return new ApplyResult($before, $this->current($target->fresh()), 'flash_sale', $target->id, $check['warnings']);
        }
        $sale = $this->sales->create(new FlashSaleData($p['name'], $p['starts_at'], $p['ends_at'], $activate, $p['items']));

        return new ApplyResult(['created' => false], ['created' => true, 'flash_sale_id' => $sale->id] + $this->current($sale->fresh()), 'flash_sale', $sale->id, $check['warnings']);
    }

    public function supportsRollback(): bool
    {
        return true;
    }

    public function rollback(MarketingSuggestion $s, MarketingSuggestionExecution $exec, ?int $adminId): array
    {
        $after = (array) $exec->after;
        if (! empty($after['created'])) {
            $sale = FlashSale::query()->find($after['flash_sale_id']);
            if ($sale && (int) $sale->items()->sum('sold') === 0) {
                $this->sales->delete($sale);

                return ['deleted_flash_sale_id' => $sale->id];
            }
            if ($sale) {   // units already sold at the flash price: keep the history, switch it off
                $c = $this->current($sale);
                $this->sales->update($sale, new FlashSaleData($c['name'], $c['starts_at'], $c['ends_at'], false, $c['items']));
            }

            return ['deactivated_flash_sale_id' => $after['flash_sale_id']];
        }
        $sale = FlashSale::query()->findOrFail($s->target_entity_id);
        $b = (array) $exec->before;
        $this->sales->update($sale, new FlashSaleData($b['name'], $b['starts_at'], $b['ends_at'], (bool) $b['is_active'], $b['items']));

        return $this->current($sale->fresh());
    }

    public function editFields(?string $targetType = null): array
    {
        return [
            $this->field('payload.name', 'locales-text'),
            $this->field('payload.starts_at', 'datetime'),
            $this->field('payload.ends_at', 'datetime'),
            $this->field('payload.items', 'json', null, ['hint' => 'offer_items_hint']),
        ];
    }

    private function current(FlashSale $f): array
    {
        $items = $f->items()->get()->map(fn ($i) => ['product_id' => (int) $i->product_id, 'variant_id' => (int) $i->product_variant_id,
            'discount_type' => $i->discount_type instanceof FlashDiscountType ? $i->discount_type->value : (string) $i->discount_type, 'value' => $this->money($i->value),
            'qty_limit' => $i->qty_limit !== null ? (int) $i->qty_limit : null])->sortBy(fn ($i) => $i['product_id'].'-'.$i['variant_id'])->values()->all();

        return ['name' => $this->translations($f, 'name'), 'starts_at' => $f->starts_at?->format('Y-m-d H:i:s'), 'ends_at' => $f->ends_at?->format('Y-m-d H:i:s'),
            'items' => $items, 'is_active' => (bool) $f->is_active];
    }
}
