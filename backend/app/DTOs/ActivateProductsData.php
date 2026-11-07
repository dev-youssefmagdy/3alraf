<?php

namespace App\DTOs;

use App\Http\Requests\Admin\ActivateProductsRequest;

/** Catalog "activate products": price + put on sale the selected products that have no price yet. */
final readonly class ActivateProductsData
{
    /** @param array<int,array{product_id:int,price:string,sale_price:?string,cost:string,is_active:bool}> $rows */
    public function __construct(public array $rows) {}

    public static function fromRequest(ActivateProductsRequest $r): self
    {
        $rows = [];
        foreach ($r->input('rows', []) as $productId => $row) {
            if (! filter_var($row['enabled'] ?? false, FILTER_VALIDATE_BOOL)) {
                continue;
            }
            $rows[] = [
                'product_id' => (int) $productId, 'price' => (string) $row['price'],
                'sale_price' => ($row['sale_price'] ?? '') === '' ? null : (string) $row['sale_price'],
                'cost' => (string) $row['cost'], 'is_active' => true,
            ];
        }

        return new self($rows);
    }
}
