<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Outbox row: one business event to deliver (signed) to the tenant's n8n webhooks. */
class GrowthEvent extends Model
{
    use BelongsToTenant;

    protected $fillable = ['event_id', 'type', 'payload', 'status', 'attempts', 'next_attempt_at', 'delivered_to', 'last_error', 'delivered_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'delivered_to' => 'array', 'next_attempt_at' => 'datetime', 'delivered_at' => 'datetime', 'attempts' => 'int'];
    }
}
