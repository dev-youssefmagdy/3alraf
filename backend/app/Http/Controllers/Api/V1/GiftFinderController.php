<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Storefront\GiftFinderCartRequest;
use App\Http\Requests\Storefront\GiftFinderRequest;
use App\Services\GiftFinder\GiftFinderService;
use Illuminate\Http\JsonResponse;

/**
 * Mobile/API Gift Finder (route gate `gift_finder.enabled`, setting on Settings → General): same rules and payload as the web form.
 * `items` = unified ranked list (type product|bundle); `results` / `bundles` / `near_budget` stay for app builds before P3.
 */
class GiftFinderController extends ApiController
{
    public function __construct(private GiftFinderService $svc) {}

    public function search(GiftFinderRequest $r): JsonResponse
    {
        return $this->respond($this->svc->search($r->validated()));
    }

    /** A stored search (share link) re-rendered: same answers, same suggestions in the same order, current prices / stock. */
    public function show(string $token): JsonResponse
    {
        return $this->respond($this->svc->shared($token));
    }

    public function cart(GiftFinderCartRequest $r, string $token): JsonResponse
    {
        return $this->respond($this->svc->prepareCart($token, (array) $r->input('product_ids', []), (array) $r->input('variants', []), (array) $r->input('bundle_ids', [])));
    }
}
