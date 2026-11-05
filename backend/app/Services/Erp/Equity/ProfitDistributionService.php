<?php

namespace App\Services\Erp\Equity;

use App\Exceptions\AccountingException;
use App\Models\Erp\Account;
use App\Models\Erp\AuditLog;
use App\Models\Erp\FiscalYear;
use App\Models\Erp\JournalEntry;
use App\Models\Erp\Owner;
use App\Models\Erp\Period;
use App\Models\Erp\ProfitDistribution;
use App\Models\Erp\ProfitDistributionLine;
use App\Models\Tenant;
use App\Services\Erp\Accounting\JournalService;
use App\Services\Erp\Accounting\PeriodService;
use App\Services\Erp\Parties\PartyService;
use App\Services\Erp\Reports\LedgerReports;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Controlled profit distribution: calculate -> draft -> approve -> post (accrual) -> pay per owner. See docs/ERP_PLAN.md "Profit methodology".
 * Ledger writes only through JournalService; nothing historic is ever edited (cancel of a posted run = reversal journals).
 */
class ProfitDistributionService
{
    public function __construct(private ProfitService $profit, private JournalService $journals, private LedgerReports $reports, private ErpSettings $settings, private PeriodService $periods) {}

    private function fail(string $key, array $p = []): never
    {
        throw new AccountingException(__('erp_dist.errors.'.$key, $p));
    }

    /** Every pre-condition of a run for [from,to]. */
    public function assertReady(string $from, string $to, bool $acknowledgeOpen, ?int $exceptId = null): FiscalYear
    {
        if ($from > $to) {
            $this->fail('bad_range');
        }
        if ($to > now()->toDateString()) {
            $this->fail('future_period');
        }
        $fy = FiscalYear::where('starts_on', '<=', $from)->where('ends_on', '>=', $from)->first() ?? $this->periods->createYear(Carbon::parse($from)->year);
        if ($to > $fy->ends_on->toDateString()) {
            $this->fail('one_year');
        }
        if (JournalEntry::where('status', 'draft')->whereBetween('entry_date', [$from, $to])->exists()) {
            $this->fail('drafts');
        }
        $tb = $this->reports->trialBalance();
        if (! $tb['balanced']) {
            $this->fail('unbalanced');
        }
        $this->periods->createYear(Carbon::parse($from)->year);
        $open = Period::where('starts_on', '<=', $to)->where('ends_on', '>=', $from)->where('status', 'open')->exists();
        if ($open && ! $acknowledgeOpen) {
            $this->fail('period_open');
        }
        if (ProfitDistribution::where('status', '!=', 'cancelled')->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->where('period_start', '<=', $to)->where('period_end', '>=', $from)->exists()) {
            $this->fail('overlap');
        }

        return $fy;
    }

    /** @return array<int,array{owner:Owner,percent:string,amount:string}> */
    public function split(string $total, ?array $overrides = null): array
    {
        $owners = Owner::where('is_eligible', true)->where('ownership_percent', '>', 0)->orderBy('id')->get();
        if ($owners->isEmpty()) {
            $this->fail('no_owners');
        }
        if ($overrides !== null) {
            $sum = '0';
            $out = [];
            foreach ($overrides as $oid => $amt) {
                $owner = $owners->firstWhere('id', (int) $oid) ?? $this->fail('override_owner');
                $amt = bcadd((string) $amt, '0', 2);
                if (bccomp($amt, '0', 2) < 0) {
                    $this->fail('override_sum');
                }
                $sum = bcadd($sum, $amt, 2);
                if (bccomp($amt, '0', 2) > 0) {
                    $out[] = ['owner' => $owner, 'percent' => bcadd(bcdiv(bcmul($amt, '100', 8), $total, 8), '0', 4), 'amount' => $amt];
                }
            }
            if (bccomp($sum, $total, 2) !== 0) {
                $this->fail('override_sum');
            }

            return $out;
        }
        $totalPct = (string) $owners->sum('ownership_percent');
        $rows = [];
        $given = '0';
        foreach ($owners as $o) {
            $amt = bcdiv(bcmul($total, (string) $o->ownership_percent, 8), $totalPct, 2); // truncated to the cent
            $given = bcadd($given, $amt, 2);
            $rows[$o->id] = ['owner' => $o, 'percent' => bcadd(bcdiv(bcmul((string) $o->ownership_percent, '100', 8), $totalPct, 8), '0', 4), 'amount' => $amt];
        }
        $remainder = bcsub($total, $given, 2); // cents lost to truncation go to the largest owner (lowest id on ties)
        $largest = $owners->sortBy([['ownership_percent', 'desc'], ['id', 'asc']])->first();
        $rows[$largest->id]['amount'] = bcadd($rows[$largest->id]['amount'], $remainder, 2);

        return array_values(array_filter($rows, fn ($r) => bccomp($r['amount'], '0', 2) > 0));
    }

