<?php

namespace App\Services\Erp\Assets;

use App\Exceptions\AccountingException;
use App\Models\Branch;
use App\Models\Erp\Account;
use App\Models\Erp\AuditLog;
use App\Models\Erp\FixedAsset;
use App\Models\Erp\JournalEntry;
use App\Models\Supplier;
use App\Services\Erp\Accounting\AccountResolver;
use App\Services\Erp\Accounting\CoaTemplate;
use App\Services\Erp\Accounting\JournalService;
use App\Services\Erp\Parties\PartyService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fixed assets: registration, monthly depreciation (idempotent per asset+month, never below salvage), disposal and branch transfer.
 * All ledger writes go through JournalService; assets.accumulated_depreciation is a cache maintained in the same transaction as the entry.
 */
class AssetService
{
    public function __construct(private JournalService $journals, private CoaTemplate $coa) {}

    private function ensureChart(): void
    {
        if (! Account::where('role_key', 'fixed_assets')->exists()) {
            $this->coa->seed(current_tenant());
            app(AccountResolver::class)->flush();
        }
    }

    /** $paidFrom: a cash-like role (cash|bank|...) or "payable" (supplier bill, optional supplier party). */
    public function register(array $d, string $paidFrom = 'cash'): FixedAsset
    {
        $cost = Money::round((string) $d['cost']);
        $salvage = Money::round((string) ($d['salvage_value'] ?? 0));
        if (Money::cmp($cost, '0') <= 0 || Money::cmp($salvage, '0') < 0 || Money::cmp($salvage, $cost) > 0 || (int) $d['useful_life_months'] < 1) {
            throw new AccountingException(__('erp_ext.errors.asset_invalid'));
        }
        if ($paidFrom !== 'payable' && ! in_array($paidFrom, PartyService::CASH_ROLES, true)) {
            throw new AccountingException(__('erp_ext.errors.bad_account'));
        }
        if (! empty($d['branch_id'])) {
            Branch::findOrFail($d['branch_id']);
        }
        if (! empty($d['supplier_id'])) {
            Supplier::findOrFail($d['supplier_id']);
        }
        $this->ensureChart();

        return DB::transaction(function () use ($d, $cost, $salvage, $paidFrom) {
            $a = FixedAsset::create(['name' => $d['name'], 'category' => $d['category'] ?? null, 'acquisition_date' => $d['acquisition_date'], 'cost' => $cost, 'salvage_value' => $salvage,
                'useful_life_months' => (int) $d['useful_life_months'], 'method' => $d['method'] ?? 'straight_line', 'frequency' => 'monthly', 'branch_id' => $d['branch_id'] ?? null,
                'supplier_id' => $d['supplier_id'] ?? null, 'status' => 'active', 'note' => $d['note'] ?? null, 'created_by' => auth('tenant')->id()]);
            $b = $a->branch_id;
            $credit = ['account' => $paidFrom, 'credit' => $cost, 'branch_id' => $b, 'memo' => $a->name];
            if ($paidFrom === 'payable' && $a->supplier_id) {
                $credit += ['party_type' => 'supplier', 'party_id' => $a->supplier_id];
            }
            $e = $this->journals->post(['entry_date' => $a->acquisition_date->toDateString(), 'type' => 'auto', 'description' => __('erp_ext.fa.memo.register').' - '.$a->name, 'source_type' => 'fixed_asset', 'source_id' => $a->id,
                'idempotency_key' => 'asset:'.$a->id.':register', 'branch_id' => $b, 'created_by' => auth('tenant')->id()],
                [['account' => 'fixed_assets', 'debit' => $cost, 'branch_id' => $b, 'memo' => $a->name], $credit]);
            $a->update(['registration_entry_id' => $e->id]);

            return $a;
        });
    }

    /** Depreciation due for the asset in the month following the already depreciated ones (before the cap). */
    public function monthlyAmount(FixedAsset $a): string
    {
        $base = bcsub((string) $a->cost, (string) $a->salvage_value, 4);
        if ($a->method === 'declining_balance') {
            $nbv = bcsub((string) $a->cost, (string) $a->accumulated_depreciation, 4);
            $m = bcmul($nbv, bcdiv('2', (string) $a->useful_life_months, 8), 4);
        } else {
            $m = bcdiv($base, (string) $a->useful_life_months, 8);
        }

        return Money::round($m);
    }

