<?php

namespace App\Http\Requests\Marketer;

use App\DTOs\Marketing\MarketingQuery;
use App\DTOs\Reports\ReportFilter;
use App\Http\Requests\Admin\BaseRequest;
use Carbon\CarbonImmutable;

/** Marketer dashboard date range (default last 30 days). Lenient like the reports filter: bad dates fall back, the range is capped. */
class MarketerDashboardRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['from' => ['nullable', 'string', 'max:20'], 'to' => ['nullable', 'string', 'max:20']];
    }

    public function filter(): ReportFilter
    {
        $f = ReportFilter::fromRequest($this);
        $max = (int) config('marketing.max_range_days', 366);
        if (CarbonImmutable::parse($f->from)->diffInDays(CarbonImmutable::parse($f->to)) + 1 > $max) {
            $f = new ReportFilter(CarbonImmutable::parse($f->to)->subDays($max - 1)->toDateString(), $f->to);
        }

        return $f;
    }

    public function toQuery(): MarketingQuery
    {
        $f = $this->filter();

        return MarketingQuery::make($f->from, $f->to, 1, 10);
    }
}
