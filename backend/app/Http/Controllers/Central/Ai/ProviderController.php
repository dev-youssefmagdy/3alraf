<?php

namespace App\Http\Controllers\Central\Ai;

use App\DataTables\AiProviderDataTable;
use App\DTOs\Ai\AiProviderData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Ai\AiProviderRequest;
use App\Models\AiProvider;
use App\Services\Ai\AiProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProviderController extends Controller implements HasMiddleware
{
    public function __construct(private AiProviderService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:ai_providers.view', only: ['index', 'datatable']),
            new Middleware('can:ai_providers.create', only: ['create', 'store']),
            new Middleware('can:ai_providers.edit', only: ['edit', 'update', 'test', 'validateForm']),
            new Middleware('can:ai_providers.delete', only: ['destroy']),
        ];
    }

    public function index()
    {
        return view('central.ai.providers.index', ['table' => new AiProviderDataTable]);
    }

    public function datatable(AiProviderDataTable $table): JsonResponse
    {
        return $table->ajax();
    }

    public function create()
    {
        return view('central.ai.providers.form', ['provider' => new AiProvider(['is_active' => true])]);
    }

    public function store(AiProviderRequest $request): JsonResponse
    {
        $this->service->create(AiProviderData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('central.ai.providers.index'));
    }

    public function edit(AiProvider $ai_provider)
    {
        return view('central.ai.providers.form', ['provider' => $ai_provider]);
    }

    public function update(AiProviderRequest $request, AiProvider $ai_provider): JsonResponse
    {
        $this->service->update($ai_provider, AiProviderData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('central.ai.providers.index'));
    }

    public function destroy(AiProvider $ai_provider): JsonResponse
    {
        $this->service->delete($ai_provider);

        return $this->ok(__('admin.deleted'));
    }

    public function test(AiProvider $ai_provider): JsonResponse
    {
        $ok = $this->service->test($ai_provider);

        return response()->json(['success' => $ok, 'message' => __($ok ? 'ai.test_ok' : 'ai.test_failed')], $ok ? 200 : 422);
    }

    public function validateForm(AiProviderRequest $request, ?AiProvider $ai_provider = null): JsonResponse
    {
        return $this->ok();
    }
}
