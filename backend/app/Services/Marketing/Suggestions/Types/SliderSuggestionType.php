<?php

namespace App\Services\Marketing\Suggestions\Types;

use App\DTOs\BannerData;
use App\Enums\BannerPlacement;
use App\Enums\Marketing\SuggestionTypeKey;
use App\Models\Banner;
use App\Models\MarketingSuggestion;
use App\Models\MarketingSuggestionExecution;
use App\Services\BannerService;
use App\Services\ImageUploadService;
use App\Services\Marketing\Suggestions\ApplyResult;
use App\Services\Marketing\Suggestions\SuggestionException;
use App\Services\Marketing\Suggestions\SuggestionImages;
use App\Support\Marketing\InternalLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Homepage slider / banners (MCP.md §17). No target = NEW banner, created INACTIVE (it has no image unless the suggestion carries one: otherwise the owner uploads it in
Banners, or proposed as `image` {url}: an https URL downloaded once at create / edit time or an upload://{sha256} staged by
 * POST banners/image-suggestion, see SuggestionImages: sha256-bound, private disk, replaced files kept for rollback). Target banner = update title / link / placement / window / sort; the active flag is kept
 * unless the apply option `activate` is ticked, which is refused for a banner without images. Links must be internal (InternalLink). BannerService.
 */
class SliderSuggestionType extends AbstractSuggestionType
{
    private const FIELDS = ['placement', 'title', 'link_url', 'starts_at', 'ends_at', 'sort_order', 'image'];

    protected array $targetModels = ['banner' => Banner::class];

    public function __construct(private BannerService $banners, private SuggestionImages $images, private ImageUploadService $uploads) {}

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
            'payload.image' => ['nullable', 'array'],
            'payload.image.url' => ['required_with:payload.image', 'string', 'max:2048', 'regex:#^(https?|upload)://#i'],
            // filled by normalize() (staged file); accepted back unchanged when a reviewer edits the payload
            'payload.image.sha256' => ['nullable', 'string'], 'payload.image.mime' => ['nullable', 'string'], 'payload.image.width' => ['nullable', 'integer'],
            'payload.image.height' => ['nullable', 'integer'], 'payload.image.bytes' => ['nullable', 'integer'], 'payload.image.error' => ['nullable', 'string'],
        ];
    }

    public function normalize(array $payload, ?string $targetType = null): array
    {
        $url = is_array($payload['image'] ?? null) ? trim((string) ($payload['image']['url'] ?? '')) : '';

        return array_filter(['image' => $url !== '' ? $this->images->stage($url) : null, 'placement' => BannerPlacement::tryFrom((string) ($payload['placement'] ?? ''))?->value, 'title' => $this->plainLocales($payload['title'] ?? null),
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

        if (isset($payload['image'])) {
            $img = $payload['image'];
            if (isset($img['error'])) {
                $errors['payload.image.url'] = __('marketing.suggestions.errors.image_fetch', ['url' => Str::limit((string) $img['url'], 80), 'reason' => __('marketing.suggestions.image_errors.'.$img['error'])]);
            } elseif (! $this->images->exists($img)) {
                $errors['payload.image.url'] = __('marketing.suggestions.errors.image_missing');
            }
        }

        return ['errors' => $errors, 'warnings' => $target || isset($payload['image']) ? [] : [__('marketing.suggestions.warnings.banner_needs_image')]];
    }

    public function snapshot(?Model $target, array $payload): ?array
    {
        return $target instanceof Banner ? array_intersect_key($this->current($target), $payload) : null;
    }

    public function compare(MarketingSuggestion $s, ?Model $target): array
    {
        $cur = $target instanceof Banner ? $this->current($target) : [];

        $rows = collect(self::FIELDS)->reject(fn ($f) => $f === 'image')->filter(fn ($f) => array_key_exists($f, (array) $s->payload))
            ->map(fn ($f) => $this->row($f, $cur[$f] ?? null, $s->payload[$f]))->values()->all();
        if (isset($s->payload['image'])) {
            $img = $s->payload['image'];
            $current = $target instanceof Banner && $target->imageUrl() ? [['src' => $target->imageUrl(), 'url' => $target->imageUrl(), 'size' => '']] : null;
            $proposed = [isset($img['sha256'])
                ? ['src' => route('marketer.suggestions.image', ['sha' => $img['sha256']]), 'url' => $img['url'], 'size' => ($img['width'] ?? '?').'×'.($img['height'] ?? '?')]
                : ['src' => null, 'url' => $img['url'] ?? '', 'size' => __('marketing.suggestions.image_errors.'.($img['error'] ?? 'missing'))]];
            $rows[] = $this->row('image', $current, $proposed) + ['images' => true];
        }

        return $rows;
    }

    public function apply(MarketingSuggestion $s, ?Model $target, array $options, ?int $adminId): ApplyResult
    {
        $p = (array) $s->payload;
        $this->ensure($check = $this->check($p, $target, $s));
        $activate = (bool) ($options['activate'] ?? false);
        $file = isset($p['image']) ? $this->images->toUploadedFile($p['image'], 'banner-'.Str::lower(Str::random(6))) : null;
        if ($target instanceof Banner) {
            if ($activate && ! $file && ! array_filter((array) $target->images)) {
                throw SuggestionException::make('banner_without_image', 422);
            }
            $before = $this->current($target);
            $e = array_replace($before, array_intersect_key($p, array_flip(self::FIELDS)));
            $this->banners->update($target, $this->dto($e, $activate || $before['is_active']));
            $newPath = null;
            if ($file) {   // stored without deleting the old files, so rollback can put them back
                $newPath = $this->uploads->storeSingle($file, 'banners');
                $target->forceFill(['images' => ['ar' => $newPath]])->save();
            }

            return new ApplyResult($before, $this->current($target->fresh()) + ($newPath ? ['new_image_path' => $newPath] : []), 'banner', $target->id, $check['warnings']);
        }
        if ($activate && ! $file) {
            throw SuggestionException::make('banner_without_image', 422);
        }
        $e = $p + ['title' => [], 'link_url' => null, 'starts_at' => null, 'ends_at' => null, 'sort_order' => 0];
        $b = $this->banners->create($this->dto($e, $activate, $file ? ['ar' => $file] : []));

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
        if (! empty($after['new_image_path'])) {
            $b->forceFill(['images' => (array) ($before['image'] ?? [])])->save();
            $this->uploads->deletePath($after['new_image_path']);
        }

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
            $this->field('payload.image', 'json', null, ['hint' => 'banner_image_hint']),
        ];
    }

    /** @param  array<string,UploadedFile>  $images  locale => file (new banners only: BannerService stores it) */
    private function dto(array $e, bool $active, array $images = []): BannerData
    {
        return new BannerData(BannerPlacement::from($e['placement']), (array) $e['title'], $images, [], $e['link_url'] ?? null, $e['starts_at'] ?? null, $e['ends_at'] ?? null,
            (int) ($e['sort_order'] ?? 0), $active);
    }

    private function current(Banner $b): array
    {
        return ['placement' => $b->placement?->value, 'title' => $this->translations($b, 'title'), 'link_url' => $b->link_url, 'starts_at' => $b->starts_at?->format('Y-m-d H:i:s'),
            'ends_at' => $b->ends_at?->format('Y-m-d H:i:s'), 'sort_order' => (int) $b->sort_order, 'is_active' => (bool) $b->is_active, 'has_image' => (bool) array_filter((array) $b->images), 'image' => (array) $b->images];
    }
}
