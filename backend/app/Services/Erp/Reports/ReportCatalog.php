<?php

namespace App\Services\Erp\Reports;

use App\Models\Erp\Account;

/** Registry of every ERP report + normalized runner (used by pages, CSV and PDF exports). */
class ReportCatalog extends ReportBase
{
    /** key => [group, filters, needs erp.branches.view] */
    public const META = [
        'trial-balance' => ['statements', ['to', 'branch'], false],
        'general-ledger' => ['statements', ['from', 'to', 'branch', 'account'], false],
        'profit-loss' => ['statements', ['from', 'to', 'branch'], false],
        'balance-sheet' => ['statements', ['to', 'branch'], false],
        'cash-flow' => ['statements', ['from', 'to', 'branch'], false],
        'journal-entries' => ['statements', ['from', 'to', 'branch', 'account', 'type', 'customer', 'supplier', 'currency'], false],
        'receivables' => ['parties', ['to', 'customer'], false],
        'payables' => ['parties', ['to', 'supplier'], false],
        'sales' => ['operations', ['from', 'to', 'branch', 'customer', 'payment', 'currency'], false],
        'purchases' => ['operations', ['from', 'to', 'branch', 'supplier', 'currency'], false],
        'sales-returns' => ['operations', ['from', 'to', 'branch', 'customer', 'currency'], false],
        'expenses' => ['operations', ['from', 'to', 'branch', 'account', 'currency'], false],
        'cogs' => ['operations', ['from', 'to', 'branch'], false],
        'inventory-valuation' => ['operations', ['to', 'branch'], false],
        'branch-profitability' => ['control', ['from', 'to', 'branch'], true],
        'owner-equity' => ['control', ['from', 'to', 'branch'], false],
        'tax' => ['control', ['from', 'to', 'branch'], false],
        'reconciliation' => ['control', ['from', 'to', 'branch'], false],
    ];

    /** Reports with their own bespoke page; exports still use the normalized form. */
    public const LEGACY = ['trial-balance', 'general-ledger', 'profit-loss', 'balance-sheet'];

    public function __construct(private LedgerReports $ledger, private ActivityReports $activity, private CashFlowReport $cash, private PartyReports $parties, private InventoryReport $inventory,
        private BranchReports $branches, private EquityReport $equity, private TaxReport $tax, private ReconciliationReport $recon) {}

    public static function exists(string $key): bool
    {
        return isset(self::META[$key]);
    }

    public function run(string $key, ErpReportFilter $f, ?int $limit = 1000): array
    {
        return match ($key) {
            'trial-balance' => $this->trialBalance($f),
            'general-ledger' => $this->generalLedger($f, $limit === null ? 50000 : 5000),
            'profit-loss' => $this->profitLoss($f),
            'balance-sheet' => $this->balanceSheet($f),
            'cash-flow' => $this->cash->run($f),
            'journal-entries' => $this->activity->journalEntries($f, $limit),
            'receivables' => $this->parties->run('customer', $f),
            'payables' => $this->parties->run('supplier', $f),
            'sales' => $this->activity->sales($f),
            'purchases' => $this->activity->purchases($f),
            'sales-returns' => $this->activity->salesReturns($f),
            'expenses' => $this->activity->expenses($f),
            'cogs' => $this->activity->cogs($f),
            'inventory-valuation' => $this->inventory->run($f),
            'branch-profitability' => $this->branches->profitability($f),
            'owner-equity' => $this->equity->run($f),
            'tax' => $this->tax->run($f),
            'reconciliation' => $this->recon->run($f),
        };
    }

    private function nm(mixed $n): string
    {
        $a = is_string($n) ? json_decode($n, true) : $n;

        return is_array($a) ? (string) ($a[app()->getLocale()] ?? $a['en'] ?? reset($a)) : (string) $n;
    }

