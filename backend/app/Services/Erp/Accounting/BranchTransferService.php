<?php

namespace App\Services\Erp\Accounting;

use App\Exceptions\AccountingException;
use App\Models\Branch;
use App\Models\Erp\JournalEntry;
use App\Services\Erp\Parties\PartyService;

/**
 * Branch-to-branch cash transfer through the clearing account `branch_transfer`:
 *   Dr branch_transfer (source branch) / Cr cash (source branch)   -  money leaves the source
 *   Dr cash (destination branch) / Cr branch_transfer (destination)  -  money arrives
 * The clearing account nets to zero consolidated, so company profit and total cash are unchanged.
 */
class BranchTransferService
{
    public function __construct(private JournalService $journals, private CoaTemplate $coa) {}

    public function cash(int $fromBranch, int $toBranch, string $amount, ?string $date = null, ?string $note = null, string $role = 'cash', ?string $ref = null, ?int $userId = null): JournalEntry
    {
        if ($fromBranch === $toBranch) {
            throw new AccountingException(__('erp_rep.bt.same'));
        }
        if (! in_array($role, PartyService::CASH_ROLES, true) || bccomp($amount, '0', 4) <= 0) {
            throw new AccountingException(__('erp_rep.bt.invalid'));
        }
        $from = Branch::findOrFail($fromBranch);
        $to = Branch::findOrFail($toBranch);
        if (! \App\Models\Erp\Account::where('role_key', 'receivable')->exists()) {
            $this->coa->seed();
        }
        $this->coa->ensureRoles(['branch_transfer']);
        $memo = trim(__('erp_rep.bt.memo', ['from' => (string) $from->name, 'to' => (string) $to->name]).' '.$note);

        return $this->journals->post([
            'entry_date' => $date ?: now()->toDateString(), 'type' => 'manual', 'source_type' => 'branch_cash_transfer', 'description' => $memo,
            'idempotency_key' => $ref ? 'btransfer:'.$ref : null, 'strict_replay' => true, 'created_by' => $userId,
        ], [
            ['account' => 'branch_transfer', 'debit' => $amount, 'branch_id' => $from->id, 'memo' => $memo],
            ['account' => $role, 'credit' => $amount, 'branch_id' => $from->id, 'memo' => $memo],
            ['account' => $role, 'debit' => $amount, 'branch_id' => $to->id, 'memo' => $memo],
            ['account' => 'branch_transfer', 'credit' => $amount, 'branch_id' => $to->id, 'memo' => $memo],
        ]);
    }
}
