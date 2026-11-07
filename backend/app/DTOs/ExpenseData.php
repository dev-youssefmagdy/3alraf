<?php

namespace App\DTOs;

use App\Http\Requests\Admin\ExpenseRequest;
use Illuminate\Http\UploadedFile;

final readonly class ExpenseData
{
    public function __construct(public int $categoryId, public ?int $branchId, public string $amount, public string $date, public ?string $note, public ?UploadedFile $attachment, public bool $removeAttachment, public ?bool $isPaid = null, public ?string $paidFrom = null, public ?int $supplierId = null) {}

    public static function fromRequest(ExpenseRequest $r): self
    {
        return new self((int) $r->input('expense_category_id'), $r->input('branch_id') ? (int) $r->input('branch_id') : null, (string) $r->input('amount'), $r->input('expense_date'), $r->input('note'), $r->file('attachment'), $r->boolean('remove_attachment'), $r->has('is_paid') ? $r->boolean('is_paid') : null, $r->input('paid_from') ?: null, $r->filled('supplier_id') ? (int) $r->input('supplier_id') : null);
    }

    public function toArray(): array
    {
        return ['expense_category_id' => $this->categoryId, 'branch_id' => $this->branchId, 'amount' => $this->amount, 'expense_date' => $this->date, 'note' => $this->note]
            + ($this->isPaid !== null ? ['is_paid' => $this->isPaid] : []) + ($this->paidFrom !== null ? ['paid_from' => $this->paidFrom] : []) + ($this->supplierId !== null ? ['supplier_id' => $this->supplierId] : []);
    }
}
