<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\Api\MarketingQueryRequest;
use App\Http\Requests\Marketing\Api\ProductIndexRequest;
use App\Models\Product;
use App\Services\Marketing\MarketingCatalogService;
use App\Support\Marketing\MarketingResponse as R;
use Illuminate\Http\JsonResponse;

/** GET /api/internal/marketing — catalog reads (scope marketing:read). Product text is untrusted plain data. */
class CatalogController extends Controller
{
    public function __construct(private MarketingCatalogService $svc) {}

    public function products(ProductIndexRequest $r): JsonResponse
    {
        return R::page($this->svc->products($r->toQuery()), fn ($x) => $x, [MarketingCatalogService::originWarning()]);
    }

    public function product(int $id): JsonResponse
    {
        $p = Product::withTrashed()->find($id);
        if (! $p) {
            return R::error(404, 'not_found');
        }
        $res = $this->svc->product($p);

        return R::ok($res['data'], $res['warnings']);
    }

    public function images(int $id): JsonResponse
    {
        $p = Product::withTrashed()->find($id);
        if (! $p) {
            return R::error(404, 'not_found');
        }
        $res = $this->svc->productImages($p);

        return R::ok($res['data'], $res['warnings']);
    }

    public function categories(MarketingQueryRequest $r): JsonResponse
    {
        return R::page($this->svc->categories($r->toQuery()), fn ($x) => $x);
    }

    public function brands(MarketingQueryRequest $r): JsonResponse
    {
        return R::page($this->svc->brands($r->toQuery()), fn ($x) => $x, [MarketingCatalogService::originWarning()]);
    }

    public function inventory(MarketingQueryRequest $r): JsonResponse
    {
        return R::page($this->svc->inventory($r->toQuery()), fn ($x) => $x);
    }
}
