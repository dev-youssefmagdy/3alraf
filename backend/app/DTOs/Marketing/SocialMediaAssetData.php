<?php

namespace App\DTOs\Marketing;

use App\Support\Marketing\CanvaUrl;
use App\Support\Marketing\StoreText;
use Carbon\CarbonImmutable;

/**
 * Metadata of a private social-media library item (P6). Built from a validated request / suggestion payload; every text is reduced to plain
 * text (untrusted AI / user input), tags lower-cased + unique, platforms restricted to the known list, Canva links allow-listed.
 * `$sent` = the keys that were present (partial updates only touch those).
 */
final readonly class SocialMediaAssetData
{
    public const PLATFORMS = ['facebook', 'instagram', 'instagram_story', 'reels', 'tiktok', 'whatsapp', 'other'];

    public const FORMATS = ['post', 'story', 'reel', 'carousel', 'video', 'ad'];

    public const RATIOS = ['1:1', '4:5', '9:16', '16:9', 'other'];

    public function __construct(
        public ?string $kind,
        public ?string $title,
        public array $caption,
        public array $platforms,
        public ?string $format,
        public ?string $aspectRatio,
        public ?string $purpose,
        public ?string $campaign,
        public array $tags,
        public array $hashtags,
        public ?string $cta,
        public ?string $brief,
        public ?string $suggestedPublishAt,
        public ?string $canvaUrl,
        public ?int $durationSeconds,
        public ?array $generationMeta,
        public ?string $note,
        public array $sent = [],
    ) {}

    public static function fromArray(array $v): self
    {
        $plain = fn ($x, int $max) => is_scalar($x) && ($s = trim((string) StoreText::plain((string) $x))) !== '' ? mb_substr($s, 0, $max) : null;
        $caption = [];
        foreach (StoreText::locales() as $l) {
            if (($c = $plain(is_array($v['caption'] ?? null) ? ($v['caption'][$l] ?? null) : null, 2200)) !== null) {
                $caption[$l] = $c;
            }
        }
        $tags = array_values(array_unique(array_filter(array_map(fn ($t) => ($t = $plain($t, 60)) !== null ? mb_strtolower($t) : null,
            is_string($v['tags'] ?? null) ? explode(',', $v['tags']) : (array) ($v['tags'] ?? [])))));
        $hashtags = array_values(array_unique(array_filter(array_map(function ($h) {
            $h = is_string($h) ? trim(StoreText::plain($h) ?? '', "# \t") : '';

            return $h !== '' && preg_match('/^[\pL\pN_]+$/u', $h) ? '#'.mb_substr($h, 0, 60) : null;
        }, is_string($v['hashtags'] ?? null) ? preg_split('/[\s,]+/', $v['hashtags']) : (array) ($v['hashtags'] ?? [])))));
        $at = null;
        if (! empty($v['suggested_publish_at'])) {
            try {
                $at = CarbonImmutable::parse((string) $v['suggested_publish_at'])->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                $at = null;
            }
        }
        $gen = is_array($v['generation_meta'] ?? null) ? array_slice(array_map(fn ($x) => is_scalar($x) ? mb_substr((string) $x, 0, 2000) : null, $v['generation_meta']), 0, 20, true) : null;

        return new self(
            in_array($v['kind'] ?? null, ['image', 'video', 'post'], true) ? $v['kind'] : null,
            $plain($v['title'] ?? null, 200),
            $caption,
            array_values(array_intersect(self::PLATFORMS, array_map('strval', (array) ($v['platforms'] ?? [])))),
            in_array($v['format'] ?? null, self::FORMATS, true) ? $v['format'] : null,
            in_array($v['aspect_ratio'] ?? null, self::RATIOS, true) ? $v['aspect_ratio'] : null,
            $plain($v['purpose'] ?? null, 500),
            $plain($v['campaign'] ?? null, 120),
            array_slice($tags, 0, 20),
            array_slice($hashtags, 0, 30),
            $plain($v['cta'] ?? null, 200),
            $plain($v['brief'] ?? null, 5000),
            $at,
            CanvaUrl::normalize($v['canva_url'] ?? null),
            isset($v['duration_seconds']) && $v['duration_seconds'] !== '' && $v['duration_seconds'] !== null ? max(0, (int) $v['duration_seconds']) : null,
            $gen ? array_filter($gen, fn ($x) => $x !== null) : null,
            $plain($v['note'] ?? null, 500),
            array_keys($v),
        );
    }

    /** Column values (only the sent keys when $partial). */
    public function columns(bool $partial = false): array
    {
        $map = ['title' => $this->title, 'caption' => $this->caption ?: null, 'platforms' => $this->platforms ?: null, 'format' => $this->format,
            'aspect_ratio' => $this->aspectRatio, 'purpose' => $this->purpose, 'campaign' => $this->campaign, 'tags' => $this->tags ?: null,
            'hashtags' => $this->hashtags ?: null, 'cta' => $this->cta, 'brief' => $this->brief, 'suggested_publish_at' => $this->suggestedPublishAt,
            'canva_url' => $this->canvaUrl, 'duration_seconds' => $this->durationSeconds, 'generation_meta' => $this->generationMeta];

        return $partial ? array_intersect_key($map, array_flip($this->sent)) : $map;
    }
}
