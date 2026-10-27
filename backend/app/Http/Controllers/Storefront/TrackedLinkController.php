<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\TrackedLink;
use App\Services\Growth\TrackedLinkService;
use Illuminate\Http\Request;

/** Short tracked link (Comment-to-Cart): counts the click (bots ignored) and redirects to the product page with UTM so first-touch attribution reaches the order. */
class TrackedLinkController extends Controller
{
    public function __invoke(Request $r, TrackedLinkService $svc, string $code)
    {
        $l = TrackedLink::where('code', $code)->firstOrFail(); // tenant scoped: another store's code is a 404
        // Each link kind is gated by the feature that owns it (comment-to-cart, experiment lab, ambassadors).
        $key = $l->ambassador_id ? 'ambassadors' : ($l->growth_experiment_id ? 'experiment_lab' : 'comment_to_cart');
        abort_unless(app(\App\Services\Growth\GrowthFeatures::class)->active($key, current_tenant()), 404);
        $to = $svc->target($l) ?? abort(404);
        $svc->click($l, $r->userAgent());

        return redirect()->to($to, 302);
    }
}
