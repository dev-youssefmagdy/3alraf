<?php

namespace App\Services\Erp\Accounting;

use App\Exceptions\AccountingException;
use App\Models\Erp\Account;
use App\Models\Erp\AuditLog;
use App\Models\Erp\JournalEntry;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The ONLY writer of journals and account balances. Every posting is atomic, balanced (bcmath, scale 4),
 * period-guarded and idempotent per (tenant, idempotency_key).
 *
 * $lines item: ['account' => id|role_key, 'debit' => '0', 'credit' => '0', 'branch_id'?, 'party_type'?, 'party_id'?, 'memo'?,
 *               'original_amount'?, 'original_currency'?]  (amounts in the tenant base currency)
 * $data: entry_date, description, type (manual|auto|opening|adjusting|closing), branch_id?, currency?, exchange_rate?,
 *        source_type?, source_id?, idempotency_key?, created_by?, attachment?
 */
class JournalService
{
    public const SCALE = 4;

    public function __construct(private AccountResolver $accounts, private PeriodService $periods) {}

    public function draft(array $data, array $lines): JournalEntry
    {
        return DB::transaction(fn () => $this->persist($data, $lines, 'draft'));
    }

    /** Create and post in one atomic step. A repeated idempotency key returns the already-posted entry untouched. */
    public function post(array $data, array $lines): JournalEntry
    {
        if (! empty($data['idempotency_key']) && ($existing = JournalEntry::where('idempotency_key', $data['idempotency_key'])->first())) {
            if (! empty($data['strict_replay'])) {
                $this->assertSameReplay($existing, $lines);
            }

            return $existing;
        }

        try {
            return DB::transaction(function () use ($data, $lines) {
                $entry = $this->persist($data, $lines, 'draft');

                return $this->postDraft($entry, $data['created_by'] ?? null);
            });
        } catch (UniqueConstraintViolationException $e) {
            // a concurrent request won the race for the same idempotency key: return its entry
            if (! empty($data['idempotency_key']) && ($existing = JournalEntry::where('idempotency_key', $data['idempotency_key'])->first())) {
                return $existing;
            }

            throw $e;
        }
    }

    /**
     * QA-012: a user form re-submitted with the same one-time token must carry the same money; a different total means the user changed the
     * form after it was saved (browser Back) - refuse instead of silently returning the earlier entry.
     *
     * @param  array<int,array{debit?:mixed,credit?:mixed}>|string  $linesOrTotal
     */
    public function assertSameReplay(JournalEntry $existing, array|string $linesOrTotal): void
    {
        $total = is_string($linesOrTotal) ? $linesOrTotal
            : array_reduce($linesOrTotal, fn ($c, $l) => bcadd($c, self::num($l['debit'] ?? 0), self::SCALE), '0');
        if (bccomp(self::num($existing->total), self::num($total), self::SCALE) !== 0) {
            throw new AccountingException(__('erp_acc.errors.duplicate_ref', ['number' => $existing->number]));
        }
    }

    /** bcmath-safe decimal string (garbage / exponent forms never reach bcmath). */
    public static function num(mixed $v): string
    {
        $v = is_float($v) ? number_format($v, 8, '.', '') : trim((string) ($v ?? '0'));
        if (preg_match('/^[+-]?(\d+(\.\d*)?|\.\d+)$/', $v)) {
            return $v;
        }

        return is_numeric($v) ? number_format((float) $v, 8, '.', '') : '0';
    }

    /** Run $fn atomically while holding the tenant row lock: serialises money-moving user actions (payments, receipts, advances) per tenant. */
    public function exclusive(callable $fn): mixed
    {
        return DB::transaction(function () use ($fn) {
            Tenant::whereKey(current_tenant()?->id ?? throw new AccountingException('No tenant context.'))->lockForUpdate()->first();

            return $fn();
        });
    }

