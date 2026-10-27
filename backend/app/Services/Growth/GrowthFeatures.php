<?php

namespace App\Services\Growth;

use App\Models\Tenant;

/**
 * Per-feature switches (plan section 9). active = master flag ON + own switch ON + every depends_on active.
 * Switches live in tenants.settings.growth.features.{key}; absent = OFF. Switching never deletes data.
 */
class GrowthFeatures
{
    public function __construct(private GrowthAccess $access, private ActionLogger $log) {}

    public function registry(bool $builtOnly = true): array
    {
        return collect(config('growth.features'))->when($builtOnly, fn ($c) => $c->filter(fn ($f) => $f['built'] ?? false))->all();
    }

    public function switchedOn(string $key, ?Tenant $tenant = null): bool
    {
        $tenant ??= current_tenant();

        return (bool) (((($tenant?->settings ?? [])['growth'] ?? [])['features'] ?? [])[$key] ?? false);
    }

    public function active(string $key, ?Tenant $tenant = null, array $seen = []): bool
    {
        $tenant ??= current_tenant();
        $def = config('growth.features.'.$key);
        if (! $def || in_array($key, $seen, true) || ! $this->access->enabled($tenant) || ! $this->switchedOn($key, $tenant)) {
            return false;
        }
        foreach ($def['depends_on'] ?? [] as $dep) {
            if (! $this->active($dep, $tenant, [...$seen, $key])) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string,bool> effective state of every built feature */
    public function states(?Tenant $tenant = null): array
    {
        return collect($this->registry())->map(fn ($f, $k) => $this->active($k, $tenant))->all();
    }

    /** Active tenants with the feature on (scheduled commands iterate these). */
    public function tenantsWith(string ...$keys): \Illuminate\Support\Collection
    {
        return Tenant::query()->get()->filter(function (Tenant $t) use ($keys) {
            foreach ($keys as $k) {
                if ($this->active($k, $t)) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    /** @param array<string,bool> $switches key => on (unknown keys ignored). Audited. */
    public function update(Tenant $tenant, array $switches, ?int $actorId = null): array
    {
        $s = $tenant->settings ?? [];
        $features = $s['growth']['features'] ?? [];
        $changed = [];
        foreach ($this->registry() as $k => $_) {
            if (array_key_exists($k, $switches) && (bool) ($features[$k] ?? false) !== (bool) $switches[$k]) {
                $features[$k] = (bool) $switches[$k];
                $changed[$k] = $features[$k];
            }
        }
        if ($changed) {
            $s['growth']['features'] = $features;
            $tenant->forceFill(['settings' => $s])->save();
            $prev = app()->bound('currentTenant') ? app('currentTenant') : null;
            app()->instance('currentTenant', $tenant);
            $this->log->log('features.updated', 'admin', $actorId, null, $tenant, 'ok', ['changed' => $changed]);
            $prev ? app()->instance('currentTenant', $prev) : app()->forgetInstance('currentTenant');
        }

        return $changed;
    }

    public function enableAll(Tenant $tenant): void
    {
        $this->update($tenant, array_fill_keys(array_keys($this->registry()), true));
    }
}
