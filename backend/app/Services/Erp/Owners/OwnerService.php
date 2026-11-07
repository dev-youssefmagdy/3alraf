<?php

namespace App\Services\Erp\Owners;

use App\Exceptions\AccountingException;
use App\Models\Branch;
use App\Models\Erp\Account;
use App\Models\Erp\JournalLine;
use App\Models\Erp\Owner;
use App\Models\Erp\OwnerTransaction;
use App\Services\Erp\Accounting\AccountResolver;
use App\Services\Erp\Accounting\CoaTemplate;
use App\Services\Erp\Accounting\JournalService;
use App\Services\Erp\Parties\PartyService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/** Owners and their equity movements. Ledger writes only through JournalService; the owner is the party (party_type "owner") of every equity line. */
class OwnerService
{
    /** Equity accounts that make up an owner's equity (credit-positive): capital, drawings, distributions paid out of retained earnings. */
    public const EQUITY_ROLES = ['owner_capital', 'owner_drawings', 'retained_earnings'];

    public function __construct(private JournalService $journals, private CoaTemplate $coa) {}

    private function ensureChart(): void
    {
        if (! Account::where('role_key', 'owner_capital')->exists()) {
            $this->coa->seed();
            app(AccountResolver::class)->flush();
        }
    }

    /** Total ownership percent of all owners except $ignoreId. */
    public function totalPercent(?int $ignoreId = null): string
    {
        return (string) (Owner::when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->sum('ownership_percent') ?: '0');
    }

    private function assertPercent(string $pct, ?int $ignoreId): void
    {
        if (bccomp($pct, '0', 4) < 0 || bccomp(bcadd($this->totalPercent($ignoreId), $pct, 4), '100', 4) > 0) {
            throw new AccountingException(__('erp_ext.errors.percent_over', ['max' => bcsub('100', $this->totalPercent($ignoreId), 4)]));
        }
    }

    public function save(array $d, ?Owner $owner = null): Owner
    {
        return DB::transaction(function () use ($d, $owner) {
            // serialise concurrent percentage edits
            Owner::lockForUpdate()->get(['id']);
            if (! empty($d['admin_id'])) {
                \App\Models\Admin::findOrFail($d['admin_id']);
            }
            $this->assertPercent((string) ($d['ownership_percent'] ?? 0), $owner?->id);
            $owner ??= new Owner;
            $owner->fill(['name' => $d['name'], 'phone' => $d['phone'] ?? null, 'ownership_percent' => $d['ownership_percent'] ?? 0,
                'is_eligible' => (bool) ($d['is_eligible'] ?? false), 'admin_id' => $d['admin_id'] ?? null])->save();

            return $owner;
        });
    }