    public function postDraft(JournalEntry $entry, ?int $userId = null): JournalEntry
    {
        return DB::transaction(function () use ($entry, $userId) {
            $entry = JournalEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if ($entry->status === 'posted') {
                return $entry; // idempotent
            }
            if ($entry->status !== 'draft') {
                throw new AccountingException(__('erp.errors.not_draft'));
            }
            $lines = $entry->lines()->get();
            $this->assertBalanced($lines->map(fn ($l) => ['debit' => $l->debit, 'credit' => $l->credit])->all());
            $period = $this->periods->openPeriodFor($entry->entry_date, true); // row-locked: a concurrent period close waits for this posting

            $accounts = Account::whereIn('id', $lines->pluck('account_id')->unique())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($lines as $l) {
                $acc = $accounts[$l->account_id];
                if (! $acc->is_active || $acc->archived_at || $acc->is_group) {
                    throw new AccountingException(__('erp.errors.account_not_postable', ['code' => $acc->code]));
                }
                $delta = $acc->normal_balance === 'debit' ? bcsub((string) $l->debit, (string) $l->credit, self::SCALE) : bcsub((string) $l->credit, (string) $l->debit, self::SCALE);
                $acc->current_balance = bcadd((string) $acc->current_balance, $delta, self::SCALE);
            }
            $accounts->each->save();

            $entry->forceFill(['status' => 'posted', 'period_id' => $period->id, 'posted_by' => $userId, 'posted_at' => now()]);
            // lines are inserted while draft, so the immutability guard only bites after this save
            $entry->save();
            AuditLog::create(['action' => 'journal.posted', 'subject_type' => 'journal', 'subject_id' => $entry->id, 'user_id' => $userId, 'meta' => ['number' => $entry->number, 'total' => $entry->total, 'source' => $entry->source_type]]);

            return $entry;
        });
    }

    /** Reverse a posted entry with a mirror entry; the original is flagged `reversed`, never edited otherwise. */
    public function reverse(JournalEntry $original, ?int $userId = null, ?string $date = null, ?string $reason = null): JournalEntry
    {
        return DB::transaction(function () use ($original, $userId, $date, $reason) {
            $original = JournalEntry::whereKey($original->id)->lockForUpdate()->firstOrFail();
            if ($original->status === 'reversed') {
                return JournalEntry::findOrFail($original->reversed_by_id);
            }
            if ($original->status !== 'posted' || $original->type === 'reversal') {
                throw new AccountingException(__('erp.errors.cannot_reverse'));
            }
            $lines = $original->lines()->get()->map(fn ($l) => [
                'account' => $l->account_id, 'debit' => (string) $l->credit, 'credit' => (string) $l->debit, 'branch_id' => $l->branch_id,
                'party_type' => $l->party_type, 'party_id' => $l->party_id, 'memo' => $l->memo,
                'original_amount' => $l->original_amount, 'original_currency' => $l->original_currency,
            ])->all();
            $rev = $this->post([
                'entry_date' => $date ?? now()->toDateString(), 'type' => 'reversal', 'branch_id' => $original->branch_id, 'currency' => $original->currency,
                'exchange_rate' => $original->exchange_rate, 'source_type' => $original->source_type, 'source_id' => $original->source_id,
                'description' => trim(__('erp.reversal_of', ['number' => $original->number]).' '.$reason), 'idempotency_key' => 'reversal:'.$original->id,
                'reverses_id' => $original->id, 'created_by' => $userId,
            ], $lines);
            $original->forceFill(['status' => 'reversed', 'reversed_by_id' => $rev->id])->save();
            AuditLog::create(['action' => 'journal.reversed', 'subject_type' => 'journal', 'subject_id' => $original->id, 'user_id' => $userId, 'meta' => ['number' => $original->number, 'reason' => $reason, 'reversal' => $rev->number]]);

            return $rev;
        });
    }

