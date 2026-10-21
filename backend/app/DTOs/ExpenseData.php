<?php

namespace App\DTOs;

use App\Http\Requests\Tenant\ExpenseRequest;
use Illuminate\Http\UploadedFile;

final readonly class ExpenseData
{
    public function __construct(public int $categoryId, public ?int $branchId, public string $amount, public string $date, public ?string $note, public ?UploadedFile $attachment, public bool $removeAttachment) {}

    public static function fromRequest(ExpenseRequest $r): self
    {
        return new self((int) $r->input('expense_category_id'), $r->input('branch_id') ? (int) $r->input('branch_id') : null, (string) $r->input('amount'), $r->input('expense_date'), $r->input('note'), $r->file('attachment'), $r->boolean('remove_attachment'));
    }

    public function toArray(): array
    {
        return ['expense_category_id' => $this->categoryId, 'branch_id' => $this->branchId, 'amount' => $this->amount, 'expense_date' => $this->date, 'note' => $this->note];
    }
}
