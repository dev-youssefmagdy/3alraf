<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Stored result of an inbound call keyed by (client, Idempotency-Key): replays return the same response. */
class IntegrationRequest extends Model
{

    protected $fillable = ['integration_client_id', 'idempotency_key', 'request_hash', 'method', 'path', 'status_code', 'response'];

    protected function casts(): array
    {
        return ['response' => 'array', 'status_code' => 'int'];
    }
}
