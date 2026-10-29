<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Storefront\GiftFinderRequest;
use App\Services\Growth\GiftFinderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Mobile/API Gift Finder (feature `gift_finder`): same rules as the web form. */
class GiftFinderController extends ApiController
{
    public function __construct(private GiftFinderService $svc) {}

    public function search(GiftFinderRequest $r): JsonResponse
    {
        return $this->respond($this->svc->search($r->validated()));
    }

    public function cart(Request $r, string $token): JsonResponse
    {
        return $this->respond($this->svc->prepareCart($token, (array) $r->input('product_ids', []), (array) $r->input('variants', [])));
    }
}
