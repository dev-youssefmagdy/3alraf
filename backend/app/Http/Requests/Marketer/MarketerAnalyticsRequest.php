<?php

namespace App\Http\Requests\Marketer;

use App\DTOs\Marketing\MarketingQuery;

/** Marketer analytics filter: the dashboard date range plus day / week / month grouping (automatic from the range length when not chosen). */
class MarketerAnalyticsRequest extends MarketerDashboardRequest
{
    private const GROUPS = ['day', 'week', 'month'];

    public function rules(): array
    {
        return parent::rules() + ['group_by' => ['nullable', 'string', 'max:10']];
    }

    public function groupBy(): string
    {
        $chosen = (string) $this->query('group_by');
        if (in_array($chosen, self::GROUPS, true)) {
            return $chosen;
        }
        $days = MarketingQuery::make($this->filter()->from, $this->filter()->to)->days();

        return $days <= 62 ? 'day' : ($days <= 200 ? 'week' : 'month');
    }

    public function toQuery(): MarketingQuery
    {
        $f = $this->filter();

        return MarketingQuery::make($f->from, $f->to, 1, 10, ['group_by' => $this->groupBy()]);
    }
}
