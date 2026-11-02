<?php

namespace App\Services\Erp\Equity;

use App\Services\Erp\Parties\PartyService;
use App\Services\Erp\Reports\LedgerReports;
use App\Support\Money;
use Carbon\Carbon;

/**
 * Net profit and distributable amounts, derived ONLY from posted journal lines (see docs/ERP_PLAN.md "Profit methodology").
 * Net profit != cash: distributable = min(distributable profit, distributable cash).
 */
class ProfitService
{
    private const S = 4;
    public const CONTRA_ROLES = ['sales_returns', 'sales_discounts'];
    public const OTHER_EXPENSE_ROLES = ['fx_loss', 'other_expense'];
    public const LIABILITY_ROLES = ['payable', 'shipping_payable', 'affiliate_payable', 'distributions_payable', 'vat_payable', 'employee_payable'];

    public function __construct(private LedgerReports $reports, private ErpSettings $settings) {}

    /** Round DOWN to cents (never distribute more than exists). */
    public static function floor2(string $v): string
    {
        return bccomp($v, '0', self::S) <= 0 ? '0.00' : bcadd($v, '0', 2);
    }

    public function compute(string $from, string $to, ?int $branchId = null): array
    {
        $pl = $this->reports->profitLoss($from, $to, $branchId);
        $taxAccountId = $this->settings->get('tax_account_id') ? (int) $this->settings->get('tax_account_id') : null;

        $gross = '0';
        $contra = '0';
        $otherIncome = '0';
        foreach ($pl['groups']['revenue'] as $r) {
            $role = $r['account']->role_key;
            if (in_array($role, self::CONTRA_ROLES, true)) {
                $contra = bcadd($contra, $r['amount'], self::S); // credit-positive: a debit balance is negative
            } elseif ($role === 'other_income') {
                $otherIncome = bcadd($otherIncome, $r['amount'], self::S);
            } else {
                $gross = bcadd($gross, $r['amount'], self::S);
            }
        }
        $returns = bcmul($contra, '-1', self::S);
        $netRevenue = bcsub($gross, $returns, self::S);
        $cogs = $pl['totals']['cogs'];
        $grossProfit = bcsub($netRevenue, $cogs, self::S);

        $opexRows = [];
        $opex = '0';
        $otherExpense = '0';
        $bookedTax = '0';
        foreach ($pl['groups']['opex'] as $r) {
            if ($taxAccountId && $r['account']->id === $taxAccountId) {
                $bookedTax = bcadd($bookedTax, $r['amount'], self::S);
            } elseif (in_array($r['account']->role_key, self::OTHER_EXPENSE_ROLES, true)) {
                $otherExpense = bcadd($otherExpense, $r['amount'], self::S);
            } else {
                $opex = bcadd($opex, $r['amount'], self::S);
                $opexRows[] = $r;
            }
        }
        $operating = bcsub($grossProfit, $opex, self::S);
        $beforeTax = bcsub(bcadd($operating, $otherIncome, self::S), $otherExpense, self::S);
        $ledgerProfit = $pl['net_profit'];

        // tax: booked account wins; otherwise an estimate (not posted) on positive profit
        $estimated = $taxAccountId ? '0' : (bccomp($beforeTax, '0', self::S) > 0 ? Money::percent($beforeTax, (string) $this->settings->get('tax_rate')) : '0');
        $tax = $taxAccountId ? $bookedTax : $estimated;
        $netProfit = bcsub($beforeTax, $tax, self::S);

        // retained earnings brought forward = retained + income summary + residual (unclosed) earnings of everything before $from
        $prior = '0';
        if (! $branchId) {
            $dayBefore = Carbon::parse($from)->subDay()->toDateString();
            $b = $this->reports->balancesByRole($dayBefore);
            $prior = bcadd(bcadd($b['retained_earnings'] ?? '0', $b['income_summary'] ?? '0', self::S), $this->reports->residualEarnings($dayBefore), self::S);
        }
        $usePrior = $this->settings->bool('distribute_prior_retained');
        $pool = $usePrior ? bcadd($prior, $netProfit, self::S) : (bccomp($prior, '0', self::S) < 0 ? bcadd($prior, $netProfit, self::S) : $netProfit);
        $afterLosses = bccomp($pool, '0', self::S) > 0 ? $pool : '0';
        $pct = (string) $this->settings->get('reserve_percent');
        $reserve = Money::percent($afterLosses, $pct);
        $distributableProfit = self::floor2(bcsub($afterLosses, $reserve, self::S));

        // cash side, as of now
        $bal = $this->reports->balancesByRole(null, $branchId);
        $cashRows = [];
        $cash = '0';
        foreach (PartyService::CASH_ROLES as $role) {
            $cashRows[$role] = $bal[$role] ?? '0.0000';
            $cash = bcadd($cash, $cashRows[$role], self::S);
        }
        $liabRows = [];
        $liab = '0';
        foreach (self::LIABILITY_ROLES as $role) {
            $liabRows[$role] = $bal[$role] ?? '0.0000';
            $liab = bcadd($liab, $liabRows[$role], self::S);
        }
        $reservesBalance = $bal['reserves'] ?? '0.0000';
        $buffer = (string) $this->settings->get('distribution_min_cash_buffer');
        $cashAfter = bcsub(bcsub(bcsub(bcsub(bcsub($cash, $liab, self::S), $estimated, self::S), $reservesBalance, self::S), $reserve, self::S), $buffer, self::S);
        $distributableCash = self::floor2($cashAfter);

        return [
            'from' => $from, 'to' => $to, 'branch_id' => $branchId,
            'gross_revenue' => $gross, 'returns_discounts' => $returns, 'net_revenue' => $netRevenue, 'cogs' => $cogs, 'gross_profit' => $grossProfit,
            'opex_rows' => $opexRows, 'opex' => $opex, 'operating_profit' => $operating, 'other_income' => $otherIncome, 'other_expense' => $otherExpense,
            'profit_before_tax' => $beforeTax, 'tax' => $tax, 'tax_estimated' => $estimated, 'net_profit' => $netProfit, 'ledger_profit' => $ledgerProfit,
            'prior_retained' => $prior, 'prior_losses' => bccomp($prior, '0', self::S) < 0 ? bcmul($prior, '-1', self::S) : '0.0000', 'profit_after_losses' => $afterLosses,
            'reserve_percent' => $pct, 'reserve_amount' => $reserve, 'distributable_profit' => $distributableProfit,
            'cash_rows' => $cashRows, 'cash_total' => $cash, 'liability_rows' => $liabRows, 'liabilities_due' => $liab, 'reserves_balance' => $reservesBalance,
            'cash_buffer' => $buffer, 'distributable_cash' => $distributableCash,
            'distributable' => bccomp($distributableProfit, $distributableCash, 2) <= 0 ? $distributableProfit : $distributableCash,
            'cash_limited' => bccomp($distributableCash, $distributableProfit, 2) < 0,
        ];
    }
}
