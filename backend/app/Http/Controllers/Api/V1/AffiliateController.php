<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\AffiliateRegisterData;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\Storefront\AffiliateRegisterRequest;
use App\Http\Resources\Api\AffiliateDashboardResource;
use App\Http\Resources\Api\AffiliateProductResource;
use App\Http\Resources\Api\AffiliateResource;
use App\Http\Resources\Api\PayoutResource;
use App\Models\Product;
use App\Services\Affiliates\AffiliateService;
use App\Services\Affiliates\AffiliateTracker;
use App\Services\Api\ApiAuthService;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Affiliate area (token ability `affiliate`, a different token type from customers). */
class AffiliateController extends ApiController
{
    public function __construct(private AffiliateService $service, private ApiAuthService $auth) {}

    public function register(AffiliateRegisterRequest $r): JsonResponse
    {
        $a = $this->service->register(AffiliateRegisterData::fromRequest($r));

        return $this->respond(new AffiliateResource($a), __('growth.registered_pending'), status: 201); // no token until an admin approves
    }

    public function login(LoginRequest $r): JsonResponse
    {
        [$a, $token] = $this->auth->loginAffiliate($r->input('email'), $r->input('password'), $r->input('device_name'));

        return $this->respond(['token' => $token, 'token_type' => 'Bearer', 'abilities' => ['affiliate'], 'affiliate' => (new AffiliateResource($a))->resolve()], __('admin.welcome_back'));
    }

    public function logout(Request $r): JsonResponse
    {
        $this->auth->logout($r->user());

        return $this->respond(null, __('store.api.logged_out'));
    }

    public function me(Request $r): JsonResponse
    {
        return $this->respond(new AffiliateResource($r->user()));
    }

    public function dashboard(Request $r): JsonResponse
    {
        $a = $r->user();

        return $this->respond(new AffiliateDashboardResource(['affiliate' => $a, 'stats' => $this->service->stats($a), 'payouts' => $a->payouts()->latest('id')->limit(20)->get(),
            'commissions' => $a->commissions()->with('order')->latest('id')->limit(20)->get()]));
    }

    public function products(Request $r): JsonResponse
    {
        return $this->list(AffiliateProductResource::collection($this->service->products($r->user())));
    }

    /** SVG QR of the affiliate's product link (send the bearer token; render with an SVG widget). */
    public function qr(Request $r, string $slug, AffiliateTracker $tracker, QrCodeService $qr): Response
    {
        $url = $tracker->productUrl($r->user(), Product::where('slug', $slug)->where('is_active', true)->firstOrFail());

        return response($qr->svg($url), 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, max-age=3600']);
    }

    public function payouts(Request $r): JsonResponse
    {
        return $this->list(PayoutResource::collection($r->user()->payouts()->latest('id')->limit(100)->get()));
    }

    public function requestPayout(Request $r): JsonResponse
    {
        return $this->respond(new PayoutResource($this->service->requestPayout($r->user())), __('growth.payout_requested'), status: 201);
    }
}
