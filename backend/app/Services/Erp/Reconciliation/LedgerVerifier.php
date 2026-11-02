<?php

namespace App\Services\Erp\Reconciliation;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Structural integrity audit of ONE tenant's ledger (the `erp:verify` command). Pure read-only, every query filters on tenant_id explicitly.
 * Complements the business reconciliation (ReconcileRunner compares the ledger to orders/stock; this checks the ledger against itself).
 *
 * Checks: unbalanced_entries, balance_drift (accounts.current_balance vs recomputed from lines), orphan_lines, bad_lines (negative / two-sided),
 * entries_without_lines, duplicate_idempotency_keys, duplicate_numbers, posted_without_period.
 */
class LedgerVerifier
{
    public const CHECKS = ['unbalanced_entries', 'balance_drift', 'orphan_lines', 'bad_lines', 'entries_without_lines', 'duplicate_idempotency_keys', 'duplicate_numbers', 'posted_without_period'];

    private const SAMPLES = 10;

    /** @return array{tenant:string,ok:bool,failures:int,checks:array<string,array{ok:bool,count:int,samples:array}>} */
    public function run(Tenant $tenant): array
    {
        $t = $tenant->id;
        $live = ['posted', 'reversed'];
        $res = [];
        $put = function (string $key, $rows) use (&$res) {
            $rows = collect($rows);
            $res[$key] = ['ok' => $rows->isEmpty(), 'count' => $rows->count(), 'samples' => $rows->take(self::SAMPLES)->values()->all()];
        };

        $put('unbalanced_entries', DB::table('erp_journal_entries as e')->join('erp_journal_lines as l', 'l.entry_id', '=', 'e.id')
            ->where('e.tenant_id', $t)->whereIn('e.status', $live)->groupBy('e.id', 'e.number')
            ->havingRaw('ROUND(SUM(l.debit), 4) <> ROUND(SUM(l.credit), 4)')->selectRaw('e.id, e.number, SUM(l.debit) as debit, SUM(l.credit) as credit')->get());

        $put('balance_drift', DB::table('erp_accounts as a')->leftJoin('erp_journal_lines as l', 'l.account_id', '=', 'a.id')
            ->leftJoin('erp_journal_entries as e', fn ($j) => $j->on('e.id', '=', 'l.entry_id')->whereIn('e.status', $live))
            ->where('a.tenant_id', $t)->groupBy('a.id', 'a.code', 'a.normal_balance', 'a.current_balance')
            ->selectRaw("a.id, a.code, a.current_balance as cached, COALESCE(SUM(CASE WHEN e.id IS NULL THEN 0 WHEN a.normal_balance = 'debit' THEN l.debit - l.credit ELSE l.credit - l.debit END), 0) as recomputed")
            ->get()->filter(fn ($r) => bccomp((string) $r->cached, (string) $r->recomputed, 4) !== 0)->values());

        $put('orphan_lines', DB::table('erp_journal_lines as l')->leftJoin('erp_journal_entries as e', 'e.id', '=', 'l.entry_id')->leftJoin('erp_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('l.tenant_id', $t)->where(fn ($q) => $q->whereNull('e.id')->orWhereNull('a.id')->orWhereColumn('e.tenant_id', '!=', 'l.tenant_id')->orWhereColumn('a.tenant_id', '!=', 'l.tenant_id'))
            ->select('l.id', 'l.entry_id', 'l.account_id')->get());

        $put('bad_lines', DB::table('erp_journal_lines')->where('tenant_id', $t)
            ->where(fn ($q) => $q->where('debit', '<', 0)->orWhere('credit', '<', 0)->orWhere(fn ($x) => $x->where('debit', '>', 0)->where('credit', '>', 0)))
            ->select('id', 'entry_id', 'debit', 'credit')->get());

        $put('entries_without_lines', DB::table('erp_journal_entries as e')->where('e.tenant_id', $t)->whereIn('e.status', $live)
            ->whereNotExists(fn ($x) => $x->select(DB::raw(1))->from('erp_journal_lines as l')->whereColumn('l.entry_id', 'e.id'))->select('e.id', 'e.number')->get());

        $put('duplicate_idempotency_keys', DB::table('erp_journal_entries')->where('tenant_id', $t)->whereNotNull('idempotency_key')->groupBy('idempotency_key')
            ->havingRaw('COUNT(*) > 1')->selectRaw('idempotency_key, COUNT(*) as n')->get());

        $put('duplicate_numbers', DB::table('erp_journal_entries')->where('tenant_id', $t)->groupBy('number')->havingRaw('COUNT(*) > 1')->selectRaw('number, COUNT(*) as n')->get());

        $put('posted_without_period', DB::table('erp_journal_entries')->where('tenant_id', $t)->whereIn('status', $live)->whereNull('period_id')->select('id', 'number')->get());

        $failures = collect($res)->where('ok', false)->count();

        return ['tenant' => $tenant->slug, 'ok' => $failures === 0, 'failures' => $failures, 'checks' => $res];
    }
}
