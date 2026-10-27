<?php

namespace App\Services\Growth;

use App\Jobs\DispatchGrowthEventJob;
use App\Models\GrowthEvent;
use App\Models\IntegrationClient;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Outbox writer. Events are emitted only when the tenant has the growth flag on and at least one active client with a webhook. */
class GrowthEventService
{
    public const TYPES = ['order.placed', 'order.cancelled', 'order.returned', 'stock.low', 'review.created', 'product.created', 'bundle.created', 'affiliate.order'];

    public function __construct(private GrowthAccess $access) {}

    public function emit(string $type, array $data, ?Tenant $tenant = null): ?GrowthEvent
    {
        $tenant ??= current_tenant();
        if (! $tenant || ! $this->access->enabled($tenant) || ! in_array($type, self::TYPES, true)) {
            return null;
        }
        $hasTarget = IntegrationClient::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNull('revoked_at')->whereNotNull('webhook_url')->exists();
        if (! $hasTarget) {
            return null;
        }
        $id = (string) Str::uuid();
        $event = GrowthEvent::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'event_id' => $id, 'type' => $type, 'status' => 'pending', 'next_attempt_at' => now(),
            'payload' => ['event_id' => $id, 'type' => $type, 'tenant' => $tenant->slug, 'occurred_at' => now()->toIso8601String(), 'data' => PiiScrubber::scrub($data)],
        ]);
        DB::afterCommit(fn () => DispatchGrowthEventJob::dispatch($event->id));

        return $event;
    }

    /** Re-queue an event (dead or delivered) from the admin event log. */
    public function replay(GrowthEvent $e): GrowthEvent
    {
        $e->update(['status' => 'pending', 'attempts' => 0, 'next_attempt_at' => now(), 'delivered_to' => null, 'last_error' => null, 'delivered_at' => null]);
        DispatchGrowthEventJob::dispatch($e->id);

        return $e;
    }

    public function prune(): int
    {
        return GrowthEvent::withoutGlobalScopes()->whereIn('status', ['delivered', 'dead'])->where('created_at', '<', now()->subDays((int) config('growth.events.retention_days')))->delete();
    }
}
