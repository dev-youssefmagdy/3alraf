<?php

namespace App\Services\Erp\Reports;

use App\Models\Branch;
use App\Models\Erp\JournalLine;
use App\Services\Erp\Parties\PartyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Shared plumbing for the ledger-derived reports. Every number comes from posted/reversed journal lines (reversal mirrors offset the
 * originals, so a reversed entry nets to zero); drafts never count. Output is the normalized shape {title, cards, tables[], notes, meta}.
 */
abstract class ReportBase
{
    protected const S = 4;

    public const CASH_ROLES = PartyService::CASH_ROLES;

    protected function tenantId(): int
    {
        return (int) (current_tenant()?->id ?? 0);
    }

    /** Lines joined to entry (e) and account (a), filtered. */
    protected function lines(ErpReportFilter $f, bool $useFrom = true, bool $withAccountFilter = true, bool $useTo = true): Builder
    {
        $tid = $this->tenantId();

        return JournalLine::query()
            ->join('erp_journal_entries as e', 'e.id', '=', 'erp_journal_lines.entry_id')
            ->join('erp_accounts as a', 'a.id', '=', 'erp_journal_lines.account_id')
            ->whereIn('e.status', ['posted', 'reversed'])
            ->when($useFrom && $f->from, fn ($q) => $q->where('e.entry_date', '>=', $f->from))
            ->when($useTo && $f->to, fn ($q) => $q->where('e.entry_date', '<=', $f->to))
            ->when($f->branchId, fn ($q) => $q->where('erp_journal_lines.branch_id', $f->branchId))
            ->when($withAccountFilter && $f->accountId, fn ($q) => $q->where('erp_journal_lines.account_id', $f->accountId))
            ->when($f->type, fn ($q) => in_array($f->type, ErpReportFilter::ENTRY_TYPES, true) ? $q->where('e.type', $f->type) : $q->where('e.source_type', $f->type))
            ->when($f->currency, fn ($q) => $q->where('e.currency', $f->currency))
            ->when($f->customerId, fn ($q) => $q->whereExists(fn ($x) => $this->partyExists($x, 'customer', $f->customerId, $tid)))
            ->when($f->supplierId, fn ($q) => $q->whereExists(fn ($x) => $this->partyExists($x, 'supplier', $f->supplierId, $tid)))
            ->when($f->paymentMethodId, fn ($q) => $q->where('e.source_type', 'order')->whereExists(fn ($x) => $x->select(DB::raw(1))->from('orders as o')
                ->whereColumn('o.id', 'e.source_id')->where('o.tenant_id', $tid)->where('o.payment_method_id', $f->paymentMethodId)));
    }

    private function partyExists($x, string $type, int $id, int $tid): void
    {
        $x->select(DB::raw(1))->from('erp_journal_lines as pl')->whereColumn('pl.entry_id', 'e.id')->where('pl.tenant_id', $tid)->where('pl.party_type', $type)->where('pl.party_id', $id);
    }

    /** Year-end closing entries (and their reversals) are not trading activity. */
    protected function noClosing(Builder $q): Builder
    {
        return $q->where('e.type', '!=', 'closing')->whereNotExists(fn ($x) => $x->select(DB::raw(1))->from('erp_journal_entries as o')->whereColumn('o.id', 'e.reverses_id')->where('o.type', 'closing'));
    }

    /** Debit-positive balance of the given account ids through $to (null = all), optional branch. */
    protected function debitBalance(array $accountIds, ?string $to, ?int $branchId = null): string
    {
        if (! $accountIds) {
            return '0.0000';
        }
        $r = JournalLine::query()->join('erp_journal_entries as e', 'e.id', '=', 'erp_journal_lines.entry_id')->whereIn('e.status', ['posted', 'reversed'])
            ->whereIn('erp_journal_lines.account_id', $accountIds)->when($to, fn ($q) => $q->where('e.entry_date', '<=', $to))
            ->when($branchId, fn ($q) => $q->where('erp_journal_lines.branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(erp_journal_lines.debit),0) as dr, COALESCE(SUM(erp_journal_lines.credit),0) as cr')->first();

        return bcsub((string) $r->dr, (string) $r->cr, self::S);
    }

    /** @return array<int|string,string> */
    protected function branchNames(): array
    {
        return Branch::withTrashed()->get()->mapWithKeys(fn ($b) => [$b->id => (string) $b->name])->all();
    }

    protected function col(string $key, string $type = 'text'): array
    {
        return ['key' => $key, 'label' => __('erp_rep.col.'.$key), 'type' => $type];
    }

    /** @param array<int,array> $columns */
    protected function table(array $columns, array $rows, ?string $title = null): array
    {
        return ['title' => $title, 'columns' => $columns, 'rows' => $rows];
    }

    protected function out(string $key, array $tables, array $cards = [], array $notes = [], array $meta = []): array
    {
        return ['key' => $key, 'title' => __('erp_rep.t.'.$key), 'cards' => $cards, 'tables' => $tables, 'notes' => $notes, 'meta' => $meta];
    }

    protected function card(string $label, string $value, string $kind = 'money'): array
    {
        return ['label' => __('erp_rep.col.'.$label), 'value' => $value, 'type' => $kind];
    }

    protected function sum(array $rows, string $key): string
    {
        return array_reduce($rows, fn ($c, $r) => bcadd($c, (string) ($r[$key] ?? '0'), self::S), '0');
    }

    protected function inList(array $roles): string
    {
        return "'".implode("','", array_map(fn ($r) => preg_replace('/[^a-z_]/', '', $r), $roles))."'";
    }
}
