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

    /** Roles added after the first chart template (audit fixes): topped up lazily for tenants seeded earlier. */
    public const LATE_ROLES = ['goods_in_delivery', 'packaging_supplies', 'branch_transfer'];

    /** Make sure the current tenant owns a chart of accounts (lazy seed + top-up of late roles). */
    protected function ensureChart(): void
    {
        $tenant = current_tenant();
        if (! $tenant) {
            return;
        }
        if (! Account::query()->where('role_key', 'receivable')->exists()) {
            $this->coa->seed($tenant);
            app(AccountResolver::class)->flush();
        } elseif (Account::query()->whereIn('role_key', self::LATE_ROLES)->count() < count(self::LATE_ROLES)) {
            $this->coa->ensureRoles(self::LATE_ROLES, $tenant);
        }
    }

    /** True while "post missing" / an explicit repost runs: a key whose entry was MANUALLY reversed may then be posted again as a new version. */
    private static bool $reposting = false;

    public static function reposting(\Closure $fn): mixed
    {
        $prev = self::$reposting;
        self::$reposting = true;
        try {
            return $fn();
        } finally {
            self::$reposting = $prev;
        }
    }

    private static function likeEscape(string $v): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $v);
    }

    /** Latest entry of a deterministic key (the key itself or a re-posted version "{key}:v{n}"). */
    public static function latest(string $key): ?JournalEntry
    {
        return JournalEntry::where(fn ($q) => $q->where('idempotency_key', $key)->orWhere('idempotency_key', 'like', self::likeEscape($key).':v%'))->orderByDesc('id')->first();
    }

    /** Is there a LIVE (posted, not reversed) entry for this deterministic key (any version)? */
    public static function live(string $key): bool
    {
        return JournalEntry::where('status', 'posted')->where(fn ($q) => $q->where('idempotency_key', $key)->orWhere('idempotency_key', 'like', self::likeEscape($key).':v%'))->exists();
    }

    /**
     * Post an auto entry; a repeated $key returns the existing entry (replays / webhook retries never double post).
     *
     * @param  array<int,array<string,mixed>>  $lines
     */
    protected function post(string $key, string $date, string $description, string $sourceType, int $sourceId, array $lines, ?int $branchId = null): ?JournalEntry
    {
        $this->ensureChart();
        if ($existing = self::latest($key)) {
            if ($existing->status !== 'reversed' || ! self::$reposting) {
                return $existing; // replay: never double post (a reversed key stays reversed outside an explicit repost)
            }
            $n = JournalEntry::where(fn ($q) => $q->where('idempotency_key', $key)->orWhere('idempotency_key', 'like', self::likeEscape($key).':v%'))->count();
            $key .= ':v'.($n + 1); // corrected re-post of a manually reversed auto entry
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

    /** Account credited for the merchant-borne payment fee of a non-online method (online fees are posted at payment time, PaymentPoster). */
    public static function feeCreditRole(?PaymentMethod $m): string
    {
        return match ($m?->type) {
            PaymentMethodType::ONLINE => 'gateway',
            PaymentMethodType::WALLET => 'wallet',
            PaymentMethodType::COD => 'shipping_payable', // charged by the carrier, settled with the carrier (ERP-023)
            default => 'cash',
        };
    }

    /** Accounts a refund may be paid from (refund_from); carrier_receivable = refunded by the carrier before settlement. */
    public const REFUND_ROLES = ['cash', 'bank', 'pos_cash', 'gateway', 'wallet', 'carrier_receivable'];

    /** Default account a manual (non-provider) refund is paid from: the money moves out of where the merchant holds it. */
    public static function defaultRefundRole(\App\Models\Order $o, bool $shippingLeg = false): string
    {
        if ($o->counter_sale) {
            return 'pos_cash';
        }
        $m = $shippingLeg && $o->prepay_method_id ? $o->prepayMethod : $o->paymentMethod;

        return match ($m?->type) {
            PaymentMethodType::ONLINE => 'gateway',
            PaymentMethodType::WALLET => 'wallet',
            default => 'cash', // COD: the merchant hands the cash back (the carrier already settled or will settle the full COD amount)
        };
    }

    /**
     * Credit side of a refund (ERP-001/010): the part sent back through the provider credits the gateway; the rest is paid manually from
     * $from (chosen on the refund form) or the default account. For COD-with-prepaid-shipping the refunded shipping leg comes out of the
     * prepay method's account, the goods part out of cash.
     *
     * @return array<string,string> role => amount
     */
    public static function refundCredits(\App\Models\Order $o, string $amount, string $provider, string $shippingPart = '0', ?string $from = null): array
    {
        $out = [];
        $provider = Money::min(Money::max('0', $provider), $amount);
        if (Money::cmp($provider, '0') > 0) {
            $out['gateway'] = $provider;
        }
        $rest = Money::sub($amount, $provider);
        if (Money::cmp($rest, '0') <= 0) {
            return $out;
        }
        if ($from && in_array($from, self::REFUND_ROLES, true)) {
            $out[$from] = Money::add($out[$from] ?? '0', $rest);

            return $out;
        }
        if ($o->prepay_method_id) {
            $leg = Money::min($rest, Money::max('0', Money::sub($shippingPart, $provider)));
            if (Money::cmp($leg, '0') > 0) {
                $r = self::defaultRefundRole($o, true);
                $out[$r] = Money::add($out[$r] ?? '0', $leg);
                $rest = Money::sub($rest, $leg);
            }
        }
        if (Money::cmp($rest, '0') > 0) {
            $r = self::defaultRefundRole($o);
            $out[$r] = Money::add($out[$r] ?? '0', $rest);
        }

        return $out;
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
