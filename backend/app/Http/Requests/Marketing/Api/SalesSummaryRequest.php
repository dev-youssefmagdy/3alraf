<?php

namespace App\Http\Requests\Marketing\Api;

/** GET sales-summary: group_by day|week|month (default day). */
class SalesSummaryRequest extends MarketingQueryRequest
{
    protected function extraRules(): array
    {
        return ['group_by' => ['sometimes', 'in:day,week,month']];
    }
}
