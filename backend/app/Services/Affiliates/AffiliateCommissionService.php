<?php

namespace App\Services\Affiliates;

use App\Enums\CommissionStatus;
use App\Enums\OrderStatus;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliateTier;
use App\Models\Order;
use App\Repositories\Contracts\AffiliateCommissionRepositoryInterface;
use App\Support\Money;

/** Commission tiers + lifecycle (docs/CALCULATIONS.md §Affiliate commission): pending -> approved (delivered + return window) -> paid (payout); voided on cancel/return. */
class AffiliateCommissionService
{
    public const DEFAULT_RETURN_WINDOW_DAYS = 14;

    public function __construct(private AffiliateCommissionRepositoryInterface $commissions) {}

    /** Lifetime sales = Σ(subtotal − discount) of the affiliate's delivered orders − Σ return_value of their approved/refunded returns (floored at 0). */
    public function lifetimeSales(Affiliate $a): string
    {
        $orders = Order::query()->where('affiliate_id', $a->id)->where('status', OrderStatus::DELIVERED->value);
        $sales = (string) (clone $orders)->selectRaw('COALESCE(SUM(subtotal - discount), 0) as s')->value('s');
        $returned = (string) \App\Models\OrderReturn::query()->whereIn('status', ['approved', 'refunded'])
            ->whereIn('order_id', (clone $orders)->select('id'))->sum('return_value');

        return Money::max('0', Money::sub(Money::round($sales), Money::round($returned)));
    }

    /** Highest tier with min_sales <= sales (per tenant). */
    public function tierFor(string $sales): ?AffiliateTier
    {
        return AffiliateTier::query()->whereRaw('min_sales <= ?', [$sales])->orderByDesc('min_sales')->first();
    }

    public function percentFor(Affiliate $a): string
    {
        return (string) ($this->tierFor($this->lifetimeSales($a))?->percent ?? '0');
    }

    /** Pending commission row for a freshly placed order (amount = OrderCalculator's affiliate_commission). */
    public function record(Order $order, Affiliate $a, string $percent): ?AffiliateCommission
    {
        if (Money::cmp((string) $order->affiliate_commission, '0') <= 0) {
            return null;
        }

        return $this->commissions->create([
            'affiliate_id' => $a->id, 'order_id' => $order->id, 'base' => Money::sub((string) $order->subtotal, (string) $order->discount),
            'percent' => $percent, 'amount' => (string) $order->affiliate_commission, 'status' => CommissionStatus::PENDING,
        ]);
    }

    /**
     * Partial returns: commission = round((base − cumulative approved/refunded return_value) × percent/100), floored at 0,
     * always recomputed from the cumulative total (no drift over several partial returns). Paid rows are never altered.
     */
    public function recalculateAfterReturns(Order $order): void
    {
        $c = $this->commissions->query()->where('order_id', $order->id)->whereIn('status', [CommissionStatus::PENDING->value, CommissionStatus::APPROVED->value])->first();
        if (! $c) {
            return;
        }
        $returned = Money::round((string) \App\Models\OrderReturn::query()->where('order_id', $order->id)->whereIn('status', ['approved', 'refunded'])->sum('return_value'));
        $left = Money::max('0', Money::sub((string) $c->base, $returned));
        $order->update(['affiliate_commission' => Money::percent($left, (string) $c->percent)]);
    }

    /** Cancelled / fully returned -> void; partial return -> amount follows orders.affiliate_commission (reduced pro-rata). Paid rows are never touched. */
    public function syncOrder(Order $order): void
    {
        $c = $this->commissions->query()->where('order_id', $order->id)->whereIn('status', [CommissionStatus::PENDING->value, CommissionStatus::APPROVED->value])->first();
        if (! $c) {
            return;
        }
        if (in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::RETURNED], true) || Money::cmp((string) $order->affiliate_commission, '0') <= 0) {
            $c->update(['status' => CommissionStatus::VOIDED, 'amount' => 0]);
            if ($c->payout_id) {
                $c->update(['payout_id' => null]);
            }

            return;
        }
        $c->update(['amount' => (string) $order->affiliate_commission]);
    }

    public function returnWindowDays(): int
    {
        return (int) (current_tenant()?->settings['affiliate_return_window_days'] ?? self::DEFAULT_RETURN_WINDOW_DAYS);
    }

    /** Pending -> approved once the order is delivered and the return window elapsed. Returns number approved. */
    public function approveDue(): int
    {
        $cutoff = now()->subDays($this->returnWindowDays());
        $n = 0;
        $this->commissions->query()->where('status', CommissionStatus::PENDING->value)
            ->whereHas('order', fn ($q) => $q->where('status', OrderStatus::DELIVERED->value)->where('delivered_at', '<=', $cutoff))
            ->each(function (AffiliateCommission $c) use (&$n) {
                $c->update(['status' => CommissionStatus::APPROVED, 'approved_at' => now()]);
                $n++;
            });

        return $n;
    }

    /** @return array{pending:string,approved:string,paid:string,available:string} */
    public function totals(Affiliate $a): array
    {
        $rows = $this->commissions->query()->where('affiliate_id', $a->id)->selectRaw('status, COALESCE(SUM(amount),0) s')->groupBy('status')->pluck('s', 'status');
        $by = fn (CommissionStatus $s) => Money::round((string) ($rows[$s->value] ?? 0));
        $available = (string) $this->commissions->query()->where('affiliate_id', $a->id)->where('status', CommissionStatus::APPROVED->value)->whereNull('payout_id')->sum('amount');

        return ['pending' => $by(CommissionStatus::PENDING), 'approved' => $by(CommissionStatus::APPROVED), 'paid' => $by(CommissionStatus::PAID), 'available' => Money::round($available)];
    }
}
