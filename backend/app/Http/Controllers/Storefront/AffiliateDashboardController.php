<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Affiliates\AffiliateService;
use App\Services\Affiliates\AffiliateTracker;
use App\Services\QrCodeService;
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

    /** QR image (SVG) of the affiliate's unique link for a product. */
    public function qr(string $locale, string $slug, QrCodeService $qr): Response
    {
        $product = Product::where('slug', $slug)->firstOrFail();
        $url = $this->tracker->productUrl(auth('affiliate')->user(), $product);

        return response($qr->svg($url), 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, max-age=3600']);
    }

    public function requestPayout(): JsonResponse
    {
        $this->service->requestPayout(auth('affiliate')->user());

        return $this->ok(__('growth.payout_requested'));
    }
}
