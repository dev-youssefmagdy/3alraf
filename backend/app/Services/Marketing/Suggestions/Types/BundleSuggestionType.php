<?php

namespace App\Services\Marketing\Suggestions\Types;

use App\DTOs\BundleData;
use App\Enums\Marketing\SuggestionTypeKey;
use App\Models\Bundle;
use App\Models\MarketingSuggestion;
use App\Models\MarketingSuggestionExecution;
use App\Services\BundleService;
use App\Services\Marketing\Suggestions\ApplyResult;
use App\Services\Marketing\Suggestions\EntityDtos;
use App\Services\Orders\PriceResolver;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * Bundles (MCP.md §17). No target = create a NEW bundle, always inactive (the owner switches it on in Bundles); target bundle = update its
 * name / description / price / items / window / sort (only the proposed fields; image and active flag kept). Through BundleService.
 * Margin (docs/CALCULATIONS.md "Marketing analytics"): (price − Σ component cost × qty) / price; negative ⇒ refused, below
 * config marketing.suggestions.min_margin_percent ⇒ warning, unknown (a component without cost) ⇒ warning. Components must be listed.
 */
class BundleSuggestionType extends AbstractSuggestionType
{
    private const FIELDS = ['name', 'description', 'price', 'items', 'starts_at', 'ends_at', 'sort_order'];

    protected array $targetModels = ['bundle' => Bundle::class];

    public function __construct(private BundleService $bundles, private PriceResolver $prices) {}

    public function key(): SuggestionTypeKey
    {
        return SuggestionTypeKey::BUNDLE;
    }

