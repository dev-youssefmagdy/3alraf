<?php

namespace App\DTOs\Reports;

use Carbon\Carbon;
use Illuminate\Http\Request;

/** Date range (+ optional branch) for report queries. One shop, one currency (setting('currency_code')): amounts are never converted. */
final readonly class ReportFilter
{
    public function __construct(
        public string $from,
        public string $to,
        public ?int $branchId = null,
    ) {}

    public static function fromRequest(Request $r): self
    {
        $to = self::date($r->input('to'), now()->toDateString());
        $from = self::date($r->input('from'), Carbon::parse($to)->subDays(29)->toDateString());
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        
        return new self($from, $to, $r->filled('branch_id') ? (int) $r->input('branch_id') : null);
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
