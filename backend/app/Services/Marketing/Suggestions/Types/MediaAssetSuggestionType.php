<?php

namespace App\Services\Marketing\Suggestions\Types;

use App\Enums\Marketing\SuggestionTypeKey;
use App\Models\MarketingSuggestion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** Product media asset brief (image / video to create for social use). Apply (P6) = private draft item in the product's social-media library. */
class MediaAssetSuggestionType extends AbstractPendingSocialType
{
    public const KINDS = ['image', 'video'];

    public const RATIOS = ['1:1', '4:5', '9:16', '16:9'];

    public function key(): SuggestionTypeKey
    {
        return SuggestionTypeKey::MEDIA_ASSET;
    }

    public function rules(?string $targetType = null): array
    {
        return $this->localeRules('title', 200) + [
            'payload' => ['required', 'array'],
            'payload.asset_kind' => ['required', Rule::in(self::KINDS)],
            'payload.platform' => ['nullable', Rule::in(self::PLATFORMS)],
            'payload.aspect_ratio' => ['nullable', Rule::in(self::RATIOS)],
            'payload.duration_seconds' => ['nullable', 'integer', 'min:1', 'max:600'],
            'payload.brief' => ['required', 'string', 'max:3000'],
            'payload.purpose' => ['nullable', 'string', 'max:500'],
            'payload.tags' => ['nullable', 'array', 'max:20'], 'payload.tags.*' => ['string', 'max:60'],
            'payload.canva_url' => $this->canvaRule(),
        ];
    }

    public function normalize(array $payload, ?string $targetType = null): array
    {
        return array_filter(['asset_kind' => $payload['asset_kind'] ?? null, 'title' => $this->plainLocales($payload['title'] ?? null), 'platform' => $payload['platform'] ?? null,
            'aspect_ratio' => $payload['aspect_ratio'] ?? null, 'duration_seconds' => isset($payload['duration_seconds']) && $payload['duration_seconds'] !== '' ? (int) $payload['duration_seconds'] : null,
            'brief' => $this->plain($payload['brief'] ?? null, 3000), 'purpose' => $this->plain($payload['purpose'] ?? null, 500),
            'tags' => array_values(array_unique(array_filter(array_map(fn ($t) => $this->plain($t, 60), (array) ($payload['tags'] ?? []))))),
            'canva_url' => self::canvaUrl($payload['canva_url'] ?? null)], fn ($v) => $v !== null && $v !== []);
    }

    protected function libraryFields(array $p, MarketingSuggestion $s): array
    {
        $title = $p['title'] ?? [];

        return ['title' => ($title[app()->getLocale()] ?? null) ?: (collect($title)->first() ?: $s->title), 'platforms' => array_filter([$p['platform'] ?? null]),
            'aspect_ratio' => $p['aspect_ratio'] ?? null, 'duration_seconds' => $p['duration_seconds'] ?? null, 'purpose' => $p['purpose'] ?? null,
            'brief' => trim(__('marketing.media.brief_kind', ['kind' => __('marketing.media.kinds.'.($p['asset_kind'] ?? 'image'))])."\n".($p['brief'] ?? '')),
            'tags' => $p['tags'] ?? [], 'canva_url' => $p['canva_url'] ?? null,
            'generation_meta' => ['suggestion_id' => $s->id, 'asset_kind' => $p['asset_kind'] ?? null, 'source' => $s->source?->value]];
    }

    public function compare(MarketingSuggestion $s, ?Model $target): array
    {
        return collect((array) $s->payload)->map(fn ($v, $k) => $this->row($k, null, $v))->values()->all();
    }

    public function editFields(?string $targetType = null): array
    {
        return [
            $this->field('payload.asset_kind', 'select', null, ['options' => self::KINDS]),
            $this->field('payload.title', 'locales-text'),
            $this->field('payload.platform', 'select', null, ['options' => self::PLATFORMS, 'nullable' => true]),
            $this->field('payload.aspect_ratio', 'select', null, ['options' => self::RATIOS, 'nullable' => true]),
            $this->field('payload.duration_seconds', 'number'),
            $this->field('payload.brief', 'textarea'),
            $this->field('payload.purpose', 'text'),
            $this->field('payload.tags', 'list'),
            $this->field('payload.canva_url', 'text', null, ['dir' => 'ltr']),
        ];
    }
}