    /** Step 1: calculate and store a draft. $d: from, to, retain_percent?, retain_amount?, acknowledge_open?, overrides? (owner_id => amount), note? */
    public function create(array $d, ?int $userId): ProfitDistribution
    {
        $from = Carbon::parse($d['from'])->toDateString();
        $to = Carbon::parse($d['to'])->toDateString();

        return DB::transaction(function () use ($d, $from, $to, $userId) {
            Tenant::whereKey(current_tenant()->id)->lockForUpdate()->first(); // serialises runs per tenant
            $ack = (bool) ($d['acknowledge_open'] ?? false);
            $fy = $this->assertReady($from, $to, $ack);
            $calc = $this->profit->compute($from, $to);
            if (bccomp($calc['distributable'], '0', 2) <= 0) {
                $this->fail('nothing_distributable');
            }
            $retain = '0';
            if (isset($d['retain_amount']) && $d['retain_amount'] !== '' && $d['retain_amount'] !== null) {
                $retain = bcadd((string) $d['retain_amount'], '0', 2);
            } elseif (isset($d['retain_percent']) && $d['retain_percent'] !== '' && $d['retain_percent'] !== null) {
                if (bccomp((string) $d['retain_percent'], '0', 4) < 0 || bccomp((string) $d['retain_percent'], '100', 4) > 0) {
                    $this->fail('retain_range');
                }
                $retain = bcdiv(bcmul($calc['distributable'], (string) $d['retain_percent'], 8), '100', 2);
            }
            $distributed = bcsub($calc['distributable'], $retain, 2);
            if (bccomp($retain, '0', 2) < 0 || bccomp($distributed, '0', 2) <= 0) {
                $this->fail('retain_range');
            }
            $lines = $this->split($distributed, ! empty($d['overrides']) ? $d['overrides'] : null);

            $snap = $calc;
            $snap['opex_by_account'] = array_map(fn ($r) => ['code' => $r['account']->code, 'name' => $r['account']->name, 'amount' => $r['amount']], $calc['opex_rows']);
            unset($snap['opex_rows']);
            try {
                $dist = ProfitDistribution::create([
                    'fiscal_year_id' => $fy->id, 'period_start' => $from, 'period_end' => $to, 'status' => 'draft', 'ledger_profit' => $calc['ledger_profit'], 'tax_amount' => $calc['tax'],
                    'net_profit' => $calc['net_profit'], 'retained_before' => $calc['prior_retained'], 'reserve_amount' => $calc['reserve_amount'], 'distributable_profit' => $calc['distributable_profit'],
                    'cash_available' => $calc['distributable_cash'], 'distributable' => $calc['distributable'], 'distributed_amount' => $distributed,
                    'retained_after' => bcsub(bcsub(bcadd($calc['prior_retained'], $calc['net_profit'], 4), $calc['reserve_amount'], 4), $distributed, 4),
                    'acknowledged_open' => $ack, 'active_key' => $from.'|'.$to, 'note' => $d['note'] ?? null, 'snapshot' => $snap, 'created_by' => $userId,
                ]);
            } catch (UniqueConstraintViolationException) {
                $this->fail('overlap');
            }
            foreach ($lines as $l) {
                ProfitDistributionLine::create(['distribution_id' => $dist->id, 'owner_id' => $l['owner']->id, 'percent' => $l['percent'], 'amount' => $l['amount']]);
            }
            AuditLog::create(['action' => 'distribution.created', 'subject_type' => 'profit_distribution', 'subject_id' => $dist->id, 'user_id' => $userId, 'meta' => ['amount' => $distributed, 'period' => "$from..$to"]]);

            return $dist;
        });
    }

    /** Fresh re-validation of an existing run against the CURRENT ledger (used at approval and again at posting). */
    private function assertPostable(ProfitDistribution $dist): array
    {
        $this->assertReady($dist->period_start->toDateString(), $dist->period_end->toDateString(), (bool) $dist->acknowledged_open, $dist->id);
        $fresh = $this->profit->compute($dist->period_start->toDateString(), $dist->period_end->toDateString());
        $others = ProfitDistribution::where('status', '!=', 'cancelled')->where('id', '!=', $dist->id)->where('period_start', '<=', $dist->period_end)->where('period_end', '>=', $dist->period_start)->sum('distributed_amount');
        if (bccomp(bcadd((string) $others, (string) $dist->distributed_amount, 2), $fresh['distributable_profit'], 2) > 0) {
            $this->fail('exceeds_profit', ['max' => $fresh['distributable_profit']]);
        }
        if (bccomp((string) $dist->distributed_amount, $fresh['distributable_cash'], 2) > 0) {
            $this->fail('exceeds_cash', ['max' => $fresh['distributable_cash']]);
        }

        return $fresh;
    }

