<?php

namespace App\Services\Marketing\Meta;

use Illuminate\Support\Facades\Cache;

/**
 * Cached read endpoints of the Meta Marketing API (P6, MCP.md §7 READ): ad accounts, account info, campaigns, ad sets, ads, insights.
 * Cache TTL config meta.cache_minutes (key = path + query; cleared with Cache::flush / tag-less). Output is normalised: budgets stay in Meta minor
 * units (`*_minor`) AND are converted to major units; insights expose spend, impressions, reach, clicks, CTR, CPC, CPM, conversions, conversion
 * value and ROAS when Meta returns them (null otherwise — never invented).
 */
class MetaReadService
{
    private const PURCHASE_ACTIONS = ['purchase', 'omni_purchase', 'offsite_conversion.fb_pixel_purchase', 'onsite_web_purchase'];

    public function __construct(private MetaGraphClient $client) {}

    public function configured(): bool
    {
        return $this->client->configured();
    }

    public function status(): array
    {
        return ['configured' => $this->client->configured(), 'page_configured' => $this->client->pageConfigured(), 'graph_version' => (string) config('meta.graph_version'),
            'ad_account_id' => $this->client->configured() ? $this->client->accountId() : null];
    }

    public function adAccounts(): array
    {
        $r = $this->cached('me/adaccounts', ['fields' => 'id,account_id,name,account_status,currency,timezone_name,amount_spent', 'limit' => 50]);

        return array_map(fn ($a) => ['id' => $a['id'] ?? null, 'account_id' => $a['account_id'] ?? null, 'name' => $a['name'] ?? null,
            'account_status' => $a['account_status'] ?? null, 'currency' => $a['currency'] ?? null, 'timezone' => $a['timezone_name'] ?? null,
            'amount_spent' => isset($a['amount_spent']) ? round((int) $a['amount_spent'] / 100, 2) : null], (array) ($r['data'] ?? []));
    }

    public function account(): array
    {
        $a = $this->cached($this->client->accountId(), ['fields' => 'id,account_id,name,account_status,currency,timezone_name,amount_spent,business_name']);

        return ['id' => $a['id'] ?? null, 'account_id' => $a['account_id'] ?? null, 'name' => $a['name'] ?? null, 'account_status' => $a['account_status'] ?? null,
            'currency' => $a['currency'] ?? null, 'timezone' => $a['timezone_name'] ?? null, 'business_name' => $a['business_name'] ?? null,
            'amount_spent' => isset($a['amount_spent']) ? round((int) $a['amount_spent'] / 100, 2) : null];
    }

    public function campaigns(int $limit = 25, ?string $after = null): array
    {
        $r = $this->cached($this->client->accountId().'/campaigns', array_filter(['fields' => 'id,name,objective,status,effective_status,daily_budget,lifetime_budget,start_time,stop_time,created_time',
            'limit' => $limit, 'after' => $after]));

        return $this->page($r, fn ($c) => ['id' => $c['id'] ?? null, 'name' => $c['name'] ?? null, 'objective' => $c['objective'] ?? null, 'status' => $c['status'] ?? null,
            'effective_status' => $c['effective_status'] ?? null] + $this->budgets($c) + ['start_time' => $c['start_time'] ?? null, 'stop_time' => $c['stop_time'] ?? null,
            'created_time' => $c['created_time'] ?? null]);
    }

    public function adSets(?string $campaignId = null, int $limit = 25, ?string $after = null): array
    {
        $path = $campaignId ? $this->id($campaignId).'/adsets' : $this->client->accountId().'/adsets';
        $r = $this->cached($path, array_filter(['fields' => 'id,name,campaign_id,status,effective_status,daily_budget,lifetime_budget,optimization_goal,billing_event,start_time,end_time',
            'limit' => $limit, 'after' => $after]));

        return $this->page($r, fn ($s) => ['id' => $s['id'] ?? null, 'name' => $s['name'] ?? null, 'campaign_id' => $s['campaign_id'] ?? null, 'status' => $s['status'] ?? null,
            'effective_status' => $s['effective_status'] ?? null] + $this->budgets($s) + ['optimization_goal' => $s['optimization_goal'] ?? null,
            'billing_event' => $s['billing_event'] ?? null, 'start_time' => $s['start_time'] ?? null, 'end_time' => $s['end_time'] ?? null]);
    }

