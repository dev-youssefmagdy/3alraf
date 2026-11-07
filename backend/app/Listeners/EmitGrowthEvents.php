<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Models\AffiliateCommission;
use App\Models\Bundle;
use App\Models\Order;
use App\Models\Review;
use App\Models\StockLevel;
use App\Models\Product;
use App\Services\Growth\GrowthConfig;
use App\Services\Growth\GrowthEventService;
use Illuminate\Events\Dispatcher;

/**
 * Bridges existing business flows to the growth outbox. Meaningful events only, ids/amounts only (no PII).
 * Never breaks the originating flow: failures are reported, not thrown.
 */
class EmitGrowthEvents
{
    public function __construct(private GrowthEventService $events, private GrowthConfig $config) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(OrderPlaced::class, fn (OrderPlaced $e) => $this->safe(fn () => $this->emit('order.placed', $this->order($e->order))));
        $events->listen(OrderStatusChanged::class, function (OrderStatusChanged $e) {
            $type = match ($e->to) {
                OrderStatus::CANCELLED => 'order.cancelled', OrderStatus::RETURNED => 'order.returned', default => null,
            };
            $type && $this->safe(fn () => $this->emit($type, $this->order($e->order) + ['from' => $e->from?->value]));
        });
    }

    /** Model-level hooks (registered from AppServiceProvider::boot). */
    public static function hookModels(): void
    {
        $self = fn () => app(self::class);
        Review::created(fn (Review $r) => $self()->safe(fn () => $self()->emit('review.created', ['review_id' => $r->id, 'product_id' => $r->product_id, 'rating' => $r->rating])));
        Product::created(fn (Product $p) => $self()->safe(fn () => $self()->emit('product.created', ['product_id' => $p->id])));
        Bundle::created(fn (Bundle $b) => $self()->safe(fn () => $self()->emit('bundle.created', ['bundle_id' => $b->id, 'active' => (bool) ($b->is_active ?? false)])));
        AffiliateCommission::created(fn (AffiliateCommission $c) => $self()->safe(fn () => $self()->emit('affiliate.order', ['order_id' => $c->order_id, 'affiliate_id' => $c->affiliate_id, 'amount' => (string) $c->amount])));
        StockLevel::saved(function (StockLevel $s) use ($self) {
            $self()->safe(function () use ($s, $self) {
                if (! $s->wasChanged(['on_hand', 'reserved'])) {
                    return;
                }
                $threshold = (int) $self()->config->get('low_stock_threshold', 5);
                $now = $s->on_hand - $s->reserved;
                $before = ($s->getOriginal('on_hand') ?? $s->on_hand) - ($s->getOriginal('reserved') ?? $s->reserved);
                if ($now <= $threshold && $before > $threshold) { // crossing only: no event per movement while already low
                    $self()->emitLowStock(['product_id' => $s->product_id, 'variant_id' => $s->product_variant_id ?: null, 'branch_id' => $s->branch_id, 'available' => $now, 'threshold' => $threshold]);
                }
            });
        });
    }

    private function order(Order $o): array
    {
        return ['order_id' => $o->id, 'number' => $o->number, 'status' => $o->status?->value ?? (string) $o->status, 'total' => (string) $o->total, 'currency' => $o->currency, 'affiliate_id' => $o->affiliate_id];
    }

    public function emitLowStock(array $data): void
    {
        $this->events->emitOnce(GrowthEventService::lowStockKey($data), 'stock.low', $data);
    }

    public function emit(string $type, array $data): void
    {
        $this->events->emit($type, $data);
    }

    public function safe(\Closure $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
