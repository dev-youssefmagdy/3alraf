<?php

namespace App\DTOs;

use App\Http\Requests\Tenant\StockTransferRequest;

final readonly class StockTransferData
{
    public function __construct(public int $fromBranchId, public int $toBranchId, public ?string $note, public array $items) {}

    public static function fromRequest(StockTransferRequest $r): self
    {
        return new self((int) $r->input('from_branch_id'), (int) $r->input('to_branch_id'), $r->input('note'), $r->stockItems());
    }
}
