<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/** Base of the mobile API: every response is {success, message, data, meta}. Payloads are always API Resources, never models. */
abstract class ApiController extends Controller
{
    protected function respond(JsonResource|array|null $data = null, ?string $message = null, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true, 'message' => $message,
            'data' => $data instanceof JsonResource ? $data->resolve(request()) : $data,
            'meta' => (object) $meta,
        ], $status);
    }

    /** @param class-string<JsonResource> $resource */
    protected function page(LengthAwarePaginator $p, string $resource, array $meta = []): JsonResponse
    {
        return $this->respond($resource::collection($p->getCollection()), null, $meta + ['pagination' => [
            'current_page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'per_page' => $p->perPage(), 'total' => $p->total(),
        ]]);
    }

    /** Collection payload (resolved to an array). */
    protected function list(JsonResource $collection, array $meta = [], ?string $message = null): JsonResponse
    {
        return $this->respond($collection, $message, $meta);
    }
}