    public function rules(?string $targetType = null): array
    {
        return $this->localeRules('name', 200) + $this->localeRules('description', 5000) + [
            'payload' => ['required', 'array'],
            'payload.price' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'payload.items' => ['nullable', 'array', 'min:1', 'max:50'],
            'payload.items.*.product_id' => ['required', 'integer', 'min:1'],
            'payload.items.*.variant_id' => ['nullable', 'integer', 'min:0'],
            'payload.items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'payload.starts_at' => ['nullable', 'date'],
            'payload.ends_at' => ['nullable', 'date', 'after:payload.starts_at'],
            'payload.sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function normalize(array $payload, ?string $targetType = null): array
    {
        $items = [];
        foreach ((array) ($payload['items'] ?? []) as $i) {
            $k = (int) ($i['product_id'] ?? 0).'-'.(int) ($i['variant_id'] ?? 0);
            $items[$k] = ['product_id' => (int) ($i['product_id'] ?? 0), 'variant_id' => (int) ($i['variant_id'] ?? 0), 'quantity' => ($items[$k]['quantity'] ?? 0) + (int) ($i['quantity'] ?? 1)];
        }
        ksort($items);

        return array_filter(['name' => $this->plainLocales($payload['name'] ?? null), 'description' => $this->plainLocales($payload['description'] ?? null),
            'price' => $this->money($payload['price'] ?? null), 'items' => array_values($items), 'starts_at' => $this->dt($payload['starts_at'] ?? null),
            'ends_at' => $this->dt($payload['ends_at'] ?? null), 'sort_order' => isset($payload['sort_order']) && $payload['sort_order'] !== '' ? (int) $payload['sort_order'] : null],
            fn ($v) => $v !== null && $v !== []);
    }

    /** Proposed values merged over the current bundle (update) or alone (create). */
    private function effective(array $p, ?Bundle $b): array
    {
        $cur = $b ? $this->current($b) : [];

        return array_replace($cur, array_intersect_key($p, array_flip(self::FIELDS)));
    }

    public function check(array $payload, ?Model $target, ?MarketingSuggestion $s = null): array
    {
        $errors = [];
        $warnings = [];
        $e = $this->effective($payload, $target instanceof Bundle ? $target : null);
        if (! $target) {
            if (! ($payload['name'] ?? [])) {
                $errors['payload.name'] = __('validation.required', ['attribute' => $this->label('name')]);
            }
            if (! isset($payload['price'])) {
                $errors['payload.price'] = __('validation.required', ['attribute' => $this->label('price')]);
            }
            if (! ($payload['items'] ?? [])) {
                $errors['payload.items'] = __('validation.required', ['attribute' => $this->label('items')]);
            }
        } elseif ($payload === []) {
            $errors['payload'] = __('marketing.suggestions.errors.empty_change');
        }
        if ($errors || ! ($e['items'] ?? []) || ! isset($e['price'])) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }
        $regular = '0';
        $cost = '0';
        $costKnown = true;
        foreach ($e['items'] as $n => $i) {
            $item = $this->prices->item($i['product_id'], $i['variant_id']);
            if (! $item) {
                $errors["payload.items.$n.product_id"] = __('orders.bundle.component_inactive');

                continue;
            }
            $regular = Money::add($regular, Money::mul($item['price'], (string) $i['quantity']));
            if (Money::cmp($item['cost'], '0') <= 0) {
                $costKnown = false;
            }
            $cost = Money::add($cost, Money::mul($item['cost'], (string) $i['quantity']));
        }
        if ($errors) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }
        if (Money::cmp($e['price'], $regular) > 0) {
            $warnings[] = __('marketing.suggestions.warnings.bundle_above_regular', ['price' => $e['price'], 'regular' => $regular]);
        }
        if (! $costKnown) {
            $warnings[] = __('marketing.suggestions.warnings.margin_unknown');
        } else {
            $margin = round(((float) $e['price'] - (float) $cost) / (float) $e['price'] * 100, 2);
            if ($margin < 0) {
                $errors['payload.price'] = __('marketing.suggestions.errors.negative_margin', ['margin' => $margin]);
            } elseif ($margin < (float) config('marketing.suggestions.min_margin_percent', 10)) {
                $warnings[] = __('marketing.suggestions.warnings.low_margin', ['margin' => $margin]);
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    public function snapshot(?Model $target, array $payload): ?array
    {
        return $target instanceof Bundle ? array_intersect_key($this->current($target), $payload) : null;
    }

    public function compare(MarketingSuggestion $s, ?Model $target): array
    {
        $cur = $target instanceof Bundle ? $this->current($target) : [];
        $rows = [];
        foreach (self::FIELDS as $f) {
            if (array_key_exists($f, (array) $s->payload)) {
                $rows[] = $this->row($f, $cur[$f] ?? null, $s->payload[$f]);
            }
        }

        return $rows;
    }

    public function apply(MarketingSuggestion $s, ?Model $target, array $options, ?int $adminId): ApplyResult
    {
        $p = (array) $s->payload;
        $this->ensure($check = $this->check($p, $target, $s));
        if ($target instanceof Bundle) {
            $before = $this->current($target);
            $this->bundles->update($target, EntityDtos::bundle($target, array_intersect_key($p, array_flip(self::FIELDS))));

            return new ApplyResult($before, $this->current($target->fresh()), 'bundle', $target->id, $check['warnings']);
        }
        $b = $this->bundles->create(new BundleData($p['name'], $p['description'] ?? [], $p['price'], $p['starts_at'] ?? null, $p['ends_at'] ?? null,
            (int) ($p['sort_order'] ?? 0), false, $p['items'], null, false));

        return new ApplyResult(['created' => false], ['created' => true, 'bundle_id' => $b->id] + $this->current($b->fresh()), 'bundle', $b->id, $check['warnings']);
    }

    public function supportsRollback(): bool
    {
        return true;
    }

    public function rollback(MarketingSuggestion $s, MarketingSuggestionExecution $exec, ?int $adminId): array
    {
        $after = (array) $exec->after;
        if (! empty($after['created'])) {
            $b = Bundle::query()->find($after['bundle_id']);
            $b && $this->bundles->delete($b);   // soft delete (order lines may reference it)

            return ['deleted_bundle_id' => $after['bundle_id']];
        }
        $b = Bundle::query()->findOrFail($s->target_entity_id);
        $this->bundles->update($b, EntityDtos::bundle($b, array_intersect_key((array) $exec->before, array_flip(self::FIELDS))));

        return $this->current($b->fresh());
    }

    public function editFields(?string $targetType = null): array
    {
        return [
            $this->field('payload.name', 'locales-text'),
            $this->field('payload.description', 'locales-textarea'),
            $this->field('payload.price', 'number', null, ['step' => '0.01']),
            $this->field('payload.items', 'json', null, ['hint' => 'items_hint']),
            $this->field('payload.starts_at', 'datetime'),
            $this->field('payload.ends_at', 'datetime'),
            $this->field('payload.sort_order', 'number'),
        ];
    }

    private function current(Bundle $b): array
    {
        $items = $b->items()->get()->map(fn ($i) => ['product_id' => (int) $i->product_id, 'variant_id' => (int) $i->product_variant_id, 'quantity' => (int) $i->quantity])
            ->sortBy(fn ($i) => $i['product_id'].'-'.$i['variant_id'])->values()->all();

        return ['name' => $this->translations($b, 'name'), 'description' => $this->translations($b, 'description'), 'price' => $this->money($b->price), 'items' => $items,
            'starts_at' => $b->starts_at?->format('Y-m-d H:i:s'), 'ends_at' => $b->ends_at?->format('Y-m-d H:i:s'), 'sort_order' => (int) $b->sort_order, 'is_active' => (bool) $b->is_active];
    }
}
