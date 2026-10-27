<?php

namespace App\Services\Growth;

use App\Jobs\DispatchGrowthEventJob;
use App\Models\GrowthEvent;
use App\Repositories\Contracts\GrowthEventRepositoryInterface;
use App\Repositories\Contracts\IntegrationClientRepositoryInterface;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Outbox writer. Events are emitted only when the tenant has the growth flag on and at least one active client with a webhook. */
class GrowthEventService
{
    public const TYPES = ['order.placed', 'order.cancelled', 'order.returned', 'stock.low', 'review.created', 'product.created', 'bundle.created', 'affiliate.order'];

    public static function lowStockKey(array $d): string
    {
        return 'stock.low:'.($d['product_id'] ?? 0).':'.($d['variant_id'] ?? 0).':'.($d['branch_id'] ?? 0);
    }

    public function __construct(private GrowthAccess $access, private GrowthFeatures $features, private GrowthEventRepositoryInterface $repo, private IntegrationClientRepositoryInterface $clients) {}

    public function emit(string $type, array $data, ?Tenant $tenant = null): ?GrowthEvent
    {
        $tenant ??= current_tenant();
        if (! $tenant || ! $this->access->enabled($tenant) || ! $this->features->active('events_outbox', $tenant) || ! in_array($type, self::TYPES, true)) {
            return null;
        }
        $hasTarget = $this->clients->webhookTargets($tenant->id)->isNotEmpty();
        if (! $hasTarget) {
            return null;
        }
        $id = (string) Str::uuid();
        $event = $this->repo->createForTenant([
            'tenant_id' => $tenant->id, 'event_id' => $id, 'type' => $type, 'status' => 'pending', 'next_attempt_at' => now(),
            'payload' => ['event_id' => $id, 'type' => $type, 'tenant' => $tenant->slug, 'occurred_at' => now()->toIso8601String(), 'data' => PiiScrubber::scrub($data)],
        ]);
        DB::afterCommit(fn () => DispatchGrowthEventJob::dispatch($event->id));

        return $event;
    }

    /** Emit at most once per $key per tenant-local day (model-event + scan safety net never double-fire). */
    public function emitOnce(string $key, string $type, array $data, ?Tenant $tenant = null): ?GrowthEvent
    {
        $tenant ??= current_tenant();
        if (! $tenant || ! \Illuminate\Support\Facades\Cache::add('growth:once:'.$tenant->id.':'.$key.':'.now($tenant->timezone ?: config('app.timezone'))->toDateString(), 1, now()->addHours(26))) {
            return null;
        }

        return $this->emit($type, $data, $tenant);
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
