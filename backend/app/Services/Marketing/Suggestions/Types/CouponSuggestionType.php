<?php

namespace App\Services\Marketing\Suggestions\Types;

use App\DTOs\DiscountCodeData;
use App\Enums\DiscountScope;
use App\Enums\DiscountType;
use App\Enums\Marketing\SuggestionTypeKey;
use App\Models\Category;
use App\Models\DiscountCode;
use App\Models\FlashSaleItem;
use App\Models\MarketingSuggestion;
use App\Models\MarketingSuggestionExecution;
use App\Models\Product;
use App\Services\DiscountCodeService;
use App\Services\Marketing\Suggestions\ApplyResult;
use App\Services\Orders\PriceResolver;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Coupons = discount codes (MCP.md §17, docs/CALCULATIONS.md order totals step "discount"). No target = NEW code, created INACTIVE unless the apply
 * option `activate` is ticked; target = update an existing code (usage counters untouched). Through DiscountCodeService.
 * Margin guard (worst case = a one-unit order of a product in scope that reaches min_order):
 *   percent: (price − min(price × value%, max_discount)) < cost ⇒ refused;  fixed: price ≥ min_order and (price − min(value, price)) < cost ⇒ refused.
 *   Stacking: a product in scope with an active flash sale overlapping the coupon window is checked at its flash price (flash + coupon combine).
 *   Products without cost are skipped with a warning. Code must be unique among live codes.
 */
class CouponSuggestionType extends AbstractSuggestionType
{
    private const FIELDS = ['code', 'type', 'value', 'min_order', 'max_discount', 'usage_limit', 'per_customer_limit', 'starts_at', 'ends_at', 'scope', 'product_ids', 'category_ids', 'free_shipping'];

    protected array $targetModels = ['discount_code' => DiscountCode::class];

    public function __construct(private DiscountCodeService $codes, private PriceResolver $prices) {}

    public function key(): SuggestionTypeKey
    {
        return SuggestionTypeKey::COUPON;
    }

    public function applyOptions(): array
    {
        return ['activate' => ['kind' => 'bool', 'label' => 'activate_coupon']];
    }

