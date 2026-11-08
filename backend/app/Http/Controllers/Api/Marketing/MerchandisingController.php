<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\Api\ListFilterRequest;
use App\Services\Marketing\MarketingCatalogService;
use App\Support\Marketing\MarketingResponse as R;
use Illuminate\Http\JsonResponse;

/** GET /api/internal/marketing — bundles, banners / sliders, offers (flash sales), coupons (scope marketing:read). No customer data. */
class MerchandisingController extends Controller
{
    public function __construct(private MarketingCatalogService $svc) {}

    public function bundles(ListFilterRequest $r): JsonResponse
    {
        $q = $r->toQuery();
        $res = $this->svc->bundles($q);

        return R::page($res['page'], fn ($x) => $x, $res['warnings'], ['range' => $q->range()]);
    }

    public function banners(ListFilterRequest $r): JsonResponse
    {
        return R::page($this->svc->banners($r->toQuery()), fn ($x) => $x);
    }

    public function offers(ListFilterRequest $r): JsonResponse
    {
        $res = $this->svc->offers($r->toQuery());

        return R::page($res['page'], fn ($x) => $x, $res['warnings']);
    }

    public function coupons(ListFilterRequest $r): JsonResponse
    {
        $q = $r->toQuery();

        return R::page($this->svc->coupons($q), fn ($x) => $x, [], ['range' => $q->range()]);
    }
}
