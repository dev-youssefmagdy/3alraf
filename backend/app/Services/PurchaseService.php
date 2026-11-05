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

        \App\Services\Erp\Accounting\PeriodGuard::assertOpen(now()->toDateString(), 'record');

        return DB::transaction(function () use ($po) {
            $items = $po->items()->get();
            $costs = $this->landedUnitCosts($po, $items);
            foreach ($items as $i) {
                $this->ledger->record($po->branch_id, $i->product_id, $i->product_variant_id, StockMovementType::PURCHASE_IN, $i->quantity, $costs[$i->id], $po, $po->number);
            }

            $po = $this->orders->update($po, ['status' => PurchaseStatus::RECEIVED, 'received_at' => now()]);
            event(new \App\Events\Erp\PurchaseReceived($po)); // ERP: Dr Inventory / Cr Payable

            return $po;
        });
    }

    /**
     * Received unit cost per item. ERP setting landed_costs = capitalise (IAS 2): PO shipping + other costs are allocated to the lines by value
     * (last line takes the remainder) and included in the unit cost; default "expense" keeps the item cost (CALCULATIONS.md §Stock).
     *
     * @return array<int,string> item id => unit cost
     */
    private function landedUnitCosts(PurchaseOrder $po, $items): array
    {
        $out = $items->mapWithKeys(fn ($i) => [$i->id => (string) $i->unit_cost])->all();
        $landed = \App\Support\Money::add((string) $po->shipping_cost, (string) $po->other_cost);
        if (app(\App\Services\Erp\Equity\ErpSettings::class)->get('landed_costs') !== 'capitalise' || \App\Support\Money::cmp($landed, '0') <= 0) {
            return $out;
        }
        $total = $items->reduce(fn ($c, $i) => \App\Support\Money::add($c, \App\Support\Money::mul((string) $i->unit_cost, $i->quantity)), '0');
        if (\App\Support\Money::cmp($total, '0') <= 0) {
            return $out;
        }
        $left = $landed;
        foreach ($items->values() as $n => $i) {
            $value = \App\Support\Money::mul((string) $i->unit_cost, $i->quantity);
            $share = $n === $items->count() - 1 ? $left : \App\Support\Money::round(bcdiv(bcmul($landed, $value, 8), $total, 8));
            $left = \App\Support\Money::sub($left, $share);
            $out[$i->id] = \App\Support\Money::round(bcdiv(\App\Support\Money::add($value, $share), (string) $i->quantity, 8));
        }

        return $out;
    }

    public function cancel(PurchaseOrder $po): PurchaseOrder
    {
        $this->ensureDraft($po);

        $po = $this->orders->update($po, ['status' => PurchaseStatus::CANCELLED]);
        event(new \App\Events\Erp\PurchaseCancelled($po));

        return $po;
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
