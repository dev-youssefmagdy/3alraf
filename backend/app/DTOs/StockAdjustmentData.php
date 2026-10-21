<?php

namespace App\DTOs;

use App\Enums\AdjustmentReason;
use App\Enums\AdjustmentType;
use App\Http\Requests\Tenant\StockAdjustmentRequest;

final readonly class StockAdjustmentData
{
    public function __construct(public int $branchId, public AdjustmentType $type, public AdjustmentReason $reason, public ?string $note, public array $items) {}

    public static function fromRequest(StockAdjustmentRequest $r): self
    {
        return new self((int) $r->input('branch_id'), AdjustmentType::from($r->input('type')), AdjustmentReason::from($r->input('reason')), $r->input('note'), $r->stockItems());
    }
}
