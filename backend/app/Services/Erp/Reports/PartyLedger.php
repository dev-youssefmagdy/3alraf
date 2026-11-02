<?php

namespace App\Services\Erp\Reports;

use App\Models\Customer;
use App\Models\Erp\Account;
use App\Models\Erp\JournalLine;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

/**
 * Customer / supplier ledgers derived ONLY from posted journal lines (party_type + party_id). A party's balance is expressed in the party's own
 * sense - customer: what they owe us (receivable - customer advances); supplier: what we owe them (payable - supplier advances) - so a positive
 * number is always "open" and an advance simply is a negative open item. No maintained totals; tenant scope comes from the models.
 */
class PartyLedger
{
    private const S = 4;

    public const TYPES = ['customer', 'supplier'];

    /** [main control role, advance role, sign applied to (debit - credit)] */
    private const MAP = [
        'customer' => ['receivable', 'customer_advances', 1],
        'supplier' => ['payable', 'supplier_advances', -1],
    ];

    public static function roles(string $type): array
    {
        return [self::MAP[$type][0], self::MAP[$type][1]];
    }

    private function sign(string $type): int
    {
        return self::MAP[$type][2];
    }

    private function accountIds(string $type): array
    {
        return Account::whereIn('role_key', self::roles($type))->pluck('id', 'role_key')->all();
    }

    private function base(string $type, ?string $to = null)
    {
        return JournalLine::query()->join('erp_journal_entries as e', 'e.id', '=', 'erp_journal_lines.entry_id')
            ->whereIn('e.status', ['posted', 'reversed'])->where('erp_journal_lines.party_type', $type)
            ->whereIn('erp_journal_lines.account_id', array_values($this->accountIds($type)))
            ->when($to, fn ($q) => $q->where('e.entry_date', '<=', $to));
    }

    private function net(string $dr, string $cr, string $type): string
    {
        $n = bcsub($dr, $cr, self::S);

        return $this->sign($type) === 1 ? $n : bcmul($n, '-1', self::S);
    }

    public function name(string $type, ?int $id): string
    {
        if (! $id) {
            return '';
        }
        $m = $type === 'customer' ? Customer::withTrashed()->find($id) : Supplier::withTrashed()->find($id);

        return (string) ($m->name ?? '#'.$id);
    }

