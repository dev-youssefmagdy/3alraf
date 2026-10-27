<?php

namespace App\Services\Growth;

use App\Models\ExperimentAdDay;
use App\Models\GrowthActionLog;
use App\Models\TenantAdmin;
use App\Notifications\GrowthNotification;
use Illuminate\Support\Facades\Gate;

/** Daily ad spend (all experiments) above the tenant's ad_daily_budget_cap: one notification + one action-log row per day, and a Brief item. Advice only; nothing is paused. */
class AdCapService
{
    public function __construct(private GrowthConfig $config, private ActionLogger $log) {}

    /** @return array{day:string,spend:float,cap:float}|null */
    public function breach(string $day): ?array
    {
        $cap = (float) $this->config->get('ad_daily_budget_cap', 0);
        $spend = round((float) ExperimentAdDay::whereDate('day', $day)->sum('spend'), 2);

        return $cap > 0 && $spend > $cap ? ['day' => $day, 'spend' => $spend, 'cap' => $cap] : null;
    }

    /** Checks the given days; returns the number of NEW alerts raised (deduped per day via the action log). */
    public function check(array $days): int
    {
        $n = 0;
        foreach (array_unique($days) as $day) {
            $b = $this->breach((string) $day);
            if (! $b || GrowthActionLog::where('action', 'ad_cap.daily_exceeded')->where('reason', 'day '.$b['day'])->exists()) {
                continue;
            }
            $this->log->log('ad_cap.daily_exceeded', 'system', null, 'day '.$b['day'], null, 'warning', $b);
            $tenant = current_tenant();
            $note = new GrowthNotification('ad_cap_exceeded', $b + ['spend_f' => number_format($b['spend'], 2), 'cap_f' => number_format($b['cap'], 2)]);
            $note->locale($tenant?->default_locale ?: config('app.locale'));
            TenantAdmin::withoutGlobalScopes()->where('tenant_id', $tenant?->id)->where('is_active', true)->get()
                ->filter(fn (TenantAdmin $a) => Gate::forUser($a)->allows('growth.view'))->each(fn (TenantAdmin $a) => $a->notify($note));
            $n++;
        }

        return $n;
    }
}
