<?php

namespace App\Http\Controllers\Tenant\Growth;

use App\DataTables\GrowthExperimentDataTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\GrowthExperimentRequest;
use App\Http\Requests\Tenant\GrowthExperimentResultRequest;
use App\Models\GrowthExperiment;
use App\Services\Growth\ExperimentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ExperimentController extends Controller implements HasMiddleware
{
    public function __construct(private ExperimentService $service) {}

    public static function middleware(): array
    {
        return [new Middleware('can:growth.view', only: ['index', 'datatable', 'show']), new Middleware('can:growth.manage', except: ['index', 'datatable', 'show'])];
    }

    public function index(GrowthExperimentDataTable $table)
    {
        return view('tenant.growth.experiments', ['table' => $table]);
    }

    public function datatable(GrowthExperimentDataTable $table)
    {
        return $table->ajax();
    }

    public function show(int $id)
    {
        return view('tenant.growth.experiment', ['exp' => GrowthExperiment::with('results')->findOrFail($id)]);
    }

    public function store(GrowthExperimentRequest $r): JsonResponse
    {
        $e = $this->service->create($r->validated(), auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.experiments.show', $e->id));
    }

    public function update(GrowthExperimentRequest $r, int $id): JsonResponse
    {
        $this->service->update($e = GrowthExperiment::findOrFail($id), $r->validated(), auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.experiments.show', $e->id));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete(GrowthExperiment::findOrFail($id), auth('tenant')->id());

        return $this->ok(__('admin.deleted'), route('tenant.growth.experiments.index'));
    }

    public function storeResult(GrowthExperimentResultRequest $r, int $id): JsonResponse
    {
        $this->service->addResult(GrowthExperiment::findOrFail($id), $r->validated(), 'admin', auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.experiments.show', $id));
    }
}
