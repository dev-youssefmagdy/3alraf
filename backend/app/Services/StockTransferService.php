<?php

namespace App\Services;

use App\DTOs\StockTransferData;
use App\Enums\StockMovementType;
use App\Enums\TransferStatus;
use App\Models\StockTransfer;
use App\Repositories\Contracts\StockTransferRepositoryInterface;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Transfers: draft -> in_transit (transfer_out at source avg cost) -> received (transfer_in at that cost) | cancelled (draft only). */
class StockTransferService
{
    public function __construct(private StockTransferRepositoryInterface $transfers, private StockLedger $ledger) {}

    public function find(int|string $id): StockTransfer
    {
        return $this->transfers->query()->with(['items.product', 'items.variant.values', 'from', 'to'])->findOrFail($id);
    }

    public function create(StockTransferData $dto): StockTransfer
    {
        return DB::transaction(function () use ($dto) {
            $t = $this->transfers->create(['from_branch_id' => $dto->fromBranchId, 'to_branch_id' => $dto->toBranchId, 'note' => $dto->note, 'status' => TransferStatus::DRAFT, 'created_by' => auth('tenant')->id()]);
            $t->update(['number' => sprintf('TR-%06d', $t->id)]);
            $this->syncItems($t, $dto);

            return $t;
        });
    }

    public function update(StockTransfer $t, StockTransferData $dto): StockTransfer
    {
        $this->ensureStatus($t, TransferStatus::DRAFT);

        return DB::transaction(function () use ($t, $dto) {
            $this->transfers->update($t, ['from_branch_id' => $dto->fromBranchId, 'to_branch_id' => $dto->toBranchId, 'note' => $dto->note]);
            $this->syncItems($t, $dto);

            return $t;
        });
    }

    public function send(StockTransfer $t): StockTransfer
    {
        $this->ensureStatus($t, TransferStatus::DRAFT);

        return DB::transaction(function () use ($t) {
            foreach ($t->items()->get() as $i) {
                $m = $this->ledger->record($t->from_branch_id, $i->product_id, $i->product_variant_id, StockMovementType::TRANSFER_OUT, $i->quantity, null, $t, $t->number);
                $i->update(['unit_cost' => $m->unit_cost]);
            }

            return $this->transfers->update($t, ['status' => TransferStatus::IN_TRANSIT, 'sent_at' => now()]);
        });
    }

    public function receive(StockTransfer $t): StockTransfer
    {
        $this->ensureStatus($t, TransferStatus::IN_TRANSIT);

        return DB::transaction(function () use ($t) {
            foreach ($t->items()->get() as $i) {
                $this->ledger->record($t->to_branch_id, $i->product_id, $i->product_variant_id, StockMovementType::TRANSFER_IN, $i->quantity, (string) $i->unit_cost, $t, $t->number);
            }

            return $this->transfers->update($t, ['status' => TransferStatus::RECEIVED, 'received_at' => now()]);
        });
    }

    public function cancel(StockTransfer $t): StockTransfer
    {
        $this->ensureStatus($t, TransferStatus::DRAFT);

        return $this->transfers->update($t, ['status' => TransferStatus::CANCELLED]);
    }

    public function delete(StockTransfer $t): void
    {
        if (! in_array($t->status, [TransferStatus::DRAFT, TransferStatus::CANCELLED], true)) {
            throw ValidationException::withMessages(['record' => __('ops.document_locked')]);
        }
        $this->transfers->delete($t);
    }

    private function ensureStatus(StockTransfer $t, TransferStatus $expected): void
    {
        if ($t->status !== $expected) {
            throw ValidationException::withMessages(['record' => __('ops.document_locked')]);
        }
    }

    private function syncItems(StockTransfer $t, StockTransferData $dto): void
    {
        $t->items()->delete();
        foreach ($dto->items as $i) {
            $t->items()->create(['product_id' => $i['product_id'], 'product_variant_id' => $i['variant_id'], 'quantity' => $i['quantity']]);
        }
    }
}
