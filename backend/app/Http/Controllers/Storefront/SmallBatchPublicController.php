<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\SmallBatchSignupRequest;
use App\Models\Product;
use App\Models\SmallBatchTest;
use App\Services\Growth\SmallBatchService;
use Illuminate\Http\JsonResponse;

/** Public interest / survey / pre-order page of a small-batch test (gated by growth.feature:small_batch_lab). Draft tests are 404. */
class SmallBatchPublicController extends Controller
{
    public function show(string $locale, string $code)
    {
        $t = SmallBatchTest::where('code', $code)->where('status', '!=', 'draft')->firstOrFail();

        return view('storefront.small-batch', ['t' => $t, 'product' => $t->product_id ? Product::find($t->product_id) : null]);
    }

    public function signup(SmallBatchSignupRequest $r, SmallBatchService $svc, string $locale, string $code): JsonResponse
    {
        $t = SmallBatchTest::where('code', $code)->where('status', '!=', 'draft')->firstOrFail();
        [, $created] = $svc->signup($t, $r->validated());

        return $this->ok(__($created ? 'growth_hub.batch.thanks' : 'growth_hub.batch.already'));
    }
}