    /**
     * Depreciate every not yet depreciated month from the acquisition month up to and including $period (YYYY-MM). Idempotent: months already
     * posted (last_depreciated_period / idempotency key) are skipped. Returns the number of entries posted.
     */
    public function depreciate(FixedAsset $asset, string $period): int
    {
        $n = 0;
        DB::transaction(function () use ($asset, $period, &$n) {
            $a = FixedAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            if ($a->status !== 'active') {
                return;
            }
            $this->ensureChart();
            $m = $a->last_depreciated_period ? Carbon::parse($a->last_depreciated_period.'-01')->addMonth() : $a->acquisition_date->copy()->startOfMonth();
            $end = Carbon::parse($period.'-01');
            $maxDep = bcsub((string) $a->cost, (string) $a->salvage_value, 4);
            for (; $m->lte($end); $m->addMonth()) {
                $ym = $m->format('Y-m');
                $left = bcsub($maxDep, (string) $a->accumulated_depreciation, 4);
                if (bccomp($left, '0', 4) <= 0) {
                    $a->last_depreciated_period = $ym; // fully depreciated: nothing more is ever booked
                    continue;
                }
                $amt = Money::min($this->monthlyAmount($a), $left);
                if (Money::cmp($amt, '0') <= 0) {
                    $a->last_depreciated_period = $ym;
                    continue;
                }
                // a life ending this month takes the remainder so rounding never leaves a residue
                if ($a->acquisition_date->copy()->startOfMonth()->addMonths($a->useful_life_months - 1)->format('Y-m') <= $ym && $a->method === 'straight_line') {
                    $amt = $left;
                }
                $key = 'asset:'.$a->id.':dep:'.$ym;
                $exists = JournalEntry::where('idempotency_key', $key)->exists();
                if (! $exists) {
                    $b = $a->branch_id;
                    $this->journals->post(['entry_date' => $m->copy()->endOfMonth()->toDateString(), 'type' => 'auto', 'description' => __('erp_ext.fa.memo.depreciation', ['period' => $ym]).' - '.$a->name,
                        'source_type' => 'fixed_asset', 'source_id' => $a->id, 'idempotency_key' => $key, 'branch_id' => $b, 'created_by' => auth('tenant')->id()],
                        [['account' => 'depreciation_expense', 'debit' => $amt, 'branch_id' => $b, 'memo' => $a->name], ['account' => 'accumulated_depreciation', 'credit' => $amt, 'branch_id' => $b, 'memo' => $a->name]]);
                    $a->accumulated_depreciation = bcadd((string) $a->accumulated_depreciation, $amt, 4);
                    $n++;
                }
                $a->last_depreciated_period = $ym;
            }
            $a->save();
        });
        $asset->refresh();

        return $n;
    }

    /** Dispose: catches depreciation up to the disposal month, then Dr cash + Dr accumulated / Cr fixed_assets, balancing with gain (other_income) or loss (other_expense). */
    public function dispose(FixedAsset $asset, string $date, string $proceeds = '0', string $into = 'cash'): FixedAsset
    {
        $proceeds = Money::round($proceeds);
        if (Money::cmp($proceeds, '0') < 0 || ! in_array($into, PartyService::CASH_ROLES, true)) {
            throw new AccountingException(__('erp_ext.errors.bad_account'));
        }

        return DB::transaction(function () use ($asset, $date, $proceeds, $into) {
            $a = FixedAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            if ($a->status !== 'active') {
                throw new AccountingException(__('erp_ext.errors.asset_disposed'));
            }
            if (Carbon::parse($date)->lt($a->acquisition_date)) {
                throw new AccountingException(__('erp_ext.errors.asset_invalid'));
            }
            $this->depreciate($a, Carbon::parse($date)->format('Y-m'));
            $a->refresh();
            $b = $a->branch_id;
            $acc = (string) $a->accumulated_depreciation;
            $gain = bcsub(bcadd($proceeds, $acc, 4), (string) $a->cost, 4); // proceeds - net book value
            $lines = [['account' => 'fixed_assets', 'credit' => $a->cost, 'branch_id' => $b, 'memo' => $a->name]];
            if (Money::cmp($acc, '0') > 0) {
                $lines[] = ['account' => 'accumulated_depreciation', 'debit' => $acc, 'branch_id' => $b, 'memo' => $a->name];
            }
            if (Money::cmp($proceeds, '0') > 0) {
                $lines[] = ['account' => $into, 'debit' => $proceeds, 'branch_id' => $b, 'memo' => $a->name];
            }
            if (bccomp($gain, '0', 4) > 0) {
                $lines[] = ['account' => 'other_income', 'credit' => $gain, 'branch_id' => $b, 'memo' => $a->name];
            } elseif (bccomp($gain, '0', 4) < 0) {
                $lines[] = ['account' => 'other_expense', 'debit' => bcmul($gain, '-1', 4), 'branch_id' => $b, 'memo' => $a->name];
            }
            $e = $this->journals->post(['entry_date' => $date, 'type' => 'auto', 'description' => __('erp_ext.fa.memo.disposal').' - '.$a->name, 'source_type' => 'fixed_asset', 'source_id' => $a->id,
                'idempotency_key' => 'asset:'.$a->id.':dispose', 'branch_id' => $b, 'created_by' => auth('tenant')->id()], $lines);
            $a->update(['status' => 'disposed', 'disposed_at' => $date, 'disposal_proceeds' => $proceeds, 'disposal_gain_loss' => $gain, 'disposal_entry_id' => $e->id]);

            return $a;
        });
    }

