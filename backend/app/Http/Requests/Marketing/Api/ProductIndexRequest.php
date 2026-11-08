<?php

namespace App\Http\Requests\Marketing\Api;

/** GET products: category (id, incl. sub-categories), brand (id), active, in_stock, search (name ar/en, sku), sort. */
class ProductIndexRequest extends MarketingQueryRequest
{
    public const SORTS = ['newest', 'oldest', 'name', 'price_asc', 'price_desc', 'stock_asc', 'stock_desc'];

    protected function extraRules(): array
    {
        return [
            'category' => ['sometimes', 'integer', 'min:1'],
            'brand' => ['sometimes', 'integer', 'min:1'],
            'active' => ['sometimes', 'boolean'],
            'in_stock' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sort' => ['sometimes', 'in:'.implode(',', self::SORTS)],
        ];
    }
}