    /**
     * contribution: Dr cash-like / Cr owner_capital; withdrawal: Dr owner_drawings / Cr cash-like (refused when the paying account cannot cover it).
     * Profit distributions are NOT owner transactions: they go through the controlled workflow (ProfitDistributionService: distributable profit,
     * cash buffer, approval, period checks) - ERP-005.
     */
    public function transact(Owner $owner, string $type, string $amount, string $date, string $account = 'cash', ?int $branchId = null, ?string $note = null, ?string $ref = null): OwnerTransaction
    {
        if ($type === 'distribution') {
            throw new AccountingException(__('erp_acc.errors.distribution_workflow'));
        }
        if (! in_array($type, OwnerTransaction::NEW_TYPES, true)) {
            throw new AccountingException(__('erp_ext.errors.bad_account'));
        }
        if (! in_array($account, PartyService::CASH_ROLES, true)) {
            throw new AccountingException(__('erp_ext.errors.bad_account'));
        }
        $amount = Money::round($amount);
        if (Money::cmp($amount, '0') <= 0) {
            throw new AccountingException(__('erp_ext.errors.amount_positive'));
        }
        if ($branchId) {
            Branch::findOrFail($branchId);
        }
        $this->ensureChart();

        return DB::transaction(function () use ($owner, $type, $amount, $date, $account, $branchId, $note, $ref) {
            if ($ref && ($done = OwnerTransaction::whereHas('entry', fn ($q) => $q->where('idempotency_key', 'owner:'.$owner->id.':'.$type.':'.$ref))->first())) {
                if (bccomp((string) $done->amount, (string) $amount, 4) !== 0) { // QA-012: same token, different amount = the form was changed after saving
                    throw new AccountingException(__('erp_acc.errors.duplicate_ref', ['number' => $done->entry?->number ?? '']));
                }

                return $done; // replay of the same form token (checked before the cash test: the first submit already moved the money)
            }
            if ($type === 'withdrawal') {
                $this->assertCovered($account, $amount); // ERP-031: never pay an owner money the business does not hold
            }
            $p = ['party_type' => 'owner', 'party_id' => $owner->id, 'branch_id' => $branchId, 'memo' => $note ?: __('erp_ext.own.t.'.$type)];
            $cash = ['account' => $account, 'branch_id' => $branchId, 'memo' => $p['memo']];
            $lines = match ($type) {
                'contribution' => [$cash + ['debit' => $amount], ['account' => 'owner_capital', 'credit' => $amount] + $p],
                'withdrawal' => [['account' => 'owner_drawings', 'debit' => $amount] + $p, $cash + ['credit' => $amount]],
            };
            $entry = $this->journals->post([
                'entry_date' => $date, 'type' => 'auto', 'description' => __('erp_ext.own.t.'.$type).' - '.$owner->name, 'source_type' => 'owner', 'source_id' => $owner->id,
                'idempotency_key' => $ref ? 'owner:'.$owner->id.':'.$type.':'.$ref : null, 'branch_id' => $branchId, 'created_by' => auth('admin')->id(),
            ], $lines);
            if ($ref && ($t = OwnerTransaction::where('journal_entry_id', $entry->id)->first())) {
                return $t; // replay of the same form token
            }

            return OwnerTransaction::create(['owner_id' => $owner->id, 'type' => $type, 'amount' => $amount, 'txn_date' => $date, 'journal_entry_id' => $entry->id,
                'branch_id' => $branchId, 'account_role' => $account, 'note' => $note, 'created_by' => auth('admin')->id()]);
        });
    }

    /** Balance of a cash-like account (all branches, posted lines) must cover $amount. */
    private function assertCovered(string $role, string $amount, ?int $branchId = null, bool $branchOnly = false): void
    {
        $acc = Account::where('role_key', $role)->value('id');
        $q = JournalLine::query()->join('erp_journal_entries as e', 'e.id', '=', 'erp_journal_lines.entry_id')->whereIn('e.status', ['posted', 'reversed'])
            ->where('erp_journal_lines.account_id', $acc)->when($branchOnly, fn ($w) => $branchId ? $w->where('erp_journal_lines.branch_id', $branchId) : $w->whereNull('erp_journal_lines.branch_id'));
        $bal = (string) ($q->selectRaw('COALESCE(SUM(erp_journal_lines.debit - erp_journal_lines.credit),0) as b')->value('b') ?? '0');
        if (bccomp($bal, $amount, 4) < 0) {
            throw new AccountingException(__('erp_acc.errors.insufficient_cash', ['balance' => number_format((float) $bal, 2), 'amount' => number_format((float) $amount, 2)]));
        }
    }

    /**
     * Branch capital allocation (ERP-006): an INTERNAL, zero-sum move - the money comes from a real source.
     *   cash  : Dr cash-like (branch B) / Cr cash-like (source branch, NULL = head office / unallocated)  -> consolidated cash unchanged
     *   equity: Dr Owner capital / Cr Branch capital (branch B)  -> memo reclass for branch capital reporting, total equity unchanged
     * Fresh owner money for a branch is a contribution (transact('contribution', branch: B)), never an allocation.
     */
    public function allocateToBranch(int $branchId, string $amount, string $date, ?string $note = null, ?string $ref = null, string $account = 'cash', ?int $fromBranchId = null)
    {
        $branch = Branch::findOrFail($branchId);
        if ($fromBranchId) {
            Branch::findOrFail($fromBranchId);
        }
        if ($fromBranchId === $branchId) {
            throw new AccountingException(__('erp_rep.bt.same'));
        }
        if (! in_array($account, PartyService::CASH_ROLES, true)) {
            throw new AccountingException(__('erp_ext.errors.bad_account'));
        }
        $amount = Money::round($amount);
        if (Money::cmp($amount, '0') <= 0) {
            throw new AccountingException(__('erp_ext.errors.amount_positive'));
        }
        $this->ensureChart();
        $m = $note ?: __('erp_ext.own.branch_alloc');
        $key = $ref ? 'branchcap:'.$branch->id.':'.$ref : null;
        if ($key && ($done = \App\Models\Erp\JournalEntry::where('idempotency_key', $key)->first())) {
            return $done;
        }
        $this->assertCovered($account, $amount, $fromBranchId, true);

        return $this->journals->post([
            'entry_date' => $date, 'type' => 'auto', 'description' => $m.' - '.$branch->name, 'source_type' => 'branch_capital', 'source_id' => $branch->id,
            'idempotency_key' => $key, 'branch_id' => null, 'created_by' => auth('admin')->id(), // per-line branches (a NULL source line must stay head office)
        ], [
            ['account' => $account, 'debit' => $amount, 'branch_id' => $branch->id, 'memo' => $m],
            ['account' => $account, 'credit' => $amount, 'branch_id' => $fromBranchId, 'memo' => $m],
            ['account' => 'owner_capital', 'debit' => $amount, 'branch_id' => $fromBranchId, 'memo' => $m],
            ['account' => 'branch_capital', 'credit' => $amount, 'branch_id' => $branch->id, 'memo' => $m],
        ]);
    }