    /** Move an asset (cost and accumulated depreciation) to another branch; the ledger follows with a reclass entry. */
    public function transfer(FixedAsset $asset, int $toBranchId, ?string $date = null): FixedAsset
    {
        Branch::findOrFail($toBranchId);

        return DB::transaction(function () use ($asset, $toBranchId, $date) {
            $a = FixedAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            if ($a->status !== 'active') {
                throw new AccountingException(__('erp_ext.errors.asset_disposed'));
            }
            if ((int) $a->branch_id === $toBranchId) {
                return $a;
            }
            $from = $a->branch_id;
            $acc = (string) $a->accumulated_depreciation;
            $lines = [
                ['account' => 'fixed_assets', 'debit' => $a->cost, 'branch_id' => $toBranchId, 'memo' => $a->name], ['account' => 'fixed_assets', 'credit' => $a->cost, 'branch_id' => $from, 'memo' => $a->name],
            ];
            if (Money::cmp($acc, '0') > 0) {
                $lines[] = ['account' => 'accumulated_depreciation', 'debit' => $acc, 'branch_id' => $from, 'memo' => $a->name];
                $lines[] = ['account' => 'accumulated_depreciation', 'credit' => $acc, 'branch_id' => $toBranchId, 'memo' => $a->name];
            }
            $this->journals->post(['entry_date' => $date ?? now()->toDateString(), 'type' => 'auto', 'description' => __('erp_ext.fa.memo.transfer').' - '.$a->name, 'source_type' => 'fixed_asset', 'source_id' => $a->id,
                'idempotency_key' => 'asset:'.$a->id.':transfer:'.($from ?? 0).'>'.$toBranchId.':'.\Illuminate\Support\Str::uuid(), 'branch_id' => $toBranchId, 'created_by' => auth('tenant')->id()], $lines);
            $a->update(['branch_id' => $toBranchId]);
            AuditLog::create(['action' => 'asset.transferred', 'subject_type' => 'fixed_asset', 'subject_id' => $a->id, 'user_id' => auth('tenant')->id(), 'meta' => ['from' => $from, 'to' => $toBranchId]]);

            return $a;
        });
    }

    /** Asset register totals + reconciliation with the fixed-asset accounts. */
    public function report(): array
    {
        $assets = FixedAsset::with('branch')->orderBy('status')->orderBy('acquisition_date')->get();
        $active = $assets->where('status', 'active');
        $cat = [];
        foreach ($active as $a) {
            $k = $a->category ?: '-';
            $cat[$k] ??= ['cost' => '0', 'acc' => '0', 'nbv' => '0'];
            $cat[$k]['cost'] = bcadd($cat[$k]['cost'], (string) $a->cost, 4);
            $cat[$k]['acc'] = bcadd($cat[$k]['acc'], (string) $a->accumulated_depreciation, 4);
            $cat[$k]['nbv'] = bcadd($cat[$k]['nbv'], $a->book_value, 4);
        }
        $cost = $active->reduce(fn ($c, $a) => bcadd($c, (string) $a->cost, 4), '0');
        $acc = $active->reduce(fn ($c, $a) => bcadd($c, (string) $a->accumulated_depreciation, 4), '0');
        $glCost = (string) (Account::where('role_key', 'fixed_assets')->value('current_balance') ?? '0');
        $glAcc = bcmul((string) (Account::where('role_key', 'accumulated_depreciation')->value('current_balance') ?? '0'), '-1', 4); // contra-asset: credit balance

        return ['assets' => $assets, 'categories' => $cat, 'cost' => $cost, 'accumulated' => $acc, 'nbv' => bcsub($cost, $acc, 4), 'gl_cost' => $glCost, 'gl_accumulated' => $glAcc,
            'reconciled' => bccomp($cost, $glCost, 4) === 0 && bccomp($acc, $glAcc, 4) === 0];
    }
}