    public function rules(?string $targetType = null): array
    {
        return [
            'payload' => ['required', 'array'],
            'payload.code' => ['nullable', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'payload.type' => ['nullable', Rule::in(DiscountType::values())],
            'payload.value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'payload.min_order' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'payload.max_discount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'payload.usage_limit' => ['nullable', 'integer', 'min:1'],
            'payload.per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'payload.starts_at' => ['nullable', 'date'],
            'payload.ends_at' => ['nullable', 'date', 'after:payload.starts_at'],
            'payload.scope' => ['nullable', Rule::in(DiscountScope::values())],
            'payload.product_ids' => ['nullable', 'array', 'max:200'], 'payload.product_ids.*' => ['integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'payload.category_ids' => ['nullable', 'array', 'max:100'], 'payload.category_ids.*' => ['integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'payload.free_shipping' => ['nullable', 'boolean'],
        ];
    }

    public function normalize(array $payload, ?string $targetType = null): array
    {
        $n = fn ($k) => isset($payload[$k]) && $payload[$k] !== '' ? (int) $payload[$k] : null;
        $out = array_filter([
            'code' => is_string($payload['code'] ?? null) && trim($payload['code']) !== '' ? strtoupper(trim($payload['code'])) : null,
            'type' => DiscountType::tryFrom((string) ($payload['type'] ?? ''))?->value, 'value' => $this->money($payload['value'] ?? null),
            'min_order' => $this->money($payload['min_order'] ?? null), 'max_discount' => $this->money($payload['max_discount'] ?? null),
            'usage_limit' => $n('usage_limit'), 'per_customer_limit' => $n('per_customer_limit'),
            'starts_at' => $this->dt($payload['starts_at'] ?? null), 'ends_at' => $this->dt($payload['ends_at'] ?? null),
            'scope' => DiscountScope::tryFrom((string) ($payload['scope'] ?? ''))?->value,
            'product_ids' => $this->intList($payload['product_ids'] ?? []), 'category_ids' => $this->intList($payload['category_ids'] ?? []),
        ], fn ($v) => $v !== null && $v !== []);
        if (array_key_exists('free_shipping', $payload) && $payload['free_shipping'] !== null && $payload['free_shipping'] !== '') {
            $out['free_shipping'] = filter_var($payload['free_shipping'], FILTER_VALIDATE_BOOL);
        }
        ksort($out);

        return $out;
    }

    private function effective(array $p, ?DiscountCode $t): array
    {
        $base = $t ? $this->current($t) : ['type' => 'percent', 'value' => '0.00', 'min_order' => '0.00', 'max_discount' => null, 'usage_limit' => null,
            'per_customer_limit' => null, 'starts_at' => null, 'ends_at' => null, 'scope' => 'all', 'product_ids' => [], 'category_ids' => [], 'free_shipping' => false, 'code' => null];

        return array_replace($base, array_intersect_key($p, array_flip(self::FIELDS)));
    }

    public function check(array $payload, ?Model $target, ?MarketingSuggestion $s = null): array
    {
        $errors = [];
        $warnings = [];
        $e = $this->effective($payload, $target instanceof DiscountCode ? $target : null);
        if (! $target) {
            foreach (['code', 'type'] as $f) {
                if (! isset($payload[$f])) {
                    $errors['payload.'.$f] = __('validation.required', ['attribute' => $this->label($f)]);
                }
            }
            if (! isset($payload['value']) && empty($payload['free_shipping'])) {
                $errors['payload.value'] = __('validation.required', ['attribute' => $this->label('value')]);
            }
        } elseif ($payload === []) {
            $errors['payload'] = __('marketing.suggestions.errors.empty_change');
        }
        if ($e['code'] && DiscountCode::query()->where('code', $e['code'])->when($target, fn ($q) => $q->where('id', '!=', $target->getKey()))->exists()) {
            $errors['payload.code'] = __('marketing.suggestions.errors.code_taken');
        }
        if ($e['type'] === 'percent' && (float) $e['value'] > 100) {
            $errors['payload.value'] = __('validation.max.numeric', ['attribute' => $this->label('value'), 'max' => 100]);
        }
        if ($e['scope'] === 'products' && ! $e['product_ids']) {
            $errors['payload.product_ids'] = __('validation.required', ['attribute' => $this->label('product_ids')]);
        }
        if ($e['scope'] === 'categories' && ! $e['category_ids']) {
            $errors['payload.category_ids'] = __('validation.required', ['attribute' => $this->label('category_ids')]);
        }
        if ($e['ends_at'] && $e['ends_at'] < now()->format('Y-m-d H:i:s')) {
            $errors['payload.ends_at'] = __('marketing.suggestions.errors.in_past');
        }
        if ($errors) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }
        if ($e['free_shipping']) {
            return ['errors' => [], 'warnings' => [__('marketing.suggestions.warnings.free_shipping_cost')]];
        }
        [$negative, $unknown] = $this->marginScan($e);
        if ($negative) {
            $errors['payload.value'] = __('marketing.suggestions.errors.coupon_below_cost', ['n' => count($negative), 'items' => implode('، ', array_slice($negative, 0, 5))]);
        }
        $unknown && $warnings[] = __('marketing.suggestions.warnings.coupon_cost_unknown', ['n' => $unknown]);
        $e['usage_limit'] === null && $e['ends_at'] === null && $warnings[] = __('marketing.suggestions.warnings.coupon_unbounded');

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /** @return array{0: list<string>, 1: int} [names of products that would sell below cost, count of products without cost] */
    private function marginScan(array $e): array
    {
        $q = Product::query()->with('variants.values')->where('is_active', true)->whereNotNull('price');
        if ($e['scope'] === 'products') {
            $q->whereIn('id', $e['product_ids']);
        } elseif ($e['scope'] === 'categories') {
            $cats = Category::query()->whereIn('id', $e['category_ids'])->get();
            $ids = Category::query()->where(function ($w) use ($cats) {
                foreach ($cats as $c) {
                    $w->orWhere('id', $c->id)->orWhere('path', 'like', $c->path.'/%');
                }
            })->pluck('id');
            $q->whereHas('categories', fn ($w) => $w->whereIn('categories.id', $ids));
        }
        $from = $e['starts_at'] ?? now()->format('Y-m-d H:i:s');
        $to = $e['ends_at'] ?? '9999-12-31 23:59:59';
        $negative = [];
        $unknown = 0;
        $q->chunkById(200, function ($products) use ($e, $from, $to, &$negative, &$unknown) {
            foreach ($products as $p) {
                $item = $this->prices->itemFor($p, $p->isVariable() ? (int) ($p->variants->firstWhere('is_active', true)?->id ?? 0) : 0);
                if (! $item) {
                    continue;
                }
                if (Money::cmp($item['cost'], '0') <= 0) {
                    $unknown++;

                    continue;
                }
                $price = $item['price'];
                $flash = FlashSaleItem::query()->where('product_id', $p->id)->whereHas('flashSale', fn ($f) => $f->where('is_active', true)->where('starts_at', '<', $to)->where('ends_at', '>', $from))->first();
                if ($flash) {
                    $price = $this->prices->flashPrice($flash, $price);
                }
                if ($e['type'] === 'fixed' && Money::cmp($price, $e['min_order']) < 0) {
                    continue;   // a one-unit order of this product does not reach min_order
                }
                $disc = $e['type'] === 'percent' ? Money::percent($price, (string) $e['value']) : Money::min((string) $e['value'], $price);
                if ($e['max_discount'] !== null) {
                    $disc = Money::min($disc, (string) $e['max_discount']);
                }
                if (Money::cmp(Money::sub($price, $disc), $item['cost']) < 0) {
                    $negative[] = $item['name'];
                }
            }
        });

        return [$negative, $unknown];
    }

    public function snapshot(?Model $target, array $payload): ?array
    {
        return $target instanceof DiscountCode ? array_intersect_key($this->current($target), $payload) : null;
    }

    public function compare(MarketingSuggestion $s, ?Model $target): array
    {
        $cur = $target instanceof DiscountCode ? $this->current($target) : [];

        return collect(self::FIELDS)->filter(fn ($f) => array_key_exists($f, (array) $s->payload))->map(fn ($f) => $this->row($f, $cur[$f] ?? null, $s->payload[$f]))->values()->all();
    }

    public function apply(MarketingSuggestion $s, ?Model $target, array $options, ?int $adminId): ApplyResult
    {
        $p = (array) $s->payload;
        $this->ensure($check = $this->check($p, $target, $s));
        $activate = (bool) ($options['activate'] ?? false);
        if ($target instanceof DiscountCode) {
            $before = $this->current($target);
            $this->codes->update($target, $this->dto($this->effective($p, $target), $activate || $before['is_active']));

            return new ApplyResult($before, $this->current($target->fresh()), 'discount_code', $target->id, $check['warnings']);
        }
        $code = $this->codes->create($this->dto($this->effective($p, null), $activate));

        return new ApplyResult(['created' => false], ['created' => true, 'discount_code_id' => $code->id] + $this->current($code->fresh()), 'discount_code', $code->id, $check['warnings']);
    }

    public function supportsRollback(): bool
    {
        return true;
    }

    public function rollback(MarketingSuggestion $s, MarketingSuggestionExecution $exec, ?int $adminId): array
    {
        $after = (array) $exec->after;
        if (! empty($after['created'])) {
            $code = DiscountCode::query()->find($after['discount_code_id']);
            if ($code && (int) $code->used_count === 0) {
                $this->codes->delete($code);

                return ['deleted_discount_code_id' => $code->id];
            }
            $code && $this->codes->update($code, $this->dto($this->current($code), false));

            return ['deactivated_discount_code_id' => $after['discount_code_id']];
        }
        $code = DiscountCode::query()->findOrFail($s->target_entity_id);
        $b = (array) $exec->before;
        $this->codes->update($code, $this->dto($b, (bool) $b['is_active']));

        return $this->current($code->fresh());
    }

    public function editFields(?string $targetType = null): array
    {
        return [
            $this->field('payload.code', 'text', null, ['dir' => 'ltr']),
            $this->field('payload.type', 'select', null, ['options' => DiscountType::values()]),
            $this->field('payload.value', 'number', null, ['step' => '0.01']),
            $this->field('payload.min_order', 'number', null, ['step' => '0.01']),
            $this->field('payload.max_discount', 'number', null, ['step' => '0.01']),
            $this->field('payload.usage_limit', 'number'),
            $this->field('payload.per_customer_limit', 'number'),
            $this->field('payload.starts_at', 'datetime'),
            $this->field('payload.ends_at', 'datetime'),
            $this->field('payload.scope', 'select', null, ['options' => DiscountScope::values()]),
            $this->field('payload.product_ids', 'id-list'),
            $this->field('payload.category_ids', 'id-list'),
            $this->field('payload.free_shipping', 'bool'),
        ];
    }

    private function dto(array $e, bool $active): DiscountCodeData
    {
        return new DiscountCodeData((string) $e['code'], (string) $e['type'], (string) ($e['value'] ?? '0'), (string) ($e['min_order'] ?? '0'), $e['max_discount'] !== null ? (string) $e['max_discount'] : null,
            $e['usage_limit'], $e['per_customer_limit'], $e['starts_at'], $e['ends_at'], (string) $e['scope'],
            $e['scope'] === 'products' ? (array) $e['product_ids'] : [], $e['scope'] === 'categories' ? (array) $e['category_ids'] : [], (bool) $e['free_shipping'], $active);
    }

    private function current(DiscountCode $c): array
    {
        return ['code' => $c->code, 'type' => $c->type?->value, 'value' => $this->money($c->value), 'min_order' => $this->money($c->min_order ?? 0),
            'max_discount' => $this->money($c->max_discount), 'usage_limit' => $c->usage_limit !== null ? (int) $c->usage_limit : null,
            'per_customer_limit' => $c->per_customer_limit !== null ? (int) $c->per_customer_limit : null, 'starts_at' => $c->starts_at?->format('Y-m-d H:i:s'),
            'ends_at' => $c->ends_at?->format('Y-m-d H:i:s'), 'scope' => $c->scope?->value, 'product_ids' => $this->intList($c->product_ids ?? []),
            'category_ids' => $this->intList($c->category_ids ?? []), 'free_shipping' => (bool) $c->free_shipping, 'is_active' => (bool) $c->is_active];
    }
}