    private function lines(?int $ownerId = null)
    {
        return JournalLine::query()->join('erp_journal_entries as e', 'e.id', '=', 'erp_journal_lines.entry_id')->whereIn('e.status', ['posted', 'reversed'])
            ->where('erp_journal_lines.party_type', 'owner')->when($ownerId, fn ($q) => $q->where('erp_journal_lines.party_id', $ownerId))
            ->whereIn('erp_journal_lines.account_id', Account::whereIn('role_key', self::EQUITY_ROLES)->pluck('id'));
    }

    /** Equity balance per owner id (credit-positive), derived from journal lines. @return array<int,array{capital:string,drawings:string,distributions:string,equity:string}> */
    public function equity(): array
    {
        $ids = Account::whereIn('role_key', self::EQUITY_ROLES)->pluck('role_key', 'id');
        $out = [];
        foreach ($this->lines()->groupBy('erp_journal_lines.party_id', 'erp_journal_lines.account_id')
            ->select('erp_journal_lines.party_id', 'erp_journal_lines.account_id', DB::raw('SUM(erp_journal_lines.debit) as dr'), DB::raw('SUM(erp_journal_lines.credit) as cr'))->get() as $r) {
            $o = &$out[(int) $r->party_id];
            $o ??= ['capital' => '0.0000', 'drawings' => '0.0000', 'distributions' => '0.0000', 'equity' => '0.0000'];
            $net = bcsub((string) $r->cr, (string) $r->dr, 4);
            $k = ['owner_capital' => 'capital', 'owner_drawings' => 'drawings', 'retained_earnings' => 'distributions'][$ids[$r->account_id]];
            $o[$k] = bcadd($o[$k], $net, 4);
            $o['equity'] = bcadd($o['equity'], $net, 4);
            unset($o);
        }

        return $out;
    }

    /** Per-owner statement (equity lines with running equity balance). */
    public function statement(Owner $owner, string $from, string $to): array
    {
        $o = $this->lines($owner->id)->where('e.entry_date', '<', $from)->selectRaw('COALESCE(SUM(erp_journal_lines.credit),0) - COALESCE(SUM(erp_journal_lines.debit),0) as n')->first();
        $bal = bcadd((string) $o->n, '0', 4);
        $opening = $bal;
        $rows = [];
        $ls = JournalLine::query()->with(['entry', 'account'])->select('erp_journal_lines.*')->join('erp_journal_entries as e', 'e.id', '=', 'erp_journal_lines.entry_id')
            ->whereIn('e.status', ['posted', 'reversed'])->where('erp_journal_lines.party_type', 'owner')->where('erp_journal_lines.party_id', $owner->id)
            ->whereIn('erp_journal_lines.account_id', Account::whereIn('role_key', self::EQUITY_ROLES)->pluck('id'))->whereBetween('e.entry_date', [$from, $to])
            ->orderBy('e.entry_date')->orderBy('erp_journal_lines.id')->get();
        foreach ($ls as $l) {
            $bal = bcadd($bal, bcsub((string) $l->credit, (string) $l->debit, 4), 4);
            $rows[] = ['line' => $l, 'balance' => $bal];
        }

        return ['opening' => $opening, 'rows' => $rows, 'closing' => $bal];
    }
}
