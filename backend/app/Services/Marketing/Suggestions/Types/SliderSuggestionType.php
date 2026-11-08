<?php

namespace App\Services\Marketing\Suggestions\Types;

use App\DTOs\BannerData;
use App\Enums\BannerPlacement;
use App\Enums\Marketing\SuggestionTypeKey;
use App\Models\Banner;
use App\Models\MarketingSuggestion;
use App\Models\MarketingSuggestionExecution;
use App\Services\BannerService;
use App\Services\Marketing\Suggestions\ApplyResult;
use App\Services\Marketing\Suggestions\SuggestionException;
use App\Support\Marketing\InternalLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Homepage slider / banners (MCP.md §17). No target = NEW banner, created INACTIVE (it has no image yet: images are uploaded by the owner in
 * Banners; suggestions never carry files or image URLs). Target banner = update title / link / placement / window / sort; the active flag is kept
 * unless the apply option `activate` is ticked, which is refused for a banner without images. Links must be internal (InternalLink). BannerService.
 */
class SliderSuggestionType extends AbstractSuggestionType
{
    private const FIELDS = ['placement', 'title', 'link_url', 'starts_at', 'ends_at', 'sort_order'];

    protected array $targetModels = ['banner' => Banner::class];

    public function __construct(private BannerService $banners) {}

    public function key(): SuggestionTypeKey
    {
        return SuggestionTypeKey::SLIDER;
    }

    public function applyOptions(): array
    {
        return ['activate' => ['kind' => 'bool', 'label' => 'activate_banner']];
    }

    public function rules(?string $targetType = null): array
    {
        return $this->localeRules('title', 200) + [
            'payload' => ['required', 'array'],
            'payload.placement' => ['nullable', Rule::enum(BannerPlacement::class)],
            'payload.link_url' => ['nullable', 'string', 'max:500', fn ($a, $v, $fail) => InternalLink::isInternal($v) ? null : $fail(__('marketing.suggestions.errors.internal_link'))],
            'payload.starts_at' => ['nullable', 'date'],
            'payload.ends_at' => ['nullable', 'date', 'after:payload.starts_at'],
            'payload.sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'payload.design_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function normalize(array $payload, ?string $targetType = null): array
    {
        return array_filter(['placement' => BannerPlacement::tryFrom((string) ($payload['placement'] ?? ''))?->value, 'title' => $this->plainLocales($payload['title'] ?? null),
            'link_url' => InternalLink::normalize(is_string($payload['link_url'] ?? null) ? $payload['link_url'] : null), 'starts_at' => $this->dt($payload['starts_at'] ?? null),
            'ends_at' => $this->dt($payload['ends_at'] ?? null), 'sort_order' => isset($payload['sort_order']) && $payload['sort_order'] !== '' ? (int) $payload['sort_order'] : null,
            'design_notes' => $this->plain($payload['design_notes'] ?? null, 2000)], fn ($v) => $v !== null && $v !== []);
    }

    public function check(array $payload, ?Model $target, ?MarketingSuggestion $s = null): array
    {
        $errors = [];
        if (! $target && ! isset($payload['placement'])) {
            $errors['payload.placement'] = __('validation.required', ['attribute' => $this->label('placement')]);
        }
        if (! array_intersect_key($payload, array_flip(self::FIELDS))) {
            $errors['payload'] = __('marketing.suggestions.errors.empty_change');
        }

        return ['errors' => $errors, 'warnings' => $target ? [] : [__('marketing.suggestions.warnings.banner_needs_image')]];
    }

    public function snapshot(?Model $target, array $payload): ?array
    {
        return $target instanceof Banner ? array_intersect_key($this->current($target), $payload) : null;
    }

    public function compare(MarketingSuggestion $s, ?Model $target): array
    {
        $cur = $target instanceof Banner ? $this->current($target) : [];

        return collect(self::FIELDS)->filter(fn ($f) => array_key_exists($f, (array) $s->payload))->map(fn ($f) => $this->row($f, $cur[$f] ?? null, $s->payload[$f]))->values()->all();
    }

    public function apply(MarketingSuggestion $s, ?Model $target, array $options, ?int $adminId): ApplyResult
    {
        $p = (array) $s->payload;
        $this->ensure($check = $this->check($p, $target, $s));
        $activate = (bool) ($options['activate'] ?? false);
        if ($target instanceof Banner) {
            if ($activate && ! array_filter((array) $target->images)) {
                throw SuggestionException::make('banner_without_image', 422);
            }
            $before = $this->current($target);
            $e = array_replace($before, array_intersect_key($p, array_flip(self::FIELDS)));
            $this->banners->update($target, $this->dto($e, $activate || $before['is_active']));

            return new ApplyResult($before, $this->current($target->fresh()), 'banner', $target->id, $check['warnings']);
        }
        if ($activate) {
            throw SuggestionException::make('banner_without_image', 422);
        }
        $e = $p + ['title' => [], 'link_url' => null, 'starts_at' => null, 'ends_at' => null, 'sort_order' => 0];
        $b = $this->banners->create($this->dto($e, false));

        return new ApplyResult(['created' => false], ['created' => true, 'banner_id' => $b->id] + $this->current($b->fresh()), 'banner', $b->id, $check['warnings']);
    }

    public function supportsRollback(): bool
    {
        return true;
    }

    public function rollback(MarketingSuggestion $s, MarketingSuggestionExecution $exec, ?int $adminId): array
    {
        $after = (array) $exec->after;
        if (! empty($after['created'])) {
            $b = Banner::query()->find($after['banner_id']);
            $b && $this->banners->delete($b);

            return ['deleted_banner_id' => $after['banner_id']];
        }
        $b = Banner::query()->findOrFail($s->target_entity_id);
        $before = (array) $exec->before;
        $this->banners->update($b, $this->dto($before, (bool) $before['is_active']));

        return $this->current($b->fresh());
    }

    public function editFields(?string $targetType = null): array
    {
        return [
            $this->field('payload.placement', 'select', null, ['options' => BannerPlacement::values()]),
            $this->field('payload.title', 'locales-text'),
            $this->field('payload.link_url', 'text', null, ['dir' => 'ltr']),
            $this->field('payload.starts_at', 'datetime'),
            $this->field('payload.ends_at', 'datetime'),
            $this->field('payload.sort_order', 'number'),
            $this->field('payload.design_notes', 'textarea'),
        ];
    }

    private function dto(array $e, bool $active): BannerData
    {
        return new BannerData(BannerPlacement::from($e['placement']), (array) $e['title'], [], [], $e['link_url'] ?? null, $e['starts_at'] ?? null, $e['ends_at'] ?? null,
            (int) ($e['sort_order'] ?? 0), $active);
    }

    private function current(Banner $b): array
    {
        return ['placement' => $b->placement?->value, 'title' => $this->translations($b, 'title'), 'link_url' => $b->link_url, 'starts_at' => $b->starts_at?->format('Y-m-d H:i:s'),
            'ends_at' => $b->ends_at?->format('Y-m-d H:i:s'), 'sort_order' => (int) $b->sort_order, 'is_active' => (bool) $b->is_active, 'has_image' => (bool) array_filter((array) $b->images)];
    }
}