    private function trialBalance(ErpReportFilter $f): array
    {
        $d = $this->ledger->trialBalance($f->to, null, $f->branchId);
        $rows = array_map(fn ($r) => ['code' => $r['account']->code, 'account' => (string) $r['account']->name, 'debit' => $r['debit'], 'credit' => $r['credit']], $d['rows']);
        $rows[] = ['code' => __('erp.f.total'), 'debit' => $d['total_debit'], 'credit' => $d['total_credit'], '_total' => true];

        return $this->out('trial-balance', [$this->table([$this->col('code'), $this->col('account'), $this->col('debit', 'money'), $this->col('credit', 'money')], $rows)], [], $d['balanced'] ? [] : [__('erp.r.unbalanced')], ['balanced' => $d['balanced']]);
    }

    private function generalLedger(ErpReportFilter $f, int $limit = 5000): array
    {
        $acc = $f->accountId ? Account::find($f->accountId) : null;
        if (! $acc) {
            return $this->out('general-ledger', [$this->table([$this->col('date')], [])], [], [__('erp.r.pick_account')]);
        }
        $d = $this->ledger->generalLedger($acc, $f->from, $f->to, $f->branchId, $limit);
        $rows = [['memo' => __('erp.r.opening'), 'balance' => $d['opening'], '_total' => true]];
        foreach ($d['rows'] as $r) {
            $rows[] = ['date' => $r['line']->entry->entry_date->format('Y-m-d'), 'number' => $r['line']->entry->number, 'memo' => (string) ($r['line']->memo ?: $r['line']->entry->description),
                'debit' => (string) $r['line']->debit, 'credit' => (string) $r['line']->credit, 'balance' => $r['balance']];
        }
        $rows[] = ['memo' => __('erp.r.closing'), 'balance' => $d['closing'], '_total' => true];

        return $this->out('general-ledger', [$this->table([$this->col('date'), $this->col('number'), $this->col('memo'), $this->col('debit', 'money'), $this->col('credit', 'money'), $this->col('balance', 'money')], $rows)],
            [], $d['truncated'] ? [__('erp_rep.truncated', ['n' => $limit])] : [], ['opening' => $d['opening'], 'closing' => $d['closing']]);
    }

    private function profitLoss(ErpReportFilter $f): array
    {
        $d = $this->ledger->profitLoss($f->from, $f->to, $f->branchId);
        $rows = [];
        foreach (['revenue', 'cogs', 'opex'] as $g) {
            $rows[] = ['account' => __('erp.r.'.$g), '_head' => true];
            foreach ($d['groups'][$g] as $r) {
                $rows[] = ['code' => $r['account']->code, 'account' => (string) $r['account']->name, 'amount' => $r['amount']];
            }
            $rows[] = ['account' => __('erp.f.total'), 'amount' => $d['totals'][$g], '_total' => true];
            if ($g === 'cogs') {
                $rows[] = ['account' => __('erp.r.gross'), 'amount' => $d['gross_profit'], '_total' => true];
            }
        }
        $rows[] = ['account' => __('erp.r.net'), 'amount' => $d['net_profit'], '_total' => true];

        return $this->out('profit-loss', [$this->table([$this->col('code'), $this->col('account'), $this->col('amount', 'money')], $rows)], [$this->card('net', $d['net_profit'])], [], ['net_profit' => $d['net_profit']]);
    }

    private function balanceSheet(ErpReportFilter $f): array
    {
        $d = $this->ledger->balanceSheet($f->to, $f->branchId);
        $rows = [];
        foreach (['asset', 'liability', 'equity'] as $s) {
            $rows[] = ['account' => __('erp.types.'.$s), '_head' => true];
            foreach ($d['sections'][$s] as $r) {
                $rows[] = ['code' => $r['account']->code, 'account' => (string) $r['account']->name, 'amount' => $r['amount']];
            }
            if ($s === 'equity') {
                $rows[] = ['account' => __('erp.r.current_earnings'), 'amount' => $d['current_earnings']];
            }
            $rows[] = ['account' => __('erp.f.total'), 'amount' => $s === 'equity' ? $d['total_equity'] : $d['totals'][$s], '_total' => true];
        }

        return $this->out('balance-sheet', [$this->table([$this->col('code'), $this->col('account'), $this->col('amount', 'money')], $rows)], [], $d['balanced'] ? [] : [__('erp.r.unbalanced')], ['balanced' => $d['balanced']]);
    }
}
