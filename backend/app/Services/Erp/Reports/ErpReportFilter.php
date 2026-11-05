<?php

namespace App\Services\Erp\Reports;

use Carbon\Carbon;
use Illuminate\Http\Request;

/** Filters shared by every ERP report. Branch-specific views need erp.branches.view: otherwise the branch filter is dropped (consolidated only). */
final class ErpReportFilter
{
    public const ENTRY_TYPES = ['manual', 'auto', 'opening', 'adjusting', 'closing', 'reversal', 'credit_note', 'debit_note'];

    public function __construct(
        public string $from,
        public string $to,
        public ?int $branchId = null,
        public ?int $accountId = null,
        public ?string $type = null,
        public ?int $customerId = null,
        public ?int $supplierId = null,
        public ?int $paymentMethodId = null,
        public ?string $currency = null,
    ) {}

    public static function make(?string $from = null, ?string $to = null, ?int $branchId = null): self
    {
        return new self($from ?: now()->startOfMonth()->toDateString(), $to ?: now()->toDateString(), $branchId);
    }

    public static function fromRequest(Request $r, bool $canBranch): self
    {
        $d = function ($v, string $def) {
            try {
                return $v ? Carbon::parse((string) $v)->toDateString() : $def;
            } catch (\Throwable) {
                return $def;
            }
        };
        $to = $d($r->input('to'), now()->toDateString());
        $from = $d($r->input('from'), now()->startOfMonth()->toDateString());
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $id = fn (string $k) => $r->filled($k) && ctype_digit((string) $r->input($k)) ? (int) $r->input($k) : null;

        return new self($from, $to, $canBranch ? $id('branch_id') : null, $id('account_id'), $r->filled('type') ? substr((string) $r->input('type'), 0, 30) : null,
            $id('customer_id'), $id('supplier_id'), $id('payment_method_id'), $r->filled('currency') ? strtoupper(substr((string) $r->input('currency'), 0, 3)) : null);
    }

    public function dayBeforeFrom(): string
    {
        return Carbon::parse($this->from)->subDay()->toDateString();
    }

    /** Query-string carried over to export links. */
    public function query(): array
    {
        return array_filter(['from' => $this->from, 'to' => $this->to, 'branch_id' => $this->branchId, 'account_id' => $this->accountId, 'type' => $this->type,
            'customer_id' => $this->customerId, 'supplier_id' => $this->supplierId, 'payment_method_id' => $this->paymentMethodId, 'currency' => $this->currency], fn ($v) => $v !== null && $v !== '');
    }
}
