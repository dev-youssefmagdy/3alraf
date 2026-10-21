<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Affiliates\AffiliateService;
use App\Services\Affiliates\AffiliateTracker;
use App\Http\Requests\Qr\QrDownloadRequest;
use App\Services\Qr\QrCodeManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AffiliateDashboardController extends Controller
{
    public function __construct(private AffiliateService $service, private AffiliateTracker $tracker) {}

    public function dashboard()
    {
        $a = auth('affiliate')->user();

        return view('storefront.affiliate.dashboard', ['affiliate' => $a, 'stats' => $this->service->stats($a), 'payouts' => $a->payouts()->latest('id')->limit(20)->get(),
            'commissions' => $a->commissions()->with('order')->latest('id')->limit(20)->get()]);
    }

    public function products()
    {
        $a = auth('affiliate')->user();

        return view('storefront.affiliate.products', ['affiliate' => $a, 'items' => $this->service->products($a)]);
    }

    /** Stored PNG QR of the affiliate's unique link for a product (or ?variant=ID); generated on demand if it is missing. */
    public function qr(string $locale, string $slug, QrDownloadRequest $request, QrCodeManager $qr): Response
    {
        $png = $qr->affiliatePng(auth('affiliate')->user(), $slug, $request->variantId());
        abort_if($png === null, 404);

        return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=3600']);
    }

    public function requestPayout(): JsonResponse
    {
        $this->service->requestPayout(auth('affiliate')->user());

        return $this->ok(__('growth.payout_requested'));
    }
}
