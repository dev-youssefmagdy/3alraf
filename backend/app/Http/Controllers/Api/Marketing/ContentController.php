<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\Api\ListFilterRequest;
use App\Http\Requests\Marketing\Api\MarketingQueryRequest;
use App\Http\Requests\Marketing\Api\SeoDataRequest;
use App\Services\Marketing\MarketingContentService;
use App\Support\Marketing\MarketingResponse as R;
use Illuminate\Http\JsonResponse;

/** GET /api/internal/marketing — theme, static pages, SEO data, blog, seasonal calendar (scope marketing:read). Text = untrusted plain data. */
class ContentController extends Controller
{
    public function __construct(private MarketingContentService $svc) {}

    public function theme(): JsonResponse
    {
        $res = $this->svc->theme();

        return R::ok($res['data'], $res['warnings']);
    }

    public function staticPages(ListFilterRequest $r): JsonResponse
    {
        return R::page($this->svc->staticPages($r->toQuery()), fn ($x) => $x);
    }

    public function seoData(SeoDataRequest $r): JsonResponse
    {
        $res = $this->svc->seoData($r->toQuery());

        return R::page($res['page'], fn ($x) => $x, $res['warnings'], ['entity_type' => $r->toQuery()->filter('entity_type', 'product')]);
    }

    public function blogPosts(ListFilterRequest $r): JsonResponse
    {
        return R::page($this->svc->blogPosts($r->toQuery()), fn ($x) => $x);
    }

    public function blogCategories(MarketingQueryRequest $r): JsonResponse
    {
        $q = $r->toQuery();

        return R::slice([], $q->page, $q->perPage, [$this->svc->blogCategoriesWarning()]);
    }

    public function seasonalCalendar(ListFilterRequest $r): JsonResponse
    {
        $res = $this->svc->seasonalCalendar((int) $r->toQuery()->filter('days', 90));

        return R::ok($res['data'], $res['warnings']);
    }
}
