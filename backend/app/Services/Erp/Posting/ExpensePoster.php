<?php

namespace App\Services\Erp\Posting;

use App\Models\Erp\Account;
use App\Models\Erp\JournalEntry;
use App\Models\Expense;
use App\Support\Money;

/**
 * Expenses : Dr Expense account (category mapping, fallback role other_expense) / Cr cash-like account (paid) or Accounts payable (unpaid).
 * Edits reverse the previous entry and post a new one (the key carries a hash of the posted facts, so a replay is a no-op);
 * deleting reverses it. Posted at the expense date.
 */
class ExpensePoster extends Poster
{
    private function accountFor(Expense $e): string|int
    {
        $this->ensureChart();
        $id = $e->category?->erp_account_id;
        if ($id && Account::whereKey($id)->where('is_active', true)->where('is_group', false)->exists()) {
            return (int) $id;
        }

        return 'other_expense';
    }

    public function sync(Expense $e): ?JournalEntry
    {
        $e->loadMissing('category');
        $debit = $this->accountFor($e);
        $paid = $e->is_paid !== false;
        $from = $paid ? ($e->paid_from ?: 'cash') : 'payable';
        $amount = Money::round((string) $e->amount);
        $date = $e->expense_date->toDateString();
        $party = $paid ? null : $e->supplier_id; // unpaid bill: the payable line carries the supplier (ERP-013)
        $sig = substr(sha1(json_encode([$amount, $date, $debit, $from, $e->branch_id] + ($party ? [5 => $party] : []))), 0, 12);
        $key = 'expense:'.$e->id.':'.$sig;

        if (JournalEntry::where('idempotency_key', $key)->where('status', 'reversed')->exists()) { // A -> B -> A edits: a reversed key is never reused
            $key .= ':r'.JournalEntry::where('source_type', 'expense')->where('source_id', $e->id)->where('type', 'auto')->count();
        }
        $live = JournalEntry::where('source_type', 'expense')->where('source_id', $e->id)->where('status', 'posted')->where('type', 'auto')->get();
        foreach ($live as $old) {
            if ($old->idempotency_key !== $key) {
                $this->journals->reverse($old, auth('tenant')->id(), $this->today(), 'expense changed');
            }
        }
        if ($live->contains('idempotency_key', $key)) {
            return $live->firstWhere('idempotency_key', $key);
        }

        return $this->post($key, $date, 'Expense #'.$e->id, 'expense', $e->id, [
            $this->dr($debit, $amount, $e->branch_id, $e->note), $this->cr($from, $amount, $e->branch_id, $e->note, $party ? 'supplier' : null, $party),
        ], $e->branch_id);
    }

    public function deleted(Expense $e): int
    {
        return $this->reverseLive('expense', $e->id, ['expense:'.$e->id.':'], 'expense deleted');
    }
}
