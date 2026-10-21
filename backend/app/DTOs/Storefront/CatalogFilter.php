<?php

namespace App\DTOs\Storefront;

use Illuminate\Http\Request;

/** Listing / search filters coming from the query string. */
final readonly class CatalogFilter
{
    public const SORTS = ['newest', 'price_asc', 'price_desc', 'best_selling', 'top_rated'];

    /** @param int[] $brands @param int[] $values attribute value ids */
    public function __construct(
        public ?string $q = null, public array $brands = [], public array $values = [], public array $tags = [],
        public ?string $priceMin = null, public ?string $priceMax = null, public string $sort = 'newest', public int $perPage = 24,
    ) {}

    public static function fromRequest(Request $r): self
    {
        $num = fn ($v) => ($v !== null && $v !== '' && is_numeric($v) && $v >= 0) ? (string) $v : null;
        $ids = fn ($v) => array_values(array_unique(array_filter(array_map('intval', (array) $v))));
        $sort = (string) $r->query('sort', 'newest');

        return new self(
            ($q = trim((string) $r->query('q', ''))) !== '' ? mb_substr($q, 0, 100) : null,
            $ids($r->query('brands')), $ids($r->query('values')), $ids($r->query('tags')),
            $num($r->query('price_min')), $num($r->query('price_max')),
            in_array($sort, self::SORTS, true) ? $sort : 'newest',
            max(1, min(60, (int) ($r->query('per_page') ?: 24))),
        );
    }
}
