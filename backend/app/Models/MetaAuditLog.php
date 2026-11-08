<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Audit trail of Meta drafts, approvals and Graph API calls (P6, MCP.md §8). Never contains tokens or query strings. */
class MetaAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['draft_id', 'action', 'actor_type', 'actor_id', 'endpoint', 'http_status', 'meta', 'created_at'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }
}