    private function locked(ProfitDistribution $dist): ProfitDistribution
    {
        return ProfitDistribution::whereKey($dist->id)->lockForUpdate()->firstOrFail();
    }

    public function approve(ProfitDistribution $dist, ?int $userId): ProfitDistribution
    {
        return DB::transaction(function () use ($dist, $userId) {
            $dist = $this->locked($dist);
            if ($dist->status !== 'draft') {
                $this->fail('bad_status');
            }
            if ($this->settings->bool('distribution_requires_second_approver') && (int) $dist->created_by === (int) $userId) {
                $this->fail('second_approver');
            }
            $this->assertPostable($dist);
            $dist->update(['status' => 'approved', 'approved_by' => $userId, 'approved_at' => now()]);
            AuditLog::create(['action' => 'distribution.approved', 'subject_type' => 'profit_distribution', 'subject_id' => $dist->id, 'user_id' => $userId, 'meta' => []]);

            return $dist;
        });
    }

    /**
     * Posting. If the fiscal year is not closed, the period profit still sits in revenue/expense accounts, so it is first moved into retained
     * earnings with a closing-type entry Dr income_summary / Cr retained_earnings (total equity unchanged: income_summary is debit, retained earnings
     * credit, the P&L accounts still carry the profit; year-end close later only sweeps the difference). Then
     * Dr retained_earnings / Cr distributions_payable per owner (party owner) and Dr retained_earnings / Cr reserves for the reserve.
     */
    public function post(ProfitDistribution $dist, ?int $userId): ProfitDistribution
    {
        return DB::transaction(function () use ($dist, $userId) {
            $dist = $this->locked($dist);
            if ($dist->status !== 'approved') {
                $this->fail('bad_status');
            }
            $fresh = $this->assertPostable($dist);
            $today = now()->toDateString();
            $transfer = null;
            if ($dist->fiscalYear()->value('status') !== 'closed' && bccomp($fresh['ledger_profit'], '0', 4) > 0) {
                $transfer = $this->journals->post(['entry_date' => $today, 'type' => 'closing', 'source_type' => 'profit_distribution', 'source_id' => $dist->id, 'created_by' => $userId,
                    'description' => __('erp_dist.memo.transfer', ['id' => $dist->id]), 'idempotency_key' => "profitdist:{$dist->id}:transfer"], [
                    ['account' => 'income_summary', 'debit' => $fresh['ledger_profit']], ['account' => 'retained_earnings', 'credit' => $fresh['ledger_profit']]]);
            }
            $lines = [];
            foreach ($dist->lines()->with('owner')->get() as $l) {
                $p = ['party_type' => 'owner', 'party_id' => $l->owner_id, 'memo' => __('erp_dist.memo.owner', ['owner' => $l->owner->name, 'id' => $dist->id])];
                $lines[] = ['account' => 'retained_earnings', 'debit' => (string) $l->amount] + $p;
                $lines[] = ['account' => 'distributions_payable', 'credit' => (string) $l->amount] + $p;
            }
            if (bccomp((string) $dist->reserve_amount, '0', 4) > 0) {
                $lines[] = ['account' => 'retained_earnings', 'debit' => (string) $dist->reserve_amount, 'memo' => __('erp_dist.memo.reserve')];
                $lines[] = ['account' => 'reserves', 'credit' => (string) $dist->reserve_amount, 'memo' => __('erp_dist.memo.reserve')];
            }
            $entry = $this->journals->post(['entry_date' => $today, 'type' => 'auto', 'source_type' => 'profit_distribution', 'source_id' => $dist->id, 'created_by' => $userId,
                'description' => __('erp_dist.memo.post', ['id' => $dist->id, 'from' => $dist->period_start->toDateString(), 'to' => $dist->period_end->toDateString()]),
                'idempotency_key' => "profitdist:{$dist->id}:post"], $lines);
            $dist->update(['status' => 'posted', 'posted_by' => $userId, 'posted_at' => now(), 'journal_entry_id' => $entry->id, 'transfer_entry_id' => $transfer?->id]);
            AuditLog::create(['action' => 'distribution.posted', 'subject_type' => 'profit_distribution', 'subject_id' => $dist->id, 'user_id' => $userId, 'meta' => ['entry' => $entry->number]]);

            return $dist;
        });
    }

