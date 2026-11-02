<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Storefront\SmallBatchSignupRequest;
use App\Http\Resources\Api\LaunchResource;
use App\Models\SmallBatchTest;
use App\Services\Growth\SmallBatchService;
use Illuminate\Http\JsonResponse;

/** Small-batch launch page (feature `small_batch_lab`). Draft tests are 404. */
class LaunchController extends ApiController
{
    public function show(string $code): JsonResponse
    {
        return $this->respond(new LaunchResource($this->test($code)));
    }

    public function signup(SmallBatchSignupRequest $r, SmallBatchService $svc, string $code): JsonResponse
    {
        [, $created] = $svc->signup($this->test($code), $r->validated());

        return $this->respond(['created' => $created], __($created ? 'growth_hub.batch.thanks' : 'growth_hub.batch.already'), status: $created ? 201 : 200);
    }

    private function test(string $code): SmallBatchTest
    {
        return SmallBatchTest::where('code', $code)->where('status', '!=', 'draft')->firstOrFail();
    }
}
