<?php

namespace App\Services\Erp\Reports;

use App\Models\Erp\Account;

/** Accounts receivable / payable: per-party balances + ageing from PartyLedger, reconciled to the control accounts computed from lines. */
class PartyReports extends ReportBase
{
    public function __construct(private PartyLedger $ledger) {}

    public function run(string $type, ErpReportFilter $f): array
    {
        $to = $f->to;
        $filter = $type === 'customer' ? $f->customerId : $f->supplierId;
        $bal = collect($this->ledger->balances($type, $to))->when($filter, fn ($c) => $c->where('id', $filter))->values();
        $age = collect($this->ledger->ageing($type, $to)['rows'])->keyBy('id');
        $rows = $bal->map(function ($b) use ($age) {
            $a = $age[$b['id']] ?? null;

            return ['party' => $b['name'], 'b0' => $a['b0'] ?? '0.0000', 'b31' => $a['b31'] ?? '0.0000', 'b61' => $a['b61'] ?? '0.0000', 'b90' => $a['b90'] ?? '0.0000',
                'outstanding' => $b['outstanding'], 'advance' => $b['advance'], 'balance' => $b['balance']];
        })->all();
        $parties = $this->sum($rows, 'balance');
        [$main, $adv] = PartyLedger::roles($type);
        $ids = Account::whereIn('role_key', [$main, $adv])->pluck('id', 'role_key');
        $sign = $type === 'customer' ? '1' : '-1';
        $control = bcmul(bcsub($this->debitBalance([$ids[$main] ?? 0], $to), '0', self::S), $sign, self::S);
        $control = bcadd($control, bcmul($this->debitBalance([$ids[$adv] ?? 0], $to), $sign, self::S), self::S); // advance account is the opposite normal balance: (dr-cr) adds in party sense
        $unattr = bcsub($control, $parties, self::S);
        $tot = ['party' => __('erp.f.total'), '_total' => true];
        foreach (['b0', 'b31', 'b61', 'b90', 'outstanding', 'advance', 'balance'] as $k) {
            $tot[$k] = $this->sum($rows, $k);
        }
        $rows[] = $tot;
        if (! $filter) {
            $rows[] = ['party' => __('erp_rep.unattributed'), 'balance' => $unattr];
            $rows[] = ['party' => __('erp_rep.control'), 'balance' => $control, '_total' => true];
        }
        $key = $type === 'customer' ? 'receivables' : 'payables';

        return $this->out($key, [$this->table([$this->col('party'), $this->col('b0', 'money'), $this->col('b31', 'money'), $this->col('b61', 'money'), $this->col('b90', 'money'),
            $this->col('outstanding', 'money'), $this->col('advance', 'money'), $this->col('balance', 'money')], $rows)],
            [$this->card('balance', $parties), $this->card('control', $control), $this->card('unattributed', $unattr)], [], ['parties' => $parties, 'control' => $control, 'unattributed' => $unattr]);
    }
}