    /** Record the cash payment of one owner's share: Dr distributions_payable / Cr cash-like. Refuses to overdraw the chosen account. */
    public function pay(ProfitDistributionLine $line, string $account, ?int $userId, ?string $date = null): ProfitDistributionLine
    {
        if (! in_array($account, PartyService::CASH_ROLES, true)) {
            throw new AccountingException(__('erp_ext.errors.bad_account'));
        }

        return DB::transaction(function () use ($line, $account, $userId, $date) {
            $line = ProfitDistributionLine::whereKey($line->id)->lockForUpdate()->firstOrFail();
            $dist = $this->locked($line->distribution);
            if ($dist->status !== 'posted') {
                $this->fail('bad_status');
            }
            if ($line->paid) {
                $this->fail('already_paid');
            }
            $accounts = Account::where('role_key', $account)->orderBy('id')->lockForUpdate()->get();
            $balance = (string) $accounts->sum('current_balance');
            if (bccomp($balance, (string) $line->amount, 4) < 0) {
                $this->fail('insufficient_cash', ['available' => number_format((float) $balance, 2), 'needed' => number_format((float) $line->amount, 2)]);
            }
            $p = ['party_type' => 'owner', 'party_id' => $line->owner_id, 'memo' => __('erp_dist.memo.payment', ['id' => $dist->id])];
            $entry = $this->journals->post(['entry_date' => $date ?: now()->toDateString(), 'type' => 'auto', 'source_type' => 'profit_distribution', 'source_id' => $dist->id, 'created_by' => $userId,
                'description' => __('erp_dist.memo.payment', ['id' => $dist->id]).' - '.$line->owner()->value('name'), 'idempotency_key' => "profitdist:{$dist->id}:line:{$line->id}:pay"], [
                ['account' => 'distributions_payable', 'debit' => (string) $line->amount] + $p, ['account' => $account, 'credit' => (string) $line->amount, 'memo' => $p['memo']]]);
            $line->update(['paid' => true, 'paid_at' => now(), 'paid_account' => $account, 'paid_by' => $userId, 'payment_entry_id' => $entry->id]);
            AuditLog::create(['action' => 'distribution.paid', 'subject_type' => 'profit_distribution', 'subject_id' => $dist->id, 'user_id' => $userId, 'meta' => ['owner_id' => $line->owner_id, 'amount' => (string) $line->amount]]);

            return $line;
        });
    }

    /** Draft/approved: just cancelled. Posted: reversal journals (only while nothing has been paid out). */
    public function cancel(ProfitDistribution $dist, ?int $userId, string $reason = ''): ProfitDistribution
    {
        return DB::transaction(function () use ($dist, $userId, $reason) {
            $dist = $this->locked($dist);
            if ($dist->status === 'cancelled') {
                return $dist;
            }
            if ($dist->status === 'posted') {
                if ($dist->lines()->where('paid', true)->exists()) {
                    $this->fail('cancel_paid');
                }
                foreach ([$dist->journal_entry_id, $dist->transfer_entry_id] as $id) {
                    if ($id && ($e = JournalEntry::find($id)) && $e->status === 'posted') {
                        $this->journals->reverse($e, $userId, null, $reason ?: __('erp_dist.memo.cancel'));
                    }
                }
            }
            $dist->update(['status' => 'cancelled', 'active_key' => null]);
            AuditLog::create(['action' => 'distribution.cancelled', 'subject_type' => 'profit_distribution', 'subject_id' => $dist->id, 'user_id' => $userId, 'meta' => ['reason' => $reason]]);

            return $dist;
        });
    }

    /** Per-owner summary of posted distributions whose period ends in [from,to]. */
    public function report(?string $from, ?string $to): array
    {
        $rows = ProfitDistributionLine::with(['owner', 'distribution'])->whereHas('distribution', fn ($q) => $q->where('status', 'posted')
            ->when($from, fn ($q) => $q->where('period_end', '>=', $from))->when($to, fn ($q) => $q->where('period_end', '<=', $to)))->get();
        $out = [];
        foreach ($rows->groupBy('owner_id') as $oid => $ls) {
            $acc = (string) $ls->sum('amount');
            $paid = (string) $ls->where('paid', true)->sum('amount');
            $out[] = ['owner' => $ls->first()->owner, 'accrued' => bcadd($acc, '0', 2), 'paid' => bcadd($paid, '0', 2), 'outstanding' => bcsub($acc, $paid, 2), 'lines' => $ls];
        }

        return $out;
    }

    public function ownerLines(Owner $owner)
    {
        return ProfitDistributionLine::with('distribution')->where('owner_id', $owner->id)->whereHas('distribution', fn ($q) => $q->where('status', '!=', 'cancelled'))->latest('id')->get();
    }
}
