<?php

namespace App\Services\Growth;

use App\Models\Setting;

/**
 * Per-feature switches (plan section 9). active = master flag ON + own switch ON + every depends_on active.
 * Switches live in setting `growth.features.{key}`; absent = OFF. Switching never deletes data.
 */
class GrowthFeatures
{
    public function __construct(private GrowthAccess $access, private ActionLogger $log) {}

    public function registry(bool $builtOnly = true): array
    {
        return collect(config('growth.features'))->when($builtOnly, fn ($c) => $c->filter(fn ($f) => $f['built'] ?? false))->all();
    }

    public function switchedOn(string $key): bool
    {
        return (bool) (((array) (setting('growth.features') ?? []))[$key] ?? false);
    }

    public function active(string $key): bool
    {
        return $this->activeChain($key, []);
    }

    private function activeChain(string $key, array $seen): bool
    {
        $def = config('growth.features.'.$key);
        if (! $def || in_array($key, $seen, true) || ! $this->access->enabled() || ! $this->switchedOn($key)) {
            return false;
        }
        foreach ($def['depends_on'] ?? [] as $dep) {
            if (! $this->activeChain($dep, [...$seen, $key])) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string,bool> effective state of every built feature */
    public function states(): array
    {
        return collect($this->registry())->map(fn ($f, $k) => $this->active($k))->all();
    }

    /** True when any of the features is active (scheduled commands skip their work otherwise). */
    public function anyActive(string ...$keys): bool
    {
        foreach ($keys as $k) {
            if ($this->active($k)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string,bool> $switches key => on (unknown keys ignored). Audited. */
    public function update(array $switches, ?int $actorId = null): array
    {
        $growth = (array) (setting('growth') ?? []);
        $features = (array) ($growth['features'] ?? []);
        $changed = [];
        foreach ($this->registry() as $k => $_) {
            if (array_key_exists($k, $switches) && (bool) ($features[$k] ?? false) !== (bool) $switches[$k]) {
                $features[$k] = (bool) $switches[$k];
                $changed[$k] = $features[$k];
            }
        }
        if ($changed) {
            $growth['features'] = $features;
            Setting::put('growth', $growth);
            $this->log->log('features.updated', 'admin', $actorId, null, null, 'ok', ['changed' => $changed]);
        }

        return $changed;
    }

    public function enableAll(): void
    {
        $this->update(array_fill_keys(array_keys($this->registry()), true));
    }
}
