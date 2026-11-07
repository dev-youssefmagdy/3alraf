<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Storefront\AmbassadorJoinRequest;
use App\Http\Requests\Storefront\AmbassadorLinkRequest;
use App\Models\Ambassador;
use App\Models\AmbassadorIdea;
use App\Models\TrackedLink;
use App\Services\Growth\AmbassadorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ambassador portal of an approved affiliate (token ability `affiliate`; feature `ambassadors`). Mirrors Storefront\AmbassadorPortalController. */
class AmbassadorController extends ApiController
{
    public function __construct(private AmbassadorService $svc) {}

    public function show(Request $r): JsonResponse
    {
        $amb = Ambassador::where('affiliate_id', $r->user()->id)->first();
        $report = $amb ? $this->svc->report($amb, now()->subDays(29), now()) : null;

        return $this->respond([
            'enrolled' => $amb !== null,
            'ambassador' => $amb ? $this->ambassador($amb) : null,
            'channels' => AmbassadorService::CHANNELS,
            'ideas' => AmbassadorIdea::orderByDesc('id')->get()->map(fn ($i) => ['id' => $i->id, 'title' => $i->title, 'body' => $i->body, 'channel' => $i->channel])->all(),
            'links' => $amb ? TrackedLink::where('ambassador_id', $amb->id)->orderByDesc('id')->get()->map(fn ($l) => $this->linkRow($l))->all() : [],
            // only what the web portal shows the affiliate (no margins / internal costs)
            'report' => $report ? array_intersect_key($report, array_flip(['valid_orders', 'pending_orders', 'excluded_orders', 'new_customer_share', 'referral_cac', 'window_days', 'by_channel', 'disclosure'])) : null,
        ]);
    }

    public function join(AmbassadorJoinRequest $r): JsonResponse
    {
        return $this->respond($this->ambassador($this->svc->enroll($r->user()->id, $r->validated())), __('growth_hub.amb.joined'), status: 201);
    }

    public function link(AmbassadorLinkRequest $r): JsonResponse
    {
        $d = $r->validated();
        $amb = Ambassador::where('affiliate_id', $r->user()->id)->firstOrFail();

        return $this->respond($this->linkRow($this->svc->link($amb, (int) $d['product_id'], $d['channel'])), __('admin.saved'), status: 201);
    }

    private function ambassador(Ambassador $a): array
    {
        return ['status' => $a->status, 'disclosure' => $a->disclosure, 'terms_accepted_at' => $a->terms_accepted_at?->toIso8601String()];
    }

    private function linkRow(TrackedLink $l): array
    {
        return ['id' => $l->id, 'channel' => $l->channel, 'product_id' => $l->product_id, 'clicks' => (int) $l->clicks, 'url' => url('l/'.$l->code)];
    }
}
