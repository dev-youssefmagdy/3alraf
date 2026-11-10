<?php

namespace App\Services\Affiliates;

use App\Enums\AffiliateStatus;
use App\Enums\CommissionStatus;
use App\Enums\OrderStatus;
use App\Events\Erp\AffiliateBonusAwarded;
use App\Models\Affiliate;
use App\Models\AffiliateBonus;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Setting;
use App\Repositories\Contracts\AffiliateBonusRepositoryInterface;
use App\Repositories\Contracts\AffiliateRepositoryInterface;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Affiliate sales bonus (docs/CALCULATIONS.md §Affiliate sales bonus): `amount` EGP for every `threshold` EGP of eligible sales,
 * cumulative and idempotent (milestone n is unique per affiliate). Eligible = delivered past the return window, placed on/after the launch date.
 */
class AffiliateBonusService
{
    public const DEFAULT_THRESHOLD = '10000.00';

    public const DEFAULT_AMOUNT = '200.00';

    public function __construct(
        private AffiliateBonusRepositoryInterface $bonuses,
        private AffiliateRepositoryInterface $affiliates,
        private AffiliateCommissionService $commissions,
    ) {}

    // ---------------------------------------------------------------- settings

    public function enabled(): bool
    {
        return filter_var(setting('affiliate_bonus_enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function threshold(): string
    {
        return Money::round((string) setting('affiliate_bonus_threshold', self::DEFAULT_THRESHOLD));
    }

    public function amount(): string
    {
        return Money::round((string) setting('affiliate_bonus_amount', self::DEFAULT_AMOUNT));
    }

    /** Launch date: only orders placed on/after it count (null = not launched, nothing counts). */
    public function startedAt(): ?CarbonImmutable
    {
        $v = setting('affiliate_bonus_started_at');

        return $v ? CarbonImmutable::parse((string) $v) : null;
    }

    /** @return array{affiliate_bonus_enabled:bool,affiliate_bonus_threshold:string,affiliate_bonus_amount:string,affiliate_bonus_started_at:?string} */
    public function settings(): array
    {
        return ['affiliate_bonus_enabled' => $this->enabled(), 'affiliate_bonus_threshold' => $this->threshold(),
            'affiliate_bonus_amount' => $this->amount(), 'affiliate_bonus_started_at' => $this->startedAt()?->format('Y-m-d H:i:s')];
    }

    /**
     * Pause periods (owner rule 2026-11-10): orders PLACED while the bonus was off never count. Open period (to = null) while it is off.
     *
     * @return list<array{from:string,to:?string}>
     */
    public function pausedPeriods(): array
    {
        return array_values(array_filter((array) setting('affiliate_bonus_paused_periods', []), fn ($p) => is_array($p) && ! empty($p['from'])));
    }

    /** Admin edit; the launch date is read-only (set by the migration). Earned bonuses keep their own threshold/amount. on→off opens a pause period, off→on closes it. */
    public function saveSettings(bool $enabled, string $threshold, string $amount): void
    {
        $values = ['affiliate_bonus_enabled' => $enabled, 'affiliate_bonus_threshold' => Money::round($threshold), 'affiliate_bonus_amount' => Money::round($amount)];
        $was = $this->enabled();
        if ($was !== $enabled) {
            $periods = $this->pausedPeriods();
            $now = now()->format('Y-m-d H:i:s'); // store clock, same as orders.placed_at
            if (! $enabled) {
                $periods[] = ['from' => $now, 'to' => null];
            } else {
                foreach ($periods as $i => $p) {
                    if (($p['to'] ?? null) === null) {
                        $periods[$i]['to'] = $now;
                    }
                }
            }
            $values['affiliate_bonus_paused_periods'] = $periods;
        }
        Setting::putMany($values);
    }

    // ---------------------------------------------------------------- calculation

    /** SQL: when the customer placed the order (placed_at, older rows / other channels fall back to created_at). */
    private const PLACED = 'COALESCE(orders.placed_at, orders.created_at)';

    /**
     * Orders that count: delivered, past the return window, placed on/after the launch date and not while the bonus was paused
     * (all affiliates when $affiliateId is null).
     */
    private function eligibleOrders(?int $affiliateId = null)
    {
        $start = $this->startedAt();
        $q = Order::query()->when($affiliateId !== null, fn ($q) => $q->where('orders.affiliate_id', $affiliateId))->where('orders.status', OrderStatus::DELIVERED->value)
            ->where('orders.delivered_at', '<=', now()->subDays($this->commissions->returnWindowDays()))
            ->whereRaw(self::PLACED.' >= ?', [($start ?? now()->addYears(100))->format('Y-m-d H:i:s')]); // not launched => nothing counts
        foreach ($this->pausedPeriods() as $p) {
            $p['to'] ? $q->whereRaw('NOT ('.self::PLACED.' >= ? AND '.self::PLACED.' < ?)', [$p['from'], $p['to']]) : $q->whereRaw(self::PLACED.' < ?', [$p['from']]);
        }

        return $q;
    }

    /** Σ (revenue_goods − returned value excl. VAT) of the eligible orders, each floored at 0 (commission figure, also for 0 % tier orders). */
    public function eligibleSales(Affiliate $a): string
    {
        // returns via a subquery (no whereIn of every order id); the VAT split of a return needs Order::returnNetOfTax, so orders are hydrated lazily
        $returned = OrderReturn::query()->whereIn('status', ['approved', 'refunded'])->whereIn('order_id', $this->eligibleOrders($a->id)->select('orders.id'))
            ->selectRaw('order_id, SUM(return_value) as v')->groupBy('order_id')->pluck('v', 'order_id');
        $sales = '0.00';
        foreach ($this->eligibleOrders($a->id)->lazyById(500, 'orders.id', 'id') as $o) {
            $sales = Money::add($sales, Money::max('0', Money::sub($o->revenueGoods(), $o->returnNetOfTax((string) ($returned[$o->id] ?? '0')))));
        }

        return Money::round($sales);
    }

    /** Σ threshold of ALL the affiliate's bonuses, voided included: a voided bonus forfeits its sales (they are never re-awarded). */
    public function consumed(Affiliate $a): string
    {
        return Money::round((string) $this->bonuses->query()->where('affiliate_id', $a->id)->sum('threshold'));
    }

    /** Award every bonus now due (all approved affiliates, or one). Idempotent. @return int bonuses created */
    public function awardDue(?Affiliate $only = null): int
    {
        // amount ≤ 0 would only burn sales into 0.00 bonuses: award nothing
        if (! $this->enabled() || Money::cmp($this->threshold(), '0') <= 0 || Money::cmp($this->amount(), '0') <= 0 || ! $this->startedAt()) {
            return 0;
        }
        $n = 0;
        $q = $this->affiliates->query()->select('id')->where('status', AffiliateStatus::APPROVED->value)
            ->whereIn('id', $this->eligibleOrders()->whereNotNull('orders.affiliate_id')->select('orders.affiliate_id'));
        if ($only) {
            $q->whereKey($only->id);
        }
        $q->chunkById(200, function ($rows) use (&$n) {
            foreach ($rows as $row) {
                try {
                    $n += $this->awardFor((int) $row->id);
                } catch (\Throwable $e) {
                    report($e); // one bad affiliate must not block the others; the next run retries it
                }
            }
        });

        return $n;
    }

    /** One affiliate, row locked: create bonus #n while eligible − consumed ≥ current threshold. */
    private function awardFor(int $affiliateId): int
    {
        try {
            return DB::transaction(function () use ($affiliateId) {
                $a = $this->affiliates->query()->whereKey($affiliateId)->lockForUpdate()->first();
                if (! $a || ! $a->isApproved()) {
                    return 0;
                }
                $threshold = $this->threshold();
                $amount = $this->amount();
                $sales = $this->eligibleSales($a);
                $consumed = $this->consumed($a);
                $milestone = (int) $this->bonuses->query()->where('affiliate_id', $a->id)->max('milestone');
                $made = 0;
                while (Money::cmp(Money::sub($sales, $consumed), $threshold) >= 0) {
                    $b = $this->bonuses->create(['affiliate_id' => $a->id, 'milestone' => ++$milestone, 'threshold' => $threshold, 'amount' => $amount,
                        'sales_at_award' => $sales, 'status' => CommissionStatus::APPROVED]);
                    DB::afterCommit(fn () => event(new AffiliateBonusAwarded($b))); // ERP: Dr Affiliate expense / Cr Affiliate payable
                    $consumed = Money::add($consumed, $threshold);
                    $made++;
                }

                return $made;
            });
        } catch (UniqueConstraintViolationException) {
            return 0; // a concurrent run awarded the same milestone first
        }
    }

    // ---------------------------------------------------------------- read side

    /**
     * Dashboard block.
     *
     * @return array{enabled:bool,threshold:string,amount:string,started_at:?string,eligible_sales:string,consumed:string,toward_next:string,remaining:string,percent:float,earned_count:int,earned_total:string,paid_total:string,available:string}
     */
    public function progress(Affiliate $a): array
    {
        $threshold = $this->threshold();
        $sales = $this->eligibleSales($a);
        $consumed = $this->consumed($a);
        $toward = Money::max('0', Money::sub($sales, $consumed));
        if (Money::cmp($threshold, '0') > 0) {
            $toward = Money::min($toward, $threshold); // a bonus due but not yet awarded (next daily run) shows as a full bar
        }
        $totals = $this->totals($a);

        return [
            'enabled' => $this->enabled(), 'threshold' => $threshold, 'amount' => $this->amount(), 'started_at' => $this->startedAt()?->toIso8601String(),
            'eligible_sales' => $sales, 'consumed' => $consumed, 'toward_next' => Money::round($toward),
            'remaining' => Money::round(Money::max('0', Money::sub($threshold, $toward))),
            'percent' => Money::cmp($threshold, '0') > 0 ? round((float) Money::div(Money::mul($toward, '100'), $threshold), 1) : 0.0,
            'earned_count' => $totals['count'], 'earned_total' => $totals['total'], 'paid_total' => $totals['paid'], 'available' => $totals['available'],
        ];
    }

    /** @return array{count:int,total:string,approved:string,paid:string,available:string} non-voided bonuses; available = approved and not in a payout */
    public function totals(Affiliate $a): array
    {
        $rows = $this->bonuses->query()->toBase()->where('affiliate_id', $a->id)->selectRaw('status, COUNT(*) c, COALESCE(SUM(amount),0) s')->groupBy('status')->get()->keyBy('status');
        $sum = fn (CommissionStatus $s) => Money::round((string) ($rows[$s->value]->s ?? 0));
        $available = (string) $this->bonuses->query()->where('affiliate_id', $a->id)->where('status', CommissionStatus::APPROVED->value)->whereNull('payout_id')->sum('amount');

        return [
            'count' => (int) ($rows[CommissionStatus::APPROVED->value]->c ?? 0) + (int) ($rows[CommissionStatus::PAID->value]->c ?? 0),
            'total' => Money::add($sum(CommissionStatus::APPROVED), $sum(CommissionStatus::PAID)),
            'approved' => $sum(CommissionStatus::APPROVED), 'paid' => $sum(CommissionStatus::PAID), 'available' => Money::round($available),
        ];
    }

    /** @return Collection<int,AffiliateBonus> latest first */
    public function recent(Affiliate $a, int $limit = 20): Collection
    {
        return $this->bonuses->query()->where('affiliate_id', $a->id)->latest('milestone')->limit($limit)->get();
    }
}
