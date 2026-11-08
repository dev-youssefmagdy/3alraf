<?php

namespace App\Services\Marketing\Suggestions\Types;

use App\Enums\Marketing\SuggestionTypeKey;
use App\Models\MarketingSuggestion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** Product social post idea (caption, hashtags, CTA, media brief). Apply (P6) = private post draft in the product's social-media library (never posted). */
class SocialContentSuggestionType extends AbstractPendingSocialType
{
    public const FORMATS = ['post', 'story', 'reel', 'carousel', 'video'];

    public function key(): SuggestionTypeKey
    {
        return SuggestionTypeKey::SOCIAL_CONTENT;
    }

    public function rules(?string $targetType = null): array
    {
        return $this->localeRules('caption', 2200, true) + [
            'payload' => ['required', 'array'],
            'payload.platform' => ['required', Rule::in(self::PLATFORMS)],
            'payload.format' => ['required', Rule::in(self::FORMATS)],
            'payload.hashtags' => ['nullable', 'array', 'max:30'], 'payload.hashtags.*' => ['string', 'max:60', 'regex:/^#?[\pL\pN_]+$/u'],
            'payload.cta' => ['nullable', 'string', 'max:200'],
            'payload.media_brief' => ['nullable', 'string', 'max:2000'],
            'payload.campaign' => ['nullable', 'string', 'max:120'],
            'payload.suggested_publish_at' => ['nullable', 'date'],
            'payload.canva_url' => $this->canvaRule(),
        ];
    }

    public function normalize(array $payload, ?string $targetType = null): array
    {
        $tags = array_values(array_unique(array_filter(array_map(fn ($h) => is_string($h) && trim($h, "# \t") !== '' ? '#'.trim($h, "# \t") : null, (array) ($payload['hashtags'] ?? [])))));

        return array_filter(['platform' => $payload['platform'] ?? null, 'format' => $payload['format'] ?? null, 'caption' => $this->plainLocales($payload['caption'] ?? null),
            'hashtags' => $tags, 'cta' => $this->plain($payload['cta'] ?? null, 200), 'media_brief' => $this->plain($payload['media_brief'] ?? null, 2000),
            'campaign' => $this->plain($payload['campaign'] ?? null, 120), 'suggested_publish_at' => $this->dt($payload['suggested_publish_at'] ?? null),
            'canva_url' => self::canvaUrl($payload['canva_url'] ?? null)], fn ($v) => $v !== null && $v !== []);
    }

    protected function libraryFields(array $p, MarketingSuggestion $s): array
    {
        return ['title' => $s->title, 'caption' => $p['caption'] ?? [], 'platforms' => array_filter([$p['platform'] ?? null]),
            'format' => in_array($p['format'] ?? null, ['post', 'story', 'reel', 'carousel', 'video'], true) ? $p['format'] : null,
            'hashtags' => $p['hashtags'] ?? [], 'cta' => $p['cta'] ?? null, 'brief' => $p['media_brief'] ?? null, 'campaign' => $p['campaign'] ?? null,
            'suggested_publish_at' => $p['suggested_publish_at'] ?? null, 'canva_url' => $p['canva_url'] ?? null,
            'generation_meta' => ['suggestion_id' => $s->id, 'source' => $s->source?->value]];
    }

    public function compare(MarketingSuggestion $s, ?Model $target): array
    {
        return collect((array) $s->payload)->map(fn ($v, $k) => $this->row($k, null, $v))->values()->all();
    }

    public function editFields(?string $targetType = null): array
    {
        return [
            $this->field('payload.platform', 'select', null, ['options' => self::PLATFORMS]),
            $this->field('payload.format', 'select', null, ['options' => self::FORMATS]),
            $this->field('payload.caption', 'locales-textarea'),
            $this->field('payload.hashtags', 'list'),
            $this->field('payload.cta', 'text'),
            $this->field('payload.media_brief', 'textarea'),
            $this->field('payload.campaign', 'text'),
            $this->field('payload.suggested_publish_at', 'datetime'),
            $this->field('payload.canva_url', 'text', null, ['dir' => 'ltr']),
        ];
    }
}
