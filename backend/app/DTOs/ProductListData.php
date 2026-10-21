<?php

namespace App\DTOs;

use App\Enums\ProductListType;
use App\Http\Requests\Tenant\ProductListRequest;

final readonly class ProductListData
{
    /** @param int[] $productIds pinned product ids in display order */
    public function __construct(public ProductListType $type, public array $productIds, public int $limit) {}

    public static function fromRequest(ProductListRequest $r): self
    {
        return new self(ProductListType::from($r->route('type')), array_values(array_unique(array_map('intval', $r->input('product_ids', [])))), (int) $r->input('limit'));
    }
}
