<?php

namespace App\Services\Stock;

use App\Enums\StockMovementType;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The ONLY place that changes stock. Every change appends a stock_movements row and updates stock_levels
 * (on_hand, weighted avg_cost) atomically. Rules: docs/CALCULATIONS.md §Stock.
 */
class StockLedger
{
    /**
     * @param  int  $qty  positive magnitude; direction comes from $type
     * @param  string|null  $unitCost  incoming cost (in-types); outgoing rows always use the current average
     */
    public function record(int $branchId, int $productId, int $variantId, StockMovementType $type, int $qty, ?string $unitCost = null, ?Model $reference = null, ?string $note = null, ?int $userId = null): StockMovement
    {
        if ($qty <= 0) {
            throw new \InvalidArgumentException('Quantity must be positive.');
        }

        return DB::transaction(function () use ($branchId, $productId, $variantId, $type, $qty, $unitCost, $reference, $note, $userId) {
            $this->assertBranch($branchId);
            $level = $this->lockedLevel($branchId, $productId, $variantId);

            $onHand = (int) $level->on_hand;
            $avg = (string) $level->avg_cost;
            $signed = $type->sign() * $qty;

            if ($signed < 0 && $onHand + $signed < 0 && ! $this->allowsBackorder()) {
                throw ValidationException::withMessages(['items' => __('ops.insufficient_stock', ['available' => $onHand, 'qty' => $qty])]);
            }

            if ($type->recalculatesAverage()) {
                $cost = $unitCost ?? $avg;
                $newAvg = $onHand <= 0 ? Money::round($cost) : $this->weightedAverage($onHand, $avg, $qty, $cost);
            } else {
                $cost = $avg; // outgoing valued at current average; average unchanged
                $newAvg = $avg;
            }

            $level->forceFill(['on_hand' => $onHand + $signed, 'avg_cost' => $newAvg])->save();

            $movement = new StockMovement([
                'branch_id' => $branchId, 'product_id' => $productId, 'product_variant_id' => $variantId, 'type' => $type, 'quantity' => $signed,
                'unit_cost' => Money::round($cost), 'balance_after' => $onHand + $signed, 'avg_cost_after' => $newAvg, 'note' => $note,
                'created_by' => $userId ?? auth('admin')->id(),
            ]);
            if ($reference) {
                $movement->reference_type = $reference->getMorphClass();
                $movement->reference_id = $reference->getKey();
            }
            $movement->save();

            if ($type === StockMovementType::PURCHASE_IN) {
                $this->syncListingCost($productId, $variantId);
            }

            return $movement;
        });
    }

    /** new_avg = (on_hand×avg + qty×cost) / (on_hand+qty), rounded half-up once at the end. */
    public function weightedAverage(int $onHand, string $avg, int $qty, string $cost): string
    {
        $num = bcadd(bcmul((string) $onHand, $avg, 8), bcmul((string) $qty, $cost, 8), 8);

        return Money::round(bcdiv($num, (string) ($onHand + $qty), 8));
    }

    public function onHand(int $branchId, int $productId, int $variantId = 0): int
    {
        return (int) StockLevel::where(['branch_id' => $branchId, 'product_id' => $productId, 'product_variant_id' => $variantId])->value('on_hand');
    }

    public function avgCost(int $branchId, int $productId, int $variantId = 0): string
    {
        return (string) (StockLevel::where(['branch_id' => $branchId, 'product_id' => $productId, 'product_variant_id' => $variantId])->value('avg_cost') ?? '0.00');
    }

    /** available = on_hand - reserved (CALCULATIONS.md §Stock). */
    public function available(int $branchId, int $productId, int $variantId = 0): int
    {
        $l = StockLevel::where(['branch_id' => $branchId, 'product_id' => $productId, 'product_variant_id' => $variantId])->first();

        return $l ? $l->on_hand - $l->reserved : 0;
    }

    /** Reserve stock for a placed order (no ledger row; on_hand unchanged). Refused when available < qty unless backorder allowed. */
    public function reserve(int $branchId, int $productId, int $variantId, int $qty): void
    {
        DB::transaction(function () use ($branchId, $productId, $variantId, $qty) {
            $level = $this->lockedLevel($branchId, $productId, $variantId);
            $available = $level->on_hand - $level->reserved;
            if ($available < $qty && ! $this->allowsBackorder()) {
                throw ValidationException::withMessages(['items' => __('ops.insufficient_stock', ['available' => max(0, $available), 'qty' => $qty])]);
            }
            $level->forceFill(['reserved' => $level->reserved + $qty])->save();
        });
    }

    public function release(int $branchId, int $productId, int $variantId, int $qty): void
    {
        DB::transaction(function () use ($branchId, $productId, $variantId, $qty) {
            $level = $this->lockedLevel($branchId, $productId, $variantId);
            $level->forceFill(['reserved' => max(0, $level->reserved - $qty)])->save();
        });
    }

    /** Order shipped: deduct on_hand through the ledger (sale_out) and drop the reservation. */
    public function ship(int $branchId, int $productId, int $variantId, int $qty, ?Model $reference = null): StockMovement
    {
        return DB::transaction(function () use ($branchId, $productId, $variantId, $qty, $reference) {
            $m = $this->record($branchId, $productId, $variantId, StockMovementType::SALE_OUT, $qty, null, $reference);
            $this->release($branchId, $productId, $variantId, $qty);

            return $m;
        });
    }

    /**
     * Row-locked stock level. Missing first-ever rows are created with INSERT IGNORE (the unique key makes a concurrent
     * creator harmless) and then re-read FOR UPDATE, so two requests can never 500 on a duplicate insert.
     */
    private function lockedLevel(int $branchId, int $productId, int $variantId): StockLevel
    {
        $key = ['branch_id' => $branchId, 'product_id' => $productId, 'product_variant_id' => $variantId];
        $find = fn () => StockLevel::query()->where($key)->lockForUpdate()->first();

        $level = $find();
        if (! $level) {
            $this->assertBranch($branchId); // reserve()/release() too: an unknown branch must not reach the INSERT IGNORE (FK error swallowed)
            DB::table('stock_levels')->insertOrIgnore($key + ['on_hand' => 0, 'reserved' => 0, 'avg_cost' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $level = $find();
        }

        return $level;
    }

    /** The branch must exist. */
    private function assertBranch(int $branchId): void
    {
        if (! \App\Models\Branch::query()->whereKey($branchId)->exists()) {
            throw new \InvalidArgumentException("Branch {$branchId} does not exist.");
        }
    }

    /** Product (or variant) cost = Σ(on_hand×avg)/Σ on_hand over all branches (what orders snapshot as unit_cost). */
    private function syncListingCost(int $productId, int $variantId): void
    {
        $rows = StockLevel::query()->where(['product_id' => $productId, 'product_variant_id' => $variantId])->where('on_hand', '>', 0)->get(['on_hand', 'avg_cost']);
        $qty = $rows->sum('on_hand');
        if ($qty <= 0) {
            return;
        }
        $value = $rows->reduce(fn ($c, $r) => bcadd($c, bcmul((string) $r->on_hand, (string) $r->avg_cost, 8), 8), '0');
        $cost = Money::round(bcdiv($value, (string) $qty, 8));

        if ($variantId === 0) {
            Product::query()->whereKey($productId)->first()?->forceFill(['cost' => $cost])->save();
        } else {
            ProductVariant::query()->where('product_id', $productId)->whereKey($variantId)->first()?->forceFill(['cost' => $cost])->save();
        }
    }

    private function allowsBackorder(): bool
    {
        return (bool) setting('allow_backorder', false);
    }
}
