<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\AmbassadorJoinRequest;
use App\Http\Requests\Storefront\AmbassadorLinkRequest;
use App\Models\Ambassador;
use App\Models\AmbassadorIdea;
use App\Models\TenantProduct;
use App\Models\TrackedLink;
use App\Services\Growth\AmbassadorService;
use App\Services\Growth\TrackedLinkService;
use Illuminate\Http\JsonResponse;

/** Affiliate-facing ambassador area: opt-in, claims guide, idea library, own per-channel links and report (gated by growth.feature:ambassadors). */
class AmbassadorPortalController extends Controller
{
    public function __construct(private AmbassadorService $svc) {}

    public function show(TrackedLinkService $links)
    {
        $aff = auth('affiliate')->user();
        $amb = Ambassador::where('affiliate_id', $aff->id)->first();
        $ls = $amb ? TrackedLink::where('ambassador_id', $amb->id)->orderByDesc('id')->get()->each(fn ($l) => $l->short = $links->url($l)) : collect();

        return view('storefront.affiliate.ambassador', ['amb' => $amb, 'ideas' => AmbassadorIdea::orderByDesc('id')->get(), 'links' => $ls, 'channels' => AmbassadorService::CHANNELS,
            'products' => TenantProduct::with('product')->where('is_active', true)->limit(100)->get(), 'report' => $amb ? $this->svc->report($amb, now()->subDays(29), now()) : null]);
    }

    public function join(AmbassadorJoinRequest $r): JsonResponse
    {
        $this->svc->enroll(auth('affiliate')->id(), $r->validated());

        return $this->ok(__('growth_hub.amb.joined'), route('storefront.affiliate.ambassador', ['locale' => app()->getLocale()]));
    }

    public function link(AmbassadorLinkRequest $r): JsonResponse
    {
        $d = $r->validated();
        $amb = Ambassador::where('affiliate_id', auth('affiliate')->id())->firstOrFail();
        $this->svc->link($amb, (int) $d['product_id'], $d['channel']);

        return $this->ok(__('admin.saved'), route('storefront.affiliate.ambassador', ['locale' => app()->getLocale()]));
    }
}
