<?php

namespace App\Services\Growth;

use App\Jobs\DispatchGrowthEventJob;
use App\Models\GrowthEvent;
use App\Repositories\Contracts\GrowthEventRepositoryInterface;
use App\Repositories\Contracts\IntegrationClientRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Outbox writer. Events are emitted only when the growth flag is on and at least one active client with a webhook. */
class GrowthEventService
{
    public const TYPES = ['order.placed', 'order.cancelled', 'order.returned', 'stock.low', 'review.created', 'product.created', 'bundle.created', 'affiliate.order'];

    public static function lowStockKey(array $d): string
    {
        return 'stock.low:'.($d['product_id'] ?? 0).':'.($d['variant_id'] ?? 0).':'.($d['branch_id'] ?? 0);
    }

    public function __construct(private GrowthAccess $access, private GrowthFeatures $features, private GrowthEventRepositoryInterface $repo, private IntegrationClientRepositoryInterface $clients) {}

    public function emit(string $type, array $data): ?GrowthEvent
    {
        if (! $this->access->enabled() || ! $this->features->active('events_outbox') || ! in_array($type, self::TYPES, true)) {
            return null;
        }
        $hasTarget = $this->clients->webhookTargets()->isNotEmpty();
        if (! $hasTarget) {
            return null;
        }
        $id = (string) Str::uuid();
        $event = $this->repo->create([
            'event_id' => $id, 'type' => $type, 'status' => 'pending', 'next_attempt_at' => now(),
            'payload' => ['event_id' => $id, 'type' => $type, 'store' => self::store(), 'occurred_at' => now()->toIso8601String(), 'data' => PiiScrubber::scrub($data)],
        ]);
        DB::afterCommit(fn () => DispatchGrowthEventJob::dispatch($event->id));

        return $event;
    }

    /** Emit at most once per $key per shop-local day (model-event + scan safety net never double-fire). */
    public function emitOnce(string $key, string $type, array $data): ?GrowthEvent
    {
        if (! \Illuminate\Support\Facades\Cache::add('growth:once:'.$key.':'.now(setting('timezone') ?: config('app.timezone'))->toDateString(), 1, now()->addHours(26))) {
            return null;
        }

        return $this->emit($type, $data);
    }

    /** Shop identity carried by every event: name, country, currency, default locale. */
    public static function store(): array
    {
        return ['name' => setting('name'), 'country' => setting('country'), 'currency' => setting('currency_code'), 'locale' => setting('default_locale')];
    }

    /** Re-queue an event (dead or delivered) from the admin event log. */
    public function replay(GrowthEvent $e): GrowthEvent
    {
        $e->update(['status' => 'pending', 'attempts' => 0, 'next_attempt_at' => now(), 'delivered_to' => null, 'last_error' => null, 'delivered_at' => null]);
        DispatchGrowthEventJob::dispatch($e->id);

        return $e;
    }

    public function find(int $id): GrowthEvent
    {
        return $this->repo->findOrFail($id);
    }

    public function prune(): int
    {
        return $this->repo->prune((int) config('growth.events.retention_days'));
    }
}
