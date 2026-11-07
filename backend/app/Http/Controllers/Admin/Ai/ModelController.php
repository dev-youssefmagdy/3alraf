<?php

namespace App\Http\Controllers\Central\Ai;

use App\DataTables\AiModelDataTable;
use App\DTOs\Ai\AiModelData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Ai\AiModelRequest;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Services\Ai\AiModelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ModelController extends Controller implements HasMiddleware
{
    public function __construct(private AiModelService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:ai_models.view', only: ['index', 'datatable']),
            new Middleware('can:ai_models.create', only: ['create', 'store']),
            new Middleware('can:ai_models.edit', only: ['edit', 'update', 'validateForm']),
            new Middleware('can:ai_models.delete', only: ['destroy']),
        ];
    }

    public function index()
    {
        return view('central.ai.models.index', ['table' => new AiModelDataTable]);
    }

    public function datatable(AiModelDataTable $table): JsonResponse
    {
        return $table->ajax();
    }

    public function create()
    {
        return view('central.ai.models.form', ['model' => new AiModel(['is_active' => true, 'kind' => 'text']), 'providers' => AiProvider::orderBy('name')->pluck('name', 'id')]);
    }

    public function store(AiModelRequest $request): JsonResponse
    {
        $this->service->create(AiModelData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('central.ai.models.index'));
    }

    public function edit(AiModel $ai_model)
    {
        return view('central.ai.models.form', ['model' => $ai_model, 'providers' => AiProvider::orderBy('name')->pluck('name', 'id')]);
    }

    public function update(AiModelRequest $request, AiModel $ai_model): JsonResponse
    {
        $this->service->update($ai_model, AiModelData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('central.ai.models.index'));
    }

    public function destroy(AiModel $ai_model): JsonResponse
    {
        $this->service->delete($ai_model);

        return $this->ok(__('admin.deleted'));
    }

    public function validateForm(AiModelRequest $request, ?AiModel $ai_model = null): JsonResponse
    {
        return $this->ok();
    }
}
