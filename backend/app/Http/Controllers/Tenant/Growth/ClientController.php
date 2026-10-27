<?php

namespace App\Http\Controllers\Tenant\Growth;

use App\DataTables\GrowthClientDataTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\GrowthClientRequest;
use App\Models\IntegrationClient;
use App\Services\Growth\IntegrationClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ClientController extends Controller implements HasMiddleware
{
    public function __construct(private IntegrationClientService $service) {}

    public static function middleware(): array
    {
        return [new Middleware('can:growth.view', only: ['index', 'datatable']), new Middleware('can:growth.manage', except: ['index', 'datatable'])];
    }

    public function index(GrowthClientDataTable $table)
    {
        return view('tenant.growth.clients', ['table' => $table, 'scopes' => config('growth.scopes'), 'base' => url('/api/integration/v1')]);
    }

    public function datatable(GrowthClientDataTable $table)
    {
        return $table->ajax();
    }

    /** Token + HMAC secret are returned once, here, and never again. */
    public function store(GrowthClientRequest $r): JsonResponse
    {
        [$client, $token, $secret] = $this->service->create($r->validated('name'), $r->validated('scopes'), $r->validated('webhook_url'), auth('tenant')->id());

        return response()->json(['success' => true, 'message' => __('growth_hub.clients.created'), 'data' => ['id' => $client->id, 'token' => $token, 'secret' => $secret]], 201);
    }

    /** Name, scopes (money scopes stay impossible) and webhook only; token / secret are untouched. */
    public function update(GrowthClientRequest $r, int $id): JsonResponse
    {
        $this->service->update($this->service->findOrFail($id), $r->validated('name'), $r->validated('scopes'), $r->validated('webhook_url'), auth('tenant')->id());

        return $this->ok(__('growth_hub.clients.updated'), route('tenant.growth.clients.index'));
    }

    public function rotate(int $id): JsonResponse
    {
        [$token, $secret] = $this->service->rotate($this->service->findOrFail($id), auth('tenant')->id());

        return response()->json(['success' => true, 'message' => __('growth_hub.clients.rotated'), 'data' => ['token' => $token, 'secret' => $secret]]);
    }

    public function revoke(int $id): JsonResponse
    {
        $this->service->revoke($this->service->findOrFail($id), auth('tenant')->id());

        return $this->ok(__('growth_hub.clients.revoked_ok'));
    }
}
