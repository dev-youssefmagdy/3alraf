<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Integration\InteractionIngestRequest;
use App\Http\Requests\Api\Integration\TrackedLinkRequest;
use App\Models\Interaction;
use App\Models\TrackedLink;
use App\Services\Growth\IdempotencyService;
use App\Services\Growth\InteractionService;
use App\Services\Growth\MissedOpportunityService;
use App\Services\Growth\ReputationService;
use App\Services\Growth\SuggestedReplyService;
use App\Services\Growth\TrackedLinkService;
use App\Services\Growth\VocService;
use Illuminate\Http\JsonResponse;

/** N4 integration endpoints. Ingest is idempotent (Idempotency-Key + unique channel/external_id); text is PII-masked before storage; nothing here sends a message. */
class EngagementController extends Controller
{
    public function __construct(private InteractionService $ix, private IdempotencyService $idem) {}

    public function ingest(InteractionIngestRequest $r): JsonResponse
    {
        return $this->idem->run($r->attributes->get('integration_client'), $r, function () use ($r) {
            [$i, $created] = $this->ix->ingest($r->validated());

            return [$created ? 201 : 200, ['success' => true, 'data' => ['id' => $i->id, 'created' => $created, 'status' => $i->status, 'category' => $i->category, 'intent' => $i->intent, 'urgency' => $i->urgency,
                'sentiment' => $i->sentiment, 'escalation_reason' => $i->escalation_reason, 'opted_out' => $i->opted_out, 'window_open' => $this->ix->windowOpen($i)]]];
        });
    }

    public function suggest(SuggestedReplyService $svc, int $id): JsonResponse
    {
        $i = Interaction::findOrFail($id); // tenant scoped

        return response()->json(['success' => true, 'data' => $svc->suggest($i)]);
    }

    public function responded(int $id): JsonResponse
    {
        $i = $this->ix->markResponded($id, null, 'n8n');

        return response()->json(['success' => true, 'data' => ['id' => $i->id, 'status' => $i->status]]);
    }

    public function link(TrackedLinkRequest $r, TrackedLinkService $svc): JsonResponse
    {
        return $this->idem->run($r->attributes->get('integration_client'), $r, function () use ($r, $svc) {
            $d = $r->validated();
            $iid = ! empty($d['interaction_id']) && Interaction::whereKey($d['interaction_id'])->exists() ? $d['interaction_id'] : null;
            $l = $svc->create((int) $d['product_id'], $d['channel'] ?? null, $iid, $d['campaign'] ?? null);

            return [201, ['success' => true, 'data' => ['code' => $l->code, 'url' => $svc->url($l)]]];
        });
    }

    public function voc(VocService $svc): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $svc->aggregate((int) min(90, max(7, (int) request('days', 30))))]);
    }

    public function alerts(ReputationService $svc): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $svc->open()->map(fn ($a) => $a->only(['id', 'product_id', 'topic', 'rate', 'baseline_rate', 'z_score', 'sample', 'pause_auto_reply', 'examples', 'detected_on']))->values()]);
    }

    public function missed(MissedOpportunityService $svc): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $svc->weekly()]);
    }
}
