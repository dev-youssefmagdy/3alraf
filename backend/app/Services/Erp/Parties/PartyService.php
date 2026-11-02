<?php

namespace App\Services\Erp\Parties;

use App\Exceptions\AccountingException;
use App\Models\Customer;
use App\Models\Erp\Account;
use App\Models\Erp\JournalEntry;
use App\Models\Supplier;
use App\Services\Erp\Accounting\CoaTemplate;
use App\Services\Erp\Accounting\JournalService;
use App\Services\Erp\Reports\PartyLedger;
use App\Support\Money;

/**
 * Money movements with customers and suppliers. Every entry goes through JournalService (the only ledger writer), carries party_type/party_id on the
 * receivable/payable/advance lines and a deterministic idempotency key built from a caller reference (a form token / receipt number).
 * Payments and receipts settle the open amount first; any excess becomes an advance (supplier_advances / customer_advances).
 */
class PartyService
{
    public const CASH_ROLES = ['cash', 'bank', 'pos_cash', 'gateway', 'wallet'];

    public function __construct(private JournalService $journals, private PartyLedger $ledger, private CoaTemplate $coa) {}

    private function ensureChart(): void
    {
        if (! Account::where('role_key', 'receivable')->exists()) {
            $this->coa->seed(current_tenant());
            app(\App\Services\Erp\Accounting\AccountResolver::class)->flush();
        }
    }

    public function party(string $type, int $id): Customer|Supplier
    {
        return $type === 'customer' ? Customer::withTrashed()->findOrFail($id) : Supplier::withTrashed()->findOrFail($id);
    }

    private function amount(string $amount): string
    {
        $a = Money::round($amount);
        if (Money::cmp($a, '0') <= 0) {
            throw new AccountingException(__('erp_ext.errors.amount_positive'));
        }

        return $a;
    }

    private function cashRole(string $role): string
    {
        return in_array($role, self::CASH_ROLES, true) ? $role : throw new AccountingException(__('erp_ext.errors.bad_account'));
    }

    private function post(string $type, int $id, string $key, string $date, string $desc, array $lines, string $entryType = 'auto', ?int $branchId = null): JournalEntry
    {
        return $this->journals->post([
            'entry_date' => $date, 'type' => $entryType, 'description' => $desc, 'source_type' => $type, 'source_id' => $id,
            'idempotency_key' => 'party:'.$type.':'.$id.':'.$key, 'branch_id' => $branchId, 'created_by' => auth('tenant')->id(),
        ], $lines);
    }

    /** Supplier payment (Dr payable [+ Dr supplier_advances for any excess] / Cr cash-like). $advanceOnly books it all as an advance. */
    public function pay(int $supplierId, string $amount, string $ref, string $from = 'cash', ?string $date = null, bool $advanceOnly = false, ?int $branchId = null, ?string $note = null): JournalEntry
    {
        return $this->journals->exclusive(fn () => $this->payNow($supplierId, $amount, $ref, $from, $date, $advanceOnly, $branchId, $note)); // open amount is read and settled under the tenant lock
    }

    private function payNow(int $supplierId, string $amount, string $ref, string $from, ?string $date, bool $advanceOnly, ?int $branchId, ?string $note): JournalEntry
    {
        $this->ensureChart();
        $s = $this->party('supplier', $supplierId);
        $amount = $this->amount($amount);
        $from = $this->cashRole($from);
        $open = Money::max($this->ledger->position('supplier', $s->id)['outstanding'], '0');
        $apply = $advanceOnly ? '0.00' : Money::min($amount, $open);
        $adv = Money::sub($amount, $apply);
        $m = $note ?: __('erp_ext.memo.supplier_payment');
        $lines = [['account' => $from, 'credit' => $amount, 'branch_id' => $branchId, 'memo' => $m]];
        if (Money::cmp($apply, '0') > 0) {
            $lines[] = ['account' => 'payable', 'debit' => $apply, 'branch_id' => $branchId, 'memo' => $m, 'party_type' => 'supplier', 'party_id' => $s->id];
        }
        if (Money::cmp($adv, '0') > 0) {
            $lines[] = ['account' => 'supplier_advances', 'debit' => $adv, 'branch_id' => $branchId, 'memo' => $m, 'party_type' => 'supplier', 'party_id' => $s->id];
        }

        return $this->post('supplier', $s->id, 'pay:'.$ref, $date ?? now()->toDateString(), $m.' - '.$s->name, $lines, 'auto', $branchId);
    }

    /** Customer receipt (Dr cash-like / Cr receivable [+ Cr customer_advances for any excess]). */
    public function receive(int $customerId, string $amount, string $ref, string $into = 'cash', ?string $date = null, bool $advanceOnly = false, ?int $branchId = null, ?string $note = null): JournalEntry
    {
        return $this->journals->exclusive(fn () => $this->receiveNow($customerId, $amount, $ref, $into, $date, $advanceOnly, $branchId, $note));
    }

    private function receiveNow(int $customerId, string $amount, string $ref, string $into, ?string $date, bool $advanceOnly, ?int $branchId, ?string $note): JournalEntry
    {
        $this->ensureChart();
        $c = $this->party('customer', $customerId);
        $amount = $this->amount($amount);
        $into = $this->cashRole($into);
        $open = Money::max($this->ledger->position('customer', $c->id)['outstanding'], '0');
        $apply = $advanceOnly ? '0.00' : Money::min($amount, $open);
        $adv = Money::sub($amount, $apply);
        $m = $note ?: __('erp_ext.memo.customer_receipt');
        $lines = [['account' => $into, 'debit' => $amount, 'branch_id' => $branchId, 'memo' => $m]];
        if (Money::cmp($apply, '0') > 0) {
            $lines[] = ['account' => 'receivable', 'credit' => $apply, 'branch_id' => $branchId, 'memo' => $m, 'party_type' => 'customer', 'party_id' => $c->id];
        }
        if (Money::cmp($adv, '0') > 0) {
            $lines[] = ['account' => 'customer_advances', 'credit' => $adv, 'branch_id' => $branchId, 'memo' => $m, 'party_type' => 'customer', 'party_id' => $c->id];
        }

        return $this->post('customer', $c->id, 'rcpt:'.$ref, $date ?? now()->toDateString(), $m.' - '.$c->name, $lines, 'auto', $branchId);
    }

