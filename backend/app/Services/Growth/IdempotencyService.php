<?php

namespace App\Services\Growth;

use App\Models\IntegrationClient;
use App\Models\IntegrationRequest;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Inbound idempotency: same (client, Idempotency-Key) replays the stored response; same key + different body = 422; concurrent duplicate = 409. */
class IdempotencyService
{
    /** @param Closure():array{0:int,1:array} $work returns [status, body] */
    public function run(IntegrationClient $client, Request $request, Closure $work): JsonResponse
    {
        $key = trim((string) $request->header('Idempotency-Key'));
        if ($key === '' || mb_strlen($key) > 120) {
            return response()->json(['success' => false, 'message' => __('growth_hub.api.idempotency_required')], 422);
        }
        $hash = hash('sha256', $request->method().'|'.$request->path().'|'.$request->getContent());

        try {
            $row = IntegrationRequest::withoutGlobalScopes()->create([
                'tenant_id' => $client->tenant_id, 'integration_client_id' => $client->id, 'idempotency_key' => $key, 'request_hash' => $hash,
                'method' => $request->method(), 'path' => mb_substr($request->path(), 0, 160),
            ]);
        } catch (UniqueConstraintViolationException) {
            $row = IntegrationRequest::withoutGlobalScopes()->where('integration_client_id', $client->id)->where('idempotency_key', $key)->firstOrFail();
            if (! hash_equals($row->request_hash, $hash)) {
                return response()->json(['success' => false, 'message' => __('growth_hub.api.idempotency_mismatch')], 422);
            }
            if ($row->status_code === null) {
                return response()->json(['success' => false, 'message' => __('growth_hub.api.in_flight')], 409);
            }

            return response()->json($row->response, $row->status_code)->header('Idempotent-Replay', 'true');
        }

        try {
            [$status, $body] = $work();
        } catch (\Throwable $e) {
            $row->delete(); // let the caller retry a failed attempt with the same key
            throw $e;
        }
        $row->update(['status_code' => $status, 'response' => $body]);

        return response()->json($body, $status);
    }

    public function prune(): int
    {
        return IntegrationRequest::withoutGlobalScopes()->where('created_at', '<', now()->subDays((int) config('growth.idempotency_ttl_days')))->delete();
    }
}
