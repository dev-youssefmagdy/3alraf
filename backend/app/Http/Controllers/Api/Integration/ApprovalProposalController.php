<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Integration\ProposalRequest;
use App\Models\GrowthApproval;
use App\Services\Growth\ApprovalService;
use App\Services\Growth\IdempotencyService;
use Illuminate\Http\JsonResponse;

/** n8n proposes; humans decide. A proposal is only ever a pending approval row (never an executed action). */
class ApprovalProposalController extends Controller
{
    public function __construct(private ApprovalService $approvals, private IdempotencyService $idem) {}

    public function store(ProposalRequest $r): JsonResponse
    {
        $client = $r->attributes->get('integration_client');

        return $this->idem->run($client, $r, function () use ($r, $client) {
            $a = $this->approvals->request($r->validated('type'), $r->validated('title'), (array) $r->validated('payload', []), null, 'client:'.$client->id);

            return [201, ['success' => true, 'data' => ['id' => $a->id, 'status' => $a->status]]];
        });
    }

    public function show(int $id): JsonResponse
    {
        $a = GrowthApproval::findOrFail($id);

        return response()->json(['success' => true, 'data' => ['id' => $a->id, 'type' => $a->type, 'status' => $a->status, 'decided_at' => $a->decided_at?->toIso8601String(), 'reason' => $a->reason]]);
    }
}