    /** Set an advance held for the party against what they owe / what we owe (Dr advances/payable swap). Applies min(advance, outstanding) or the given amount. */
    public function applyAdvance(string $type, int $id, string $ref, ?string $amount = null, ?string $date = null): ?JournalEntry
    {
        return $this->journals->exclusive(fn () => $this->applyAdvanceNow($type, $id, $ref, $amount, $date));
    }

    private function applyAdvanceNow(string $type, int $id, string $ref, ?string $amount, ?string $date): ?JournalEntry
    {
        $this->ensureChart();
        $p = $this->party($type, $id);
        $pos = $this->ledger->position($type, $p->id);
        $max = Money::min(Money::max($pos['advance'], '0'), Money::max($pos['outstanding'], '0'));
        $use = $amount ? Money::min($this->amount($amount), $max) : $max;
        if (Money::cmp($use, '0') <= 0) {
            throw new AccountingException(__('erp_ext.errors.nothing_to_apply'));
        }
        $m = __('erp_ext.memo.apply_advance');
        $lines = $type === 'customer'
            ? [['account' => 'customer_advances', 'debit' => $use, 'memo' => $m, 'party_type' => 'customer', 'party_id' => $p->id], ['account' => 'receivable', 'credit' => $use, 'memo' => $m, 'party_type' => 'customer', 'party_id' => $p->id]]
            : [['account' => 'payable', 'debit' => $use, 'memo' => $m, 'party_type' => 'supplier', 'party_id' => $p->id], ['account' => 'supplier_advances', 'credit' => $use, 'memo' => $m, 'party_type' => 'supplier', 'party_id' => $p->id]];

        return $this->post($type, $p->id, 'adv:'.$ref, $date ?? now()->toDateString(), $m.' - '.$p->name, $lines);
    }

    /**
     * Opening balance (journal type "opening" against opening_equity). $amount is what the party owes us (customer) / what we owe them (supplier).
     */
    public function opening(string $type, int $id, string $amount, string $ref, ?string $date = null): JournalEntry
    {
        $this->ensureChart();
        $p = $this->party($type, $id);
        $a = $this->amount($amount);
        $m = __('erp_ext.memo.opening');
        $lines = $type === 'customer'
            ? [['account' => 'receivable', 'debit' => $a, 'memo' => $m, 'party_type' => 'customer', 'party_id' => $p->id], ['account' => 'opening_equity', 'credit' => $a, 'memo' => $m]]
            : [['account' => 'opening_equity', 'debit' => $a, 'memo' => $m], ['account' => 'payable', 'credit' => $a, 'memo' => $m, 'party_type' => 'supplier', 'party_id' => $p->id]];

        return $this->post($type, $p->id, 'open:'.$ref, $date ?? now()->toDateString(), $m.' - '.$p->name, $lines, 'opening');
    }

    /**
     * Credit / debit note with a party (journal types credit_note | debit_note). The note always moves the party's open balance:
     * customer: credit_note reduces what they owe (Dr counter / Cr receivable), debit_note increases it;
     * supplier: debit_note reduces what we owe (Dr payable / Cr counter), credit_note increases it.
     * $counter = account id (defaults: customer credit note -> sales_returns, customer debit note -> other_revenue, supplier debit note -> inventory, supplier credit note -> purchase_expenses).
     */
    public function note(string $type, int $id, string $kind, string $amount, string $ref, ?int $counterAccountId = null, ?string $date = null, ?string $desc = null): JournalEntry
    {
        $this->ensureChart();
        if (! in_array($kind, ['credit_note', 'debit_note'], true)) {
            throw new AccountingException(__('erp_ext.errors.bad_account'));
        }
        $p = $this->party($type, $id);
        $a = $this->amount($amount);
        $reduces = ($type === 'customer') === ($kind === 'credit_note');
        $counter = $counterAccountId ?: match (true) {
            $type === 'customer' && $reduces => 'sales_returns', $type === 'customer' => 'other_revenue',
            $reduces => 'inventory', default => 'purchase_expenses',
        };
        if (is_int($counter) && ! Account::where('is_group', false)->where('is_active', true)->whereKey($counter)->exists()) {
            throw new AccountingException(__('erp_ext.errors.bad_account'));
        }
        $control = $type === 'customer' ? 'receivable' : 'payable';
        $m = $desc ?: __('erp_ext.memo.'.$kind);
        $party = ['party_type' => $type, 'party_id' => $p->id, 'memo' => $m];
        // customer: reduce = credit receivable; supplier: reduce = debit payable
        $controlDebit = $type === 'customer' ? ! $reduces : $reduces;
        $lines = $controlDebit
            ? [['account' => $control, 'debit' => $a] + $party, ['account' => $counter, 'credit' => $a, 'memo' => $m]]
            : [['account' => $counter, 'debit' => $a, 'memo' => $m], ['account' => $control, 'credit' => $a] + $party];

        return $this->post($type, $p->id, $kind.':'.$ref, $date ?? now()->toDateString(), $m.' - '.$p->name, $lines, $kind);
    }
}
