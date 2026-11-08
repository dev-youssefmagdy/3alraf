<?php

namespace App\DTOs\Marketing;

use App\DTOs\Reports\ReportFilter;
use Carbon\CarbonImmutable;

/** Validated query of a Marketing API read endpoint: date range (inclusive days, shop timezone), page / per_page and endpoint filters. */
final readonly class MarketingQuery
{
    public function __construct(
        public string $from,
        public string $to,
        public int $page = 1,
        public int $perPage = 25,
        public array $filters = [],
    ) {}

    /** Default range = last `marketing.default_range_days` days ending today. */
    public static function make(?string $from, ?string $to, int $page = 1, ?int $perPage = null, array $filters = []): self
    {
        $to = $to ?: now()->toDateString();
        $from = $from ?: CarbonImmutable::parse($to)->subDays(max(1, (int) config('marketing.default_range_days', 30)) - 1)->toDateString();

        return new self($from, $to, max(1, $page), $perPage ?? (int) config('marketing.per_page_default', 25), $filters);
    }

    public function report(): ReportFilter
    {
        return new ReportFilter($this->from, $this->to);
    }

    /** Inclusive number of days in the range. */
    public function days(): int
    {
        return (int) CarbonImmutable::parse($this->from)->diffInDays(CarbonImmutable::parse($this->to)) + 1;
    }

    public function filter(string $key, mixed $default = null): mixed
    {
        return $this->filters[$key] ?? $default;
    }

    public function range(): array
    {
        return ['from' => $this->from, 'to' => $this->to, 'days' => $this->days()];
    }
}
