<?php

namespace App\Http\Controllers\Marketer;

use App\DataTables\IntegrationClientDataTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IntegrationClientRequest;
use App\Services\Integration\IntegrationClientService;
use Illuminate\Http\JsonResponse;

/** Credentials of the n8n integration API (perms integration_clients.view / .manage on the routes). Marketer panel (P4); old /admin URLs 301 here. */
class IntegrationClientController extends Controller
{
    public function __construct(private IntegrationClientService $service) {}

    public function index(IntegrationClientDataTable $table)
    {
        return view('marketer.integration-clients.index', ['table' => $table, 'scopes' => config('integration.scopes'), 'base' => url('/api/integration/v1')]);
    }

    public function datatable(IntegrationClientDataTable $table)
    {
        return $table->ajax();
    }

    /** Token + HMAC secret are returned once, here, and never again. */
    public function store(IntegrationClientRequest $r): JsonResponse
    {
        [$client, $token, $secret] = $this->service->create($r->validated('name'), $r->validated('scopes'));

        return response()->json(['success' => true, 'message' => __('admin.integration.created'), 'data' => ['id' => $client->id, 'token' => $token, 'secret' => $secret]], 201);
    }

    /** Name and scopes only; token / secret are untouched. */
    public function update(IntegrationClientRequest $r, int $id): JsonResponse
    {
        $this->service->update($this->service->findOrFail($id), $r->validated('name'), $r->validated('scopes'));

        return $this->ok(__('admin.integration.updated'), route('marketer.integration-clients.index'));
    }

    public function rotate(int $id): JsonResponse
    {
        [$token, $secret] = $this->service->rotate($this->service->findOrFail($id));

        return response()->json(['success' => true, 'message' => __('admin.integration.rotated'), 'data' => ['token' => $token, 'secret' => $secret]]);
    }

    public function revoke(int $id): JsonResponse
    {
        $this->service->revoke($this->service->findOrFail($id));

        return $this->ok(__('admin.integration.revoked_ok'));
    }
}