    public function ads(?string $adSetId = null, int $limit = 25, ?string $after = null): array
    {
        $path = $adSetId ? $this->id($adSetId).'/ads' : $this->client->accountId().'/ads';
        $r = $this->cached($path, array_filter(['fields' => 'id,name,adset_id,campaign_id,status,effective_status,creative{id,name}', 'limit' => $limit, 'after' => $after]));

        return $this->page($r, fn ($a) => ['id' => $a['id'] ?? null, 'name' => $a['name'] ?? null, 'adset_id' => $a['adset_id'] ?? null, 'campaign_id' => $a['campaign_id'] ?? null,
            'status' => $a['status'] ?? null, 'effective_status' => $a['effective_status'] ?? null, 'creative_id' => $a['creative']['id'] ?? null]);
    }

    /**
     * Insights for the account (no id) or one campaign / ad set / ad. $level: account|campaign|adset|ad (breakdown rows).
     * Range: $preset (config meta.insights_presets) or since/until (Y-m-d).
     */
    public function insights(?string $objectId = null, string $level = 'account', ?string $preset = 'last_30d', ?string $since = null, ?string $until = null): array
    {
        $q = ['fields' => implode(',', (array) config('meta.insights_fields')), 'level' => $level, 'limit' => 100];
        if ($since && $until) {
            $q['time_range'] = json_encode(['since' => $since, 'until' => $until]);
        } else {
            $q['date_preset'] = $preset ?: 'last_30d';
        }
        if ($level !== 'account') {
            $q['fields'] .= ','.$level.'_id,'.$level.'_name';
        }
        $r = $this->cached(($objectId ? $this->id($objectId) : $this->client->accountId()).'/insights', $q);

        return array_map(function ($row) use ($level) {
            $conv = $this->sumActions((array) ($row['actions'] ?? []));
            $value = $this->sumActions((array) ($row['action_values'] ?? []));
            $roas = collect((array) ($row['purchase_roas'] ?? []))->first();

            return array_filter(['id' => $level !== 'account' ? ($row[$level.'_id'] ?? null) : null, 'name' => $level !== 'account' ? ($row[$level.'_name'] ?? null) : null], fn ($v) => $v !== null) + [
                'date_start' => $row['date_start'] ?? null, 'date_stop' => $row['date_stop'] ?? null,
                'spend' => isset($row['spend']) ? round((float) $row['spend'], 2) : null, 'impressions' => isset($row['impressions']) ? (int) $row['impressions'] : null,
                'reach' => isset($row['reach']) ? (int) $row['reach'] : null, 'clicks' => isset($row['clicks']) ? (int) $row['clicks'] : null,
                'ctr' => isset($row['ctr']) ? round((float) $row['ctr'], 4) : null, 'cpc' => isset($row['cpc']) ? round((float) $row['cpc'], 4) : null,
                'cpm' => isset($row['cpm']) ? round((float) $row['cpm'], 4) : null, 'conversions' => $conv, 'conversion_value' => $value,
                'roas' => is_array($roas) && isset($roas['value']) ? round((float) $roas['value'], 4) : null,
            ];
        }, (array) ($r['data'] ?? []));
    }

    // ------------------------------------------------------------------ helpers

    private function cached(string $path, array $query): array
    {
        $key = 'meta:read:'.sha1($path.'?'.http_build_query($query).'|'.config('meta.graph_version').'|'.$this->client->accountDigits());

        return Cache::remember($key, now()->addMinutes(max(1, (int) config('meta.cache_minutes', 10))), fn () => $this->client->get($path, $query));
    }

    private function page(array $r, callable $map): array
    {
        return ['items' => array_values(array_map($map, (array) ($r['data'] ?? []))), 'next_cursor' => isset($r['paging']['next']) ? ($r['paging']['cursors']['after'] ?? null) : null];
    }

    private function budgets(array $o): array
    {
        $d = isset($o['daily_budget']) ? (int) $o['daily_budget'] : null;
        $l = isset($o['lifetime_budget']) ? (int) $o['lifetime_budget'] : null;

        return ['daily_budget_minor' => $d, 'lifetime_budget_minor' => $l, 'daily_budget' => $d !== null ? round($d / 100, 2) : null, 'lifetime_budget' => $l !== null ? round($l / 100, 2) : null];
    }

    private function sumActions(array $rows): ?float
    {
        $hit = array_filter($rows, fn ($a) => in_array($a['action_type'] ?? null, self::PURCHASE_ACTIONS, true));
        if (! $hit) {
            return null;
        }
        // Meta reports the same purchase under several action types: take the largest (omni / pixel), never the sum of duplicates.
        return round(max(array_map(fn ($a) => (float) ($a['value'] ?? 0), $hit)), 2);
    }

    private function id(string $id): string
    {
        if (! preg_match('/^\d{1,30}$/', $id)) {
            throw new MetaException('unsafe_request', 'Invalid Meta object id.', 422);
        }

        return $id;
    }
}
