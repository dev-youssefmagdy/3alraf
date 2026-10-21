<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Resource over AffiliateService::stats() + recent payouts/commissions. */
class AffiliateDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;
        $s = $r['stats'];

        return [
            'affiliate' => new AffiliateResource($r['affiliate']),
            'stats' => ['clicks' => $s['clicks'], 'orders' => $s['orders'], 'conversion_percent' => $s['conversion'], 'lifetime_sales' => (string) $s['sales'],
                'tier' => $s['tier'] ? ['name' => $s['tier']->name, 'percent' => (string) $s['tier']->percent, 'min_sales' => (string) $s['tier']->min_sales] : null,
                'next_tier' => $s['next_tier'] ? ['name' => $s['next_tier']->name, 'percent' => (string) $s['next_tier']->percent, 'min_sales' => (string) $s['next_tier']->min_sales] : null,
                'commissions' => $s['commissions']],
            'payouts' => PayoutResource::collection($r['payouts']),
            'recent_commissions' => $r['commissions']->map(fn ($c) => ['order' => $c->order?->number, 'amount' => (string) $c->amount, 'percent' => (string) $c->percent, 'status' => $c->status->value])->values(),
        ];
    }
}
