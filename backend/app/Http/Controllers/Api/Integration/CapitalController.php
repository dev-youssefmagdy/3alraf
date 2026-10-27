<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Integration\ReturnReasonRequest;
use App\Services\Growth\CeoChallengeService;
use App\Services\Growth\InventoryDecisionService;
use App\Services\Growth\ReturnIntelService;
use App\Services\Growth\TrendRadarService;
use Illuminate\Http\JsonResponse;

/** N6 integration endpoints (read + structured return reason ingest). Nothing here purchases, reprices or refunds. */
class CapitalController extends Controller
{
    public function inventory(InventoryDecisionService $svc): JsonResponse
    {
        $a = $svc->analyze();

        return response()->json(['success' => true, 'data' => ['summary' => $a['summary'], 'recommendations' => array_values(array_filter($a['rows'], fn ($r) => $r['action'] !== 'wait' || $r['reasons'])), 'note' => 'recommendations_only_no_purchase']]);
    }

    public function returns(ReturnIntelService $svc): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $svc->report()]);
    }

    public function reason(ReturnReasonRequest $r, ReturnIntelService $svc): JsonResponse
    {
        $d = $r->validated();
        $d['kind'] === 'return' ? $svc->tagReturn($d['id'], $d['code'], null) : $svc->tagCancellation($d['id'], $d['code'], null);

        return response()->json(['success' => true, 'data' => ['kind' => $d['kind'], 'id' => $d['id'], 'code' => $d['code']]]);
    }

    public function trends(TrendRadarService $svc): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['week' => TrendRadarService::week(), 'candidates' => $svc->candidates()->map(fn ($c) => $c->only(['id', 'name', 'source', 'product_id', 'score', 'components', 'evidence', 'uncertainty', 'confidence', 'status']))->all()]]);
    }

    public function challenge(CeoChallengeService $svc): JsonResponse
    {
        $c = $svc->history(1)->first();

        return response()->json(['success' => true, 'data' => $c ? $c->only(['id', 'week', 'rule', 'question', 'hypothesis', 'experiment', 'metric', 'duration_days', 'evidence', 'status', 'result_note']) + ['review_on' => $c->review_on->toDateString()] : null]);
    }
}
