<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Request log of the integration API: one row per authenticated call (client, method, path, status code, request hash). `idempotency_key` holds a
 * per-call UUID since the API became read-only (P2); no body, no response, no IP is stored. Pruned by `integration:prune`.
 */
class IntegrationRequest extends Model
{
    protected $fillable = ['integration_client_id', 'idempotency_key', 'request_hash', 'method', 'path', 'status_code', 'response'];

    protected function casts(): array
    {
        return ['response' => 'array', 'status_code' => 'int'];
    }
}
