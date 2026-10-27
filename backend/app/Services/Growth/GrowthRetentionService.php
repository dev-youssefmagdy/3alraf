<?php

namespace App\Services\Growth;

use App\Models\ExperimentAdDay;
use App\Models\GrowthActionLog;
use App\Models\Interaction;
use App\Models\SmallBatchSignup;
use App\Models\SmallBatchTest;
use App\Models\Tenant;

/** Retention + erasure for personal/operational growth data (N4/N5 gaps). Runs across tenants (global scopes bypassed, explicit tenant filter). */
class GrowthRetentionService
{
    /** @return array<string,int> deleted rows per table */
    public function prune(?Tenant $tenant = null, bool $dry = false): array
    {
        $r = (array) config('growth.retention');
        $q = fn ($model, string $col, int $days) => $model::withoutGlobalScopes()->when($tenant, fn ($b) => $b->where('tenant_id', $tenant->id))->where($col, '<', now()->subDays($days));
        $out = [];
        foreach ([
            'interactions' => [Interaction::class, 'created_at', $r['interactions_days'] ?? 180],
            'small_batch_signups' => [SmallBatchSignup::class, 'created_at', $r['small_batch_days'] ?? 180],
            'experiment_ad_days' => [ExperimentAdDay::class, 'day', $r['ad_days_days'] ?? 730],
        ] as $k => [$m, $c, $d]) {
            $b = $q($m, $c, (int) $d);
            $out[$k] = $dry ? $b->count() : $b->delete();
        }
        $b = GrowthActionLog::withoutGlobalScopes()->when($tenant, fn ($x) => $x->where('tenant_id', $tenant->id))->where('created_at', '<', now()->subDays((int) ($r['action_log_days'] ?? 730)));
        $out['growth_action_log'] = $dry ? $b->count() : $b->delete();
        $out['growth_events'] = $dry ? 0 : app(GrowthEventService::class)->prune();
        $out['idempotency'] = $dry ? 0 : app(IdempotencyService::class)->prune();

        return $out;
    }

    /** Erase one data subject: interactions of a thread/external id and small-batch signups matching a contact (hash re-derived per test). */
    public function erase(Tenant $tenant, ?string $thread = null, ?string $contact = null): array
    {
        $out = ['interactions' => 0, 'small_batch_signups' => 0];
        if ($thread) {
            $out['interactions'] = Interaction::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where(fn ($b) => $b->where('thread_id', $thread)->orWhere('external_id', $thread))->delete();
        }
        if ($contact) {
            $norm = mb_strtolower(trim($contact));
            $hashes = SmallBatchTest::withoutGlobalScopes()->where('tenant_id', $tenant->id)->pluck('id')->map(fn ($id) => sha1($tenant->id.'|'.$id.'|'.$norm))->all();
            $out['small_batch_signups'] = $hashes ? SmallBatchSignup::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereIn('contact_hash', $hashes)->delete() : 0;
        }
        GrowthActionLog::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'action' => 'data_erasure', 'source' => 'system', 'outcome' => 'ok', 'meta' => $out, 'created_at' => now()]);

        return $out;
    }
}
