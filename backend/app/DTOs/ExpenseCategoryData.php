<?php

namespace App\DTOs;

use App\Http\Requests\Admin\ExpenseCategoryRequest;

final readonly class ExpenseCategoryData
{
    public function __construct(public array $name, public bool $isActive, public ?int $erpAccountId = null, public bool $hasAccount = false) {}

    public static function fromRequest(ExpenseCategoryRequest $r): self
    {
        return new self($r->cleanTranslations($r->input('name')), $r->boolean('is_active'), $r->filled('erp_account_id') ? (int) $r->input('erp_account_id') : null,
            $r->has('erp_account_id') && (bool) auth('admin')->user()?->can('erp.expenses.manage')); // ERP-029: the ledger mapping needs erp.expenses.manage
    }

    public function toArray(): array
    {
        return ['name' => $this->name, 'is_active' => $this->isActive] + ($this->hasAccount ? ['erp_account_id' => $this->erpAccountId] : []);
    }
}
