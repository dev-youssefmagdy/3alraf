<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Stored result of an inbound call keyed by (client, Idempotency-Key): replays return the same response. */
class IntegrationRequest extends Model
{
    use BelongsToTenant;

    protected $fillable = ['integration_client_id', 'idempotency_key', 'request_hash', 'method', 'path', 'status_code', 'response'];

    protected function casts(): array
    {
        return ['response' => 'array', 'status_code' => 'int'];
    }
}
