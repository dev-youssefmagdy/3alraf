<?php

namespace App\DTOs;

use App\Http\Requests\Tenant\CustomPageRequest;

final readonly class CustomPageData
{
    /** @param int[] $productIds manually pinned products in order */
    public function __construct(public string $slug, public array $title, public array $filters, public array $productIds, public bool $isActive) {}

    public static function fromRequest(CustomPageRequest $r): self
    {
        $f = $r->input('filters', []);
        $ids = fn ($v) => array_values(array_unique(array_filter(array_map('intval', (array) $v))));
        $filters = array_filter([
            'categories' => $ids($f['categories'] ?? []), 'brands' => $ids($f['brands'] ?? []), 'attribute_values' => $ids($f['attribute_values'] ?? []),
            'price_min' => ($f['price_min'] ?? '') === '' ? null : (string) $f['price_min'],
            'price_max' => ($f['price_max'] ?? '') === '' ? null : (string) $f['price_max'],
            'flash_sale' => ! empty($f['flash_sale']),
            'sort' => $f['sort'] ?? 'newest',
        ], fn ($v) => $v !== null && $v !== [] && $v !== false);

        return new self(strtolower(trim($r->input('slug'))), $r->cleanTranslations($r->input('title')), $filters, $ids($r->input('product_ids', [])), $r->boolean('is_active'));
    }

    public function toArray(): array
    {
        return ['slug' => $this->slug, 'title' => $this->title, 'filters' => $this->filters, 'is_active' => $this->isActive];
    }
}
