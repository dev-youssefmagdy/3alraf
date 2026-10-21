<?php

namespace App\Services;

use App\DTOs\StockAdjustmentData;
use App\Enums\AdjustmentType;
use App\Enums\StockMovementType;
use App\Models\StockAdjustment;
use App\Repositories\Contracts\StockAdjustmentRepositoryInterface;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\DB;

/** Adjustments are applied immediately and are immutable (corrections = new adjustment). */
class StockAdjustmentService
{
    public function __construct(private StockAdjustmentRepositoryInterface $adjustments, private StockLedger $ledger) {}

    public function find(int|string $id): StockAdjustment
    {
        return $this->adjustments->query()->with(['items.product', 'items.variant.values', 'branch'])->findOrFail($id);
    }

    public function create(StockAdjustmentData $dto): StockAdjustment
    {
        return DB::transaction(function () use ($dto) {
            $a = $this->adjustments->create(['branch_id' => $dto->branchId, 'type' => $dto->type, 'reason' => $dto->reason, 'note' => $dto->note, 'created_by' => auth('tenant')->id()]);
            $a->update(['number' => sprintf('ADJ-%06d', $a->id)]);
            $type = $dto->type === AdjustmentType::INCREASE ? StockMovementType::ADJUSTMENT_IN : StockMovementType::ADJUSTMENT_OUT;
            foreach ($dto->items as $i) {
                $m = $this->ledger->record($dto->branchId, $i['product_id'], $i['variant_id'], $type, $i['quantity'], $dto->type === AdjustmentType::INCREASE ? $i['unit_cost'] : null, $a, $a->number.' - '.$dto->reason->label());
                $a->items()->create(['product_id' => $i['product_id'], 'product_variant_id' => $i['variant_id'], 'quantity' => $i['quantity'], 'unit_cost' => $m->unit_cost]);
            }

            return $a;
        });
    }
}
