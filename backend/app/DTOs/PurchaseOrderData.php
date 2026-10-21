<?php

namespace App\DTOs;

use App\Http\Requests\Tenant\PurchaseOrderRequest;

final readonly class PurchaseOrderData
{
    public function __construct(public int $supplierId, public int $branchId, public string $orderedAt, public string $shippingCost, public string $otherCost, public ?string $note, public array $items, public bool $receive) {}

    public static function fromRequest(PurchaseOrderRequest $r): self
    {
        return new self((int) $r->input('supplier_id'), (int) $r->input('branch_id'), $r->input('ordered_at'), (string) ($r->input('shipping_cost') ?: 0), (string) ($r->input('other_cost') ?: 0), $r->input('note'), $r->stockItems(), $r->boolean('receive'));
    }
}
