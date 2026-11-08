<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** MCP.md §8 approval record of a Meta draft: bound to (draft_version, content_hash), expires, carries the publish execution result. */
class MetaCampaignApproval extends Model
{
    protected $fillable = ['draft_id', 'draft_version', 'content_hash', 'requested_by', 'requested_by_token_id', 'approved_by', 'approval_status', 'approved_at',
        'expires_at', 'note', 'execution_status', 'idempotency_key', 'external_reference', 'execution_error', 'invalidated_at', 'invalidated_reason'];

    protected function casts(): array
    {
        return ['draft_version' => 'int', 'approved_at' => 'datetime', 'expires_at' => 'datetime', 'invalidated_at' => 'datetime', 'external_reference' => 'array'];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(MetaCampaignDraft::class, 'draft_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }
}
