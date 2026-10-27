<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Integration\AdResultsRequest;
use App\Models\GrowthExperiment;
use App\Services\Growth\ExperimentLabService;
use App\Services\Growth\IdempotencyService;
use Illuminate\Http\JsonResponse;

/** N5: ad-platform results per day (idempotent upsert) and read-only lab metrics. Carries no budget / spend instruction: money changes go through approvals. */
class AdResultsController extends Controller
{
    public function __construct(private ExperimentLabService $lab, private IdempotencyService $idem) {}

    public function store(AdResultsRequest $r, int $id): JsonResponse
    {
        $client = $r->attributes->get('integration_client');
        $e = GrowthExperiment::whereNotNull('product_id')->findOrFail($id); // tenant scoped

        return $this->idem->run($client, $r, fn () => [200, ['success' => true, 'data' => $this->lab->importAds($e, $r->validated()['rows'], 'n8n', $client->id)]]);
    }

    public function show(int $id): JsonResponse
    {
        $e = GrowthExperiment::whereNotNull('product_id')->findOrFail($id);

        return response()->json(['success' => true, 'data' => ['metrics' => $this->lab->metrics($e), 'fatigue' => $this->lab->fatigue($e), 'roas_note' => 'ROAS is revenue / spend, not profit']]);
    }
}