    /** Party row for lists: id, name, balance (net open), advance (credit held, positive), outstanding (net of advances excluded). */
    public function balances(string $type, ?string $to = null): array
    {
        [$main] = self::roles($type);
        $acc = $this->accountIds($type);
        $rows = $this->base($type, $to)->groupBy('erp_journal_lines.party_id', 'erp_journal_lines.account_id')
            ->select('erp_journal_lines.party_id', 'erp_journal_lines.account_id', DB::raw('SUM(erp_journal_lines.debit) as dr'), DB::raw('SUM(erp_journal_lines.credit) as cr'))->get();
        $by = [];
        foreach ($rows as $r) {
            $id = (int) $r->party_id;
            $by[$id] ??= ['id' => $id, 'outstanding' => '0.0000', 'advance' => '0.0000', 'balance' => '0.0000'];
            $v = $this->net((string) $r->dr, (string) $r->cr, $type);
            $isMain = ($acc[$main] ?? null) === (int) $r->account_id;
            $by[$id][$isMain ? 'outstanding' : 'advance'] = $isMain ? $v : bcmul($v, '-1', self::S); // advance shown as positive credit held
            $by[$id]['balance'] = bcadd($by[$id]['balance'], $v, self::S);
        }
        $names = $this->names($type, array_keys($by));
        foreach ($by as $id => &$row) {
            $row['name'] = $names[$id] ?? ('#'.$id);
        }
        unset($row);
        uasort($by, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return array_values($by);
    }

    private function names(string $type, array $ids): array
    {
        $ids = array_filter($ids);
        if (! $ids) {
            return [];
        }

        return ($type === 'customer' ? Customer::withTrashed() : Supplier::withTrashed())->whereIn('id', $ids)->pluck('name', 'id')->all();
    }

    /** Open (outstanding) amount of one party on the main control account, and the advance credit it holds. */
    public function position(string $type, int $id): array
    {
        $r = collect($this->balances($type))->firstWhere('id', $id);

        return ['outstanding' => $r['outstanding'] ?? '0.0000', 'advance' => $r['advance'] ?? '0.0000', 'balance' => $r['balance'] ?? '0.0000'];
    }

    /** Statement over both accounts in the party's sense, with running balance. */
    public function statement(string $type, int $id, string $from, string $to): array
    {
        $opening = '0';
        $o = $this->base($type)->where('erp_journal_lines.party_id', $id)->where('e.entry_date', '<', $from)
            ->selectRaw('COALESCE(SUM(erp_journal_lines.debit),0) as dr, COALESCE(SUM(erp_journal_lines.credit),0) as cr')->first();
        $opening = $this->net((string) $o->dr, (string) $o->cr, $type);
        $lines = JournalLine::query()->with(['entry', 'account'])->select('erp_journal_lines.*')
            ->join('erp_journal_entries as e', 'e.id', '=', 'erp_journal_lines.entry_id')
            ->whereIn('e.status', ['posted', 'reversed'])->where('erp_journal_lines.party_type', $type)->where('erp_journal_lines.party_id', $id)
            ->whereIn('erp_journal_lines.account_id', array_values($this->accountIds($type)))->whereBetween('e.entry_date', [$from, $to])
            ->orderBy('e.entry_date')->orderBy('erp_journal_lines.id')->get();
        $bal = $opening;
        $rows = [];
        foreach ($lines as $l) {
            $delta = $this->net((string) $l->debit, (string) $l->credit, $type);
            $bal = bcadd($bal, $delta, self::S);
            $rows[] = ['line' => $l, 'delta' => $delta, 'balance' => $bal];
        }

        return ['opening' => $opening, 'rows' => $rows, 'closing' => $bal];
    }

    /**
     * Ageing from OPEN ITEMS: per party the "charge" lines (positive in the party's sense) are consumed oldest-first by the
     * settlements (negative lines: payments, advances, notes); what remains of each charge is bucketed by age in days at $asOf.
     * Surplus settlements (credit balance) are reported as `credit`, not netted into the buckets.
     */
    public function ageing(string $type, ?string $asOf = null): array
    {
        $asOf ??= now()->toDateString();
        $lines = $this->base($type, $asOf)->select('erp_journal_lines.party_id', 'erp_journal_lines.debit', 'erp_journal_lines.credit', 'e.entry_date', 'e.id as eid', 'erp_journal_lines.id as lid')
            ->orderBy('e.entry_date')->orderBy('erp_journal_lines.id')->get()->groupBy('party_id');
        $names = $this->names($type, $lines->keys()->all());
        $buckets = ['b0' => '0', 'b31' => '0', 'b61' => '0', 'b90' => '0'];
        $total = $buckets + ['credit' => '0', 'total' => '0'];
        $rows = [];
        foreach ($lines as $pid => $ls) {
            $charges = [];
            $settle = '0';
            foreach ($ls as $l) {
                $d = $this->net((string) $l->debit, (string) $l->credit, $type);
                if (bccomp($d, '0', self::S) > 0) {
                    $charges[] = ['date' => (string) \Illuminate\Support\Carbon::parse($l->entry_date)->toDateString(), 'amount' => $d];
                } else {
                    $settle = bcadd($settle, bcmul($d, '-1', self::S), self::S);
                }
            }
            $row = ['id' => (int) $pid, 'name' => $names[$pid] ?? ($pid ? '#'.$pid : ''), 'unattributed' => ! $pid] + $buckets + ['credit' => '0', 'total' => '0'];
            foreach ($charges as $c) {
                $use = bccomp($settle, $c['amount'], self::S) >= 0 ? $c['amount'] : $settle;
                $settle = bcsub($settle, $use, self::S);
                $left = bcsub($c['amount'], $use, self::S);
                if (bccomp($left, '0', self::S) <= 0) {
                    continue;
                }
                $age = (int) \Illuminate\Support\Carbon::parse($c['date'])->diffInDays(\Illuminate\Support\Carbon::parse($asOf), false);
                $k = $age <= 30 ? 'b0' : ($age <= 60 ? 'b31' : ($age <= 90 ? 'b61' : 'b90'));
                $row[$k] = bcadd($row[$k], $left, self::S);
                $row['total'] = bcadd($row['total'], $left, self::S);
            }
            $row['credit'] = $settle;
            if (bccomp($row['total'], '0', self::S) === 0 && bccomp($row['credit'], '0', self::S) === 0) {
                continue;
            }
            foreach (array_keys($total) as $k) {
                $total[$k] = bcadd($total[$k], $row[$k], self::S);
            }
            $rows[] = $row;
        }
        usort($rows, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return ['rows' => $rows, 'totals' => $total, 'as_of' => $asOf];
    }

    /** Sum of party balances (+ lines without a party) must equal the control accounts' balances. */
    public function reconcile(string $type): array
    {
        $acc = Account::whereIn('role_key', self::roles($type))->get()->keyBy('role_key');
        $attributed = $this->balances($type);
        $parties = array_reduce($attributed, fn ($c, $r) => bcadd($c, $r['balance'], self::S), '0');
        // lines on the control accounts that carry no party (guest orders, manual entries)
        $unattr = '0';
        $control = '0';
        foreach ($acc as $a) {
            $q = JournalLine::query()->join('erp_journal_entries as e', 'e.id', '=', 'erp_journal_lines.entry_id')->whereIn('e.status', ['posted', 'reversed'])
                ->where('erp_journal_lines.account_id', $a->id);
            $all = (clone $q)->selectRaw('COALESCE(SUM(erp_journal_lines.debit),0) as dr, COALESCE(SUM(erp_journal_lines.credit),0) as cr')->first();
            $withParty = (clone $q)->where('erp_journal_lines.party_type', $type)->whereNotNull('erp_journal_lines.party_id')->selectRaw('COALESCE(SUM(erp_journal_lines.debit),0) as dr, COALESCE(SUM(erp_journal_lines.credit),0) as cr')->first();
            $unattr = bcadd($unattr, $this->net(bcsub((string) $all->dr, (string) $withParty->dr, self::S), bcsub((string) $all->cr, (string) $withParty->cr, self::S), $type), self::S);
            // the maintained control balance (cache) is what we reconcile against
            $cb = (string) $a->current_balance;
            $sgn = $a->normal_balance === 'debit' ? 1 : -1;
            $inPartySense = $sgn === $this->sign($type) ? $cb : bcmul($cb, '-1', self::S);
            $control = bcadd($control, $inPartySense, self::S);
        }
        $computed = bcadd($parties, $unattr, self::S);

        return ['parties' => $parties, 'unattributed' => $unattr, 'computed' => $computed, 'control' => $control, 'ok' => bccomp($computed, $control, self::S) === 0];
    }
}
