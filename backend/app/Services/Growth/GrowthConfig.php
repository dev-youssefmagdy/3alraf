<?php

namespace App\Services\Growth;

use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

/** config/growth.php guardrails merged with tenant overrides (tenants.settings.growth.config). Only whitelisted, bounded keys are overridable. */
class GrowthConfig
{
    /** key => [min, max]; lists are handled separately. */
    public const OVERRIDABLE = [
        'min_margin_percent' => [0, 95], 'max_discount_percent' => [0, 90], 'low_stock_threshold' => [0, 100000],
        'min_days_of_cover' => [0, 365], 'max_days_of_cover' => [1, 730], 'reorder_cash_cap' => [0, 1e9],
        'ad_daily_budget_cap' => [0, 1e7], 'ad_total_budget_cap' => [0, 1e8],
    ];

    public function all(?Tenant $tenant = null): array
    {
        $tenant ??= current_tenant();
        $over = (array) ((($tenant?->settings ?? [])['growth'] ?? [])['config'] ?? []);
        $base = (array) config('growth.guardrails');
        foreach ($over as $k => $v) {
            if (array_key_exists($k, self::OVERRIDABLE) || $k === 'auto_reply_allowlist') {
                $base[$k] = $v;
            }
        }

        return $base;
    }

    public function get(string $key, mixed $default = null, ?Tenant $tenant = null): mixed
    {
        return $this->all($tenant)[$key] ?? $default;
    }

    /** Overrides stored for the tenant (only what differs from defaults). */
    public function overrides(?Tenant $tenant = null): array
    {
        $tenant ??= current_tenant();

        return (array) ((($tenant?->settings ?? [])['growth'] ?? [])['config'] ?? []);
    }

    /** @param array<string,mixed> $input */
    public function update(Tenant $tenant, array $input): array
    {
        $clean = [];
        $errors = [];
        foreach (self::OVERRIDABLE as $k => [$min, $max]) {
            $v = $input[$k] ?? null;
            if ($v === null || $v === '') {
                continue;
            }
            if (! is_numeric($v) || $v < $min || $v > $max) {
                $errors[$k] = __('growth_hub.settings.out_of_range', ['min' => $min, 'max' => $max]);

                continue;
            }
            $clean[$k] = $v + 0;
        }
        $min = $clean['min_days_of_cover'] ?? $this->get('min_days_of_cover', 0, $tenant);
        $max = $clean['max_days_of_cover'] ?? $this->get('max_days_of_cover', 0, $tenant);
        if ($min > $max) {
            $errors['min_days_of_cover'] = __('growth_hub.settings.cover_order');
        }
        $list = $input['auto_reply_allowlist'] ?? null;
        if ($list !== null) {
            $items = is_array($list) ? $list : preg_split('/[\s,]+/', (string) $list, -1, PREG_SPLIT_NO_EMPTY);
            $clean['auto_reply_allowlist'] = array_values(array_unique(array_filter(array_map(fn ($x) => preg_replace('/[^a-z0-9_.-]/', '', strtolower((string) $x)), $items))));
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $s = $tenant->settings ?? [];
        $s['growth']['config'] = $clean;
        $tenant->forceFill(['settings' => $s])->save();

        return $this->all($tenant);
    }
}
