<?php

namespace App\Services;

use App\DTOs\PurchaseOrderData;
use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Models\PurchaseOrder;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Services\Stock\StockLedger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Purchase orders: draft -> received (stock_in at line cost, weighted avg per CALCULATIONS.md) | cancelled. */
class PurchaseService
{
    public function __construct(private PurchaseOrderRepositoryInterface $orders, private StockLedger $ledger) {}

    public function find(int|string $id): PurchaseOrder
    {
        return $this->orders->query()->with(['items.product', 'items.variant.values', 'supplier', 'branch'])->findOrFail($id);
    }

    public function create(PurchaseOrderData $dto): PurchaseOrder
    {
        return DB::transaction(function () use ($dto) {
            $po = $this->orders->create($this->header($dto) + ['status' => PurchaseStatus::DRAFT, 'created_by' => auth('tenant')->id()]);
            $po->update(['number' => sprintf('PO-%06d', $po->id)]);
            $this->syncItems($po, $dto);
            if ($dto->receive) {
                $this->receive($po);
            }

            return $po;
        });
    }

    public function update(PurchaseOrder $po, PurchaseOrderData $dto): PurchaseOrder
    {
        $this->ensureDraft($po);

        return DB::transaction(function () use ($po, $dto) {
            $this->orders->update($po, $this->header($dto));
            $this->syncItems($po, $dto);
            if ($dto->receive) {
                $this->receive($po);
            }

            return $po;
        });
    }

    public function receive(PurchaseOrder $po): PurchaseOrder
    {
        $this->ensureDraft($po);

        return DB::transaction(function () use ($po) {
            foreach ($po->items()->get() as $i) {
                $this->ledger->record($po->branch_id, $i->product_id, $i->product_variant_id, StockMovementType::PURCHASE_IN, $i->quantity, (string) $i->unit_cost, $po, $po->number);
            }

            return $this->orders->update($po, ['status' => PurchaseStatus::RECEIVED, 'received_at' => now()]);
        });
    }

    public function cancel(PurchaseOrder $po): PurchaseOrder
    {
        $this->ensureDraft($po);

        return $this->orders->update($po, ['status' => PurchaseStatus::CANCELLED]);
    }

    public function delete(PurchaseOrder $po): void
    {
        if ($po->status === PurchaseStatus::RECEIVED) {
            throw ValidationException::withMessages(['record' => __('ops.document_locked')]);
        }
        $this->orders->delete($po);
    }

    private function ensureDraft(PurchaseOrder $po): void
    {
        if (! $po->isDraft()) {
            throw ValidationException::withMessages(['record' => __('ops.document_locked')]);
        }
    }

    private function header(PurchaseOrderData $dto): array
    {
        $items = array_reduce($dto->items, fn ($c, $i) => bcadd($c, Money::mul($i['quantity'], $i['unit_cost']), 2), '0.00');

        return [
            'supplier_id' => $dto->supplierId, 'branch_id' => $dto->branchId, 'ordered_at' => $dto->orderedAt, 'note' => $dto->note,
            'shipping_cost' => $dto->shippingCost, 'other_cost' => $dto->otherCost,
            'items_total' => $items, 'total_cost' => Money::add($items, $dto->shippingCost, $dto->otherCost),
        ];
    }

    private function syncItems(PurchaseOrder $po, PurchaseOrderData $dto): void
    {
        $po->items()->delete();
        foreach ($dto->items as $i) {
            $po->items()->create([
                'product_id' => $i['product_id'], 'product_variant_id' => $i['variant_id'], 'quantity' => $i['quantity'],
                'unit_cost' => $i['unit_cost'], 'line_total' => Money::mul($i['quantity'], $i['unit_cost']),
            ]);
        }
    }
}
