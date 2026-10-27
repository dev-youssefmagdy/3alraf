<?php

namespace App\DTOs\Orders;

final readonly class ReturnRequestData
{
    /** @param array<int,int> $quantities order_item_id => qty (>0) */
    public function __construct(public array $quantities, public ?string $reason, public bool $restock, public bool $refundShipping, public ?int $branchId = null, public string $returnShippingCost = '0', public ?string $reasonCode = null) {}

    public static function fromArray(array $in): self
    {
        $q = collect($in['items'] ?? [])->mapWithKeys(fn ($r, $id) => [(int) ($r['order_item_id'] ?? $id) => (int) ($r['quantity'] ?? 0)])->filter(fn ($v) => $v > 0)->all();

        return new self($q, $in['reason'] ?? null, (bool) ($in['restock'] ?? true), (bool) ($in['refund_shipping'] ?? false),
            isset($in['branch_id']) && $in['branch_id'] !== '' ? (int) $in['branch_id'] : null, (string) (($in['return_shipping_cost'] ?? '') ?: '0'), ($in['reason_code'] ?? '') !== '' ? (string) $in['reason_code'] : null);
    }
}
