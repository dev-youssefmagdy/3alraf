<?php

namespace App\DTOs\Reports;

use Carbon\Carbon;
use Illuminate\Http\Request;

/** Date range + tenant scope for report queries. Tenant context: exactly the current tenant, own currency. Central: selected tenant(s) or all, normalized by currency_rate. */
final readonly class ReportFilter
{
    /** @param ?array<int,int> $tenantIds null = all tenants (central only) */
    public function __construct(
        public string $from,
        public string $to,
        public bool $central = false,
        public ?array $tenantIds = null,
        public ?int $branchId = null,
    ) {}

    public static function fromRequest(Request $r, bool $central): self
    {
        $to = self::date($r->input('to'), now()->toDateString());
        $from = self::date($r->input('from'), Carbon::parse($to)->subDays(29)->toDateString());
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $tenantId = $central ? selected_tenant_id() : current_tenant()?->id;

        return new self($from, $to, $central, $tenantId ? [$tenantId] : null, $central || ! $r->filled('branch_id') ? null : (int) $r->input('branch_id'));
    }

    public function start(): string
    {
        return $this->from.' 00:00:00';
    }

    public function end(): string
    {
        return $this->to.' 23:59:59';
    }

    /** Parameters to carry over in links (export, etc.). */
    public function query(): array
    {
        return array_filter(['from' => $this->from, 'to' => $this->to, 'branch_id' => $this->branchId]);
    }

    private static function date(mixed $v, string $default): string
    {
        try {
            return $v ? Carbon::parse((string) $v)->toDateString() : $default;
        } catch (\Throwable) {
            return $default;
        }
    }
}
