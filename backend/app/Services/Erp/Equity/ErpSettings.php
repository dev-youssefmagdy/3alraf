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
        'revenue_policy' => 'Revenue, VAT and COGS are recognised when control passes to the customer (default: at shipment; see revenue_recognition).',
        'revenue_recognition' => 'shipped',                // shipped | delivered | confirmed (legacy, mirrors the Net Profit report) - docs/CALCULATIONS.md §ERP revenue recognition
        'landed_costs' => 'expense',                       // expense (PO shipping/other -> purchase expenses) | capitalise (IAS 2: allocated into the received unit cost)
        'journal_requires_second_poster' => false,         // maker-checker: a manual journal draft must be posted by another user
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

    /** Numeric setting as a bcmath-safe plain decimal (QA-005: a stored "1e1" / garbage never reaches bcmath; garbage = the default). */
    public function decimal(string $key): string
    {
        $v = $this->get($key);
        $v = is_float($v) ? number_format($v, 8, '.', '') : trim((string) $v);
        if (preg_match('/^\d+(\.\d+)?$/', $v)) {
            return $v;
        }
        if (is_numeric($v) && (float) $v >= 0 && is_finite((float) $v)) {
            return rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
        }

        return (string) (self::DEFAULTS[$key] ?? '0');
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
