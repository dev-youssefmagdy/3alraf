<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Integration\ExperimentResultRequest;
use App\Models\GrowthExperiment;
use App\Services\Growth\ExperimentService;
use App\Services\Growth\IdempotencyService;
use Illuminate\Http\JsonResponse;

class ExperimentResultController extends Controller
{
    public function __construct(private ExperimentService $service, private IdempotencyService $idem) {}

    public function store(ExperimentResultRequest $r, int $id): JsonResponse
    {
        $client = $r->attributes->get('integration_client');
        $exp = GrowthExperiment::findOrFail($id); // tenant-scoped: another tenant's id is a 404

        return $this->idem->run($client, $r, function () use ($r, $exp, $client) {
            $res = $this->service->addResult($exp, $r->validated(), 'n8n', $client->id);

            return [201, ['success' => true, 'data' => ['id' => $res->id]]];
        });
    }
}