    public function deleteDraft(JournalEntry $entry): void
    {
        if ($entry->status !== 'draft') {
            throw new AccountingException(__('erp.errors.not_draft'));
        }
        DB::transaction(function () use ($entry) {
            $entry->lines()->delete();
            $entry->delete();
        });
    }

    /** @param array<int,array{debit:mixed,credit:mixed}> $lines */
    public function assertBalanced(array $lines): void
    {
        $dr = '0';
        $cr = '0';
        foreach ($lines as $l) {
            $dr = bcadd($dr, (string) ($l['debit'] ?? 0), self::SCALE);
            $cr = bcadd($cr, (string) ($l['credit'] ?? 0), self::SCALE);
        }
        if (count($lines) < 2 || bccomp($dr, '0', self::SCALE) <= 0 || bccomp($dr, $cr, self::SCALE) !== 0) {
            throw new AccountingException(__('erp.errors.unbalanced', ['debit' => $dr, 'credit' => $cr]));
        }
    }

    private function persist(array $data, array $lines, string $status): JournalEntry
    {
        $tenant = current_tenant() ?? throw new AccountingException('No tenant context.');
        $clean = [];
        foreach ($lines as $l) {
            $dr = (string) ($l['debit'] ?? 0);
            $cr = (string) ($l['credit'] ?? 0);
            if (bccomp($dr, '0', self::SCALE) < 0 || bccomp($cr, '0', self::SCALE) < 0 || (bccomp($dr, '0', self::SCALE) > 0 && bccomp($cr, '0', self::SCALE) > 0)) {
                throw new AccountingException(__('erp.errors.line_invalid'));
            }
            if (bccomp($dr, '0', self::SCALE) === 0 && bccomp($cr, '0', self::SCALE) === 0) {
                continue; // zero lines carry no information
            }
            $clean[] = ['account_id' => $this->accounts->resolve($l['account']), 'debit' => bcadd($dr, '0', self::SCALE), 'credit' => bcadd($cr, '0', self::SCALE),
                'branch_id' => $l['branch_id'] ?? $data['branch_id'] ?? null, 'party_type' => $l['party_type'] ?? null, 'party_id' => $l['party_id'] ?? null, 'memo' => $l['memo'] ?? null,
                'original_amount' => $l['original_amount'] ?? null, 'original_currency' => $l['original_currency'] ?? null];
        }
        $this->assertBalanced($clean);
        $ids = array_values(array_unique(array_column($clean, 'account_id')));
        if (Account::whereIn('id', $ids)->count() !== count($ids)) {
            throw new AccountingException(__('erp.errors.line_invalid')); // unknown / foreign-tenant account id
        }

        Tenant::whereKey($tenant->id)->lockForUpdate()->first(); // serialises numbering per tenant
        $total = array_reduce($clean, fn ($c, $l) => bcadd($c, $l['debit'], self::SCALE), '0');
        $date = Carbon::parse($data['entry_date'] ?? now())->toDateString();
        $entry = JournalEntry::create([
            'number' => $this->nextNumber(), 'status' => $status, 'type' => $data['type'] ?? 'manual', 'entry_date' => $date, 'branch_id' => $data['branch_id'] ?? null,
            'currency' => $data['currency'] ?? $tenant->currency_code, 'exchange_rate' => $data['exchange_rate'] ?? 1, 'source_type' => $data['source_type'] ?? null,
            'source_id' => $data['source_id'] ?? null, 'idempotency_key' => $data['idempotency_key'] ?? null, 'reverses_id' => $data['reverses_id'] ?? null,
            'description' => $data['description'] ?? null, 'attachment' => $data['attachment'] ?? null, 'total' => $total, 'created_by' => $data['created_by'] ?? null,
        ]);
        $entry->lines()->createMany($clean);

        return $entry;
    }

    private function nextNumber(): string
    {
        $last = JournalEntry::orderByDesc('id')->value('number');
        $n = $last ? ((int) substr($last, 3)) + 1 : 1;

        return 'JE-'.str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }
}
