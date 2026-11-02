<?php

namespace App\Services\Erp\Equity;

use App\Models\Erp\ErpSetting;

/** Per-tenant ERP policy settings (erp_settings key/value json). Defaults are the safest values. */
class ErpSettings
{
    public const DEFAULTS = [
        'auto_posting_enabled' => true,                    // listeners post only when true (false for tenants that pre-date the ERP until backfilled/enabled)
        'opening_date' => null,                            // set by erp:backfill: documents confirmed before it live in the opening balances
        'reserve_percent' => '0',                          // % of distributable profit (after prior losses) moved to the reserves account
        'distribution_min_cash_buffer' => '0',             // cash that must stay in the business after distributions
        'distribution_requires_second_approver' => false,  // approver must differ from the creator
        'distribute_prior_retained' => false,              // also distribute positive retained earnings brought forward
        'tax_rate' => '0',                                 // % applied to positive profit before tax (estimate, not posted)
        'tax_account_id' => null,                          // optional expense account already carrying booked tax
        'costing_policy' => 'Weighted average cost per branch/product/variant (see docs/CALCULATIONS.md).',
        'revenue_policy' => 'Revenue is recognised when the order is confirmed by the admin (same rule as the Net Profit report).',
    ];

    private array $cache = [];

    private function tenantKey(): int
    {
        return current_tenant()?->id ?? 0;
    }

    public function all(): array
    {
        $t = $this->tenantKey();

        return $this->cache[$t] ??= array_merge(self::DEFAULTS, ErpSetting::pluck('value', 'key')->all());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key);
    }

    public function set(string $key, mixed $value): void
    {
        if (! array_key_exists($key, self::DEFAULTS)) {
            return;
        }
        ErpSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        unset($this->cache[$this->tenantKey()]);
    }
}
