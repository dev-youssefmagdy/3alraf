<?php

namespace App\DTOs;

use App\Http\Requests\Tenant\TenantProductRequest;

final readonly class TenantProductData
{
    /** @param array<int,array{id:int,price:?string,sale_price:?string,cost:?string,is_active:bool}> $variants */
    public function __construct(
        public string $price,
        public ?string $salePrice,
        public string $cost,
        public bool $isActive,
        public array $variants = [],
        public ?int $minStockAlert = null,
    ) {}

    public static function fromRequest(TenantProductRequest $r): self
    {
        $v = $r->validated();
        $n = fn ($x) => ($x === null || $x === '') ? null : (string) $x;
        $variants = collect($v['variants'] ?? [])->map(fn ($row) => [
            'id' => (int) $row['id'], 'price' => $n($row['price'] ?? null), 'sale_price' => $n($row['sale_price'] ?? null),
            'cost' => $n($row['cost'] ?? null), 'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOL),
        ])->values()->all();

        return new self((string) $v['price'], $n($v['sale_price'] ?? null), (string) $v['cost'], $r->boolean('is_active'), $variants, $n($v['min_stock_alert'] ?? null) === null ? null : (int) $v['min_stock_alert']);
    }
}
