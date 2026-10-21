<?php

namespace App\DTOs;

use App\Enums\BannerPlacement;
use App\Http\Requests\Tenant\BannerRequest;

final readonly class BannerData
{
    /** @param array<string,\Illuminate\Http\UploadedFile> $images locale => file ; @param string[] $removeLocales */
    public function __construct(public BannerPlacement $placement, public array $title, public array $images, public array $removeLocales, public ?string $linkUrl,
        public ?string $startsAt, public ?string $endsAt, public int $sortOrder, public bool $isActive) {}

    public static function fromRequest(BannerRequest $r): self
    {
        return new self(
            BannerPlacement::from($r->input('placement')), $r->cleanTranslations($r->input('title')),
            array_filter((array) $r->file('images')), array_keys(array_filter((array) $r->input('remove_images'))),
            $r->input('link_url'), $r->input('starts_at') ?: null, $r->input('ends_at') ?: null, (int) $r->input('sort_order', 0), $r->boolean('is_active'),
        );
    }

    public function toArray(): array
    {
        return ['placement' => $this->placement, 'title' => $this->title, 'link_url' => $this->linkUrl, 'starts_at' => $this->startsAt, 'ends_at' => $this->endsAt,
            'sort_order' => $this->sortOrder, 'is_active' => $this->isActive];
    }
}
