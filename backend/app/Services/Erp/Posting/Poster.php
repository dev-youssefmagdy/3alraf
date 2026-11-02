<?php

namespace App\Services\Erp\Posting;

use App\Enums\PaymentMethodType;
use App\Models\Erp\Account;
use App\Models\Erp\JournalEntry;
use App\Models\PaymentMethod;
use App\Services\Erp\Accounting\AccountResolver;
use App\Services\Erp\Accounting\CoaTemplate;
use App\Services\Erp\Accounting\JournalService;
use App\Support\Money;

/**
 * Shared plumbing of every poster: lazy chart seeding, deterministic-key posting through JournalService (the only ledger writer),
 * line helpers that skip zero amounts. Posters CONSUME business numbers (docs/CALCULATIONS.md); they never recompute them.
 */
abstract class Poster
{
    public function __construct(protected JournalService $journals, protected CoaTemplate $coa) {}

    /** Make sure the current tenant owns a chart of accounts (lazy seed). */
    protected function ensureChart(): void
    {
        $tenant = current_tenant();
        if ($tenant && ! Account::query()->where('role_key', 'receivable')->exists()) {
            $this->coa->seed($tenant);
            app(AccountResolver::class)->flush();
        }
    }

    /**
     * Post an auto entry; a repeated $key returns the existing entry (replays / webhook retries never double post).
     *
     * @param  array<int,array<string,mixed>>  $lines
     */
    protected function post(string $key, string $date, string $description, string $sourceType, int $sourceId, array $lines, ?int $branchId = null): ?JournalEntry
    {
        $this->ensureChart();
        if ($existing = JournalEntry::where('idempotency_key', $key)->first()) {
            return $existing;
        }
        $lines = array_values(array_filter($lines, fn ($l) => bccomp((string) ($l['debit'] ?? 0), '0', 4) > 0 || bccomp((string) ($l['credit'] ?? 0), '0', 4) > 0));
        if (! $lines) {
            return null; // nothing to record (e.g. a zero-value document)
        }

        return $this->journals->post([
            'entry_date' => $date, 'type' => 'auto', 'description' => $description, 'source_type' => $sourceType, 'source_id' => $sourceId,
            'idempotency_key' => $key, 'branch_id' => $branchId, 'created_by' => auth('tenant')->id(),
        ], $lines);
    }

    protected function dr(string|int $account, mixed $amount, ?int $branchId = null, ?string $memo = null, ?string $partyType = null, ?int $partyId = null): array
    {
        return ['account' => $account, 'debit' => Money::round((string) $amount), 'credit' => '0', 'branch_id' => $branchId, 'memo' => $memo, 'party_type' => $partyType, 'party_id' => $partyId];
    }

    protected function cr(string|int $account, mixed $amount, ?int $branchId = null, ?string $memo = null, ?string $partyType = null, ?int $partyId = null): array
    {
        return ['account' => $account, 'debit' => '0', 'credit' => Money::round((string) $amount), 'branch_id' => $branchId, 'memo' => $memo, 'party_type' => $partyType, 'party_id' => $partyId];
    }

    protected function pos(mixed $v): bool
    {
        return Money::cmp((string) $v, '0') > 0;
    }

    /** Asset account (role key) that holds money received/paid through a payment method type. */
    public static function settlementRole(?PaymentMethod $m, bool $codToCarrier = true): string
    {
        return match ($m?->type) {
            PaymentMethodType::ONLINE => 'gateway',
            PaymentMethodType::WALLET => 'wallet',
            PaymentMethodType::COD => $codToCarrier ? 'carrier_receivable' : 'cash',
            default => 'cash',
        };
    }

    /** Explicit business date for historical posting (Phase 9 backfill); null = the event happens today. */
    private static ?string $dateOverride = null;

    /** Run $fn with every poster dating its entries on $date (the document's own business date) instead of today. */
    public static function withDate(string $date, \Closure $fn): mixed
    {
        $prev = self::$dateOverride;
        self::$dateOverride = $date;
        try {
            return $fn();
        } finally {
            self::$dateOverride = $prev;
        }
    }

    protected function today(): string
    {
        return self::$dateOverride ?? now()->toDateString();
    }

    /** Reverse every live (posted, auto) entry of a source whose key starts with one of the prefixes. */
    protected function reverseLive(string $sourceType, int $sourceId, array $keyPrefixes, ?string $reason = null): int
    {
        $n = 0;
        $q = JournalEntry::where('source_type', $sourceType)->where('source_id', $sourceId)->where('status', 'posted')->where('type', 'auto')
            ->where(function ($w) use ($keyPrefixes) {
                foreach ($keyPrefixes as $p) {
                    $w->orWhere('idempotency_key', 'like', str_replace(['%', '_'], ['\%', '\_'], $p).'%');
                }
            });
        foreach ($q->orderByDesc('id')->get() as $e) {
            $this->journals->reverse($e, auth('tenant')->id(), $this->today(), $reason);
            $n++;
        }

        return $n;
    }

    /** Net signed amount (debit − credit) of an account role over all posted lines of a source (reversals included, so they net out). */
    protected function netDebit(string $sourceType, int $sourceId, string $role, ?string $keyPrefix = null): string
    {
        $acc = Account::where('role_key', $role)->value('id');
        $q = \App\Models\Erp\JournalLine::query()->where('account_id', $acc)
            ->whereHas('entry', fn ($e) => $e->where('source_type', $sourceType)->where('source_id', $sourceId)->where('status', '!=', 'draft')
                ->when($keyPrefix, fn ($w) => $w->where('idempotency_key', 'like', $keyPrefix.'%')));

        return bcsub((string) ($q->sum('debit') ?: '0'), (string) ($q->sum('credit') ?: '0'), 4);
    }
}
