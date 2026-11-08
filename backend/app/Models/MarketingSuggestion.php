<?php

namespace App\Models;

use App\Enums\Marketing\SuggestionPriority;
use App\Enums\Marketing\SuggestionSource;
use App\Enums\Marketing\SuggestionStatus;
use App\Enums\Marketing\SuggestionTypeKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One reviewable marketing suggestion (P5, MCP.md §16/§26). `payload` is validated + normalised by the type handler
 * (App\Services\Marketing\Suggestions\Types\*); `content_hash` = hash of (type, target, normalised payload) and is what an approval binds to,
 * together with `version`. `target_snapshot_hash` = hash of the target's relevant fields when the suggestion was made (stale detection).
 * Never written directly: App\Services\Marketing\Suggestions\SuggestionEngine is the only writer.
 */
class MarketingSuggestion extends Model
{
    protected $fillable = ['type', 'title', 'description', 'target_entity_type', 'target_entity_id', 'payload', 'source_data_snapshot', 'target_snapshot',
        'target_snapshot_hash', 'reasoning', 'priority', 'status', 'source', 'source_meta', 'created_by_token_id', 'created_by_admin_id', 'reviewed_by',
        'approved_by', 'applied_by', 'version', 'content_hash', 'result_entity_type', 'result_entity_id', 'last_error', 'submitted_at', 'reviewed_at',
        'expires_at', 'approved_at', 'applied_at', 'rejected_at', 'rolled_back_at'];

    protected function casts(): array
    {
        return ['type' => SuggestionTypeKey::class, 'status' => SuggestionStatus::class, 'priority' => SuggestionPriority::class, 'source' => SuggestionSource::class,
            'payload' => 'array', 'source_data_snapshot' => 'array', 'target_snapshot' => 'array', 'source_meta' => 'array', 'version' => 'int',
            'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'expires_at' => 'datetime', 'approved_at' => 'datetime', 'applied_at' => 'datetime',
            'rejected_at' => 'datetime', 'rolled_back_at' => 'datetime'];
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(MarketingSuggestionRevision::class, 'suggestion_id')->orderByDesc('version');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(MarketingSuggestionApproval::class, 'suggestion_id')->latest('id');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(MarketingSuggestionExecution::class, 'suggestion_id')->latest('id');
    }

    public function activity(): HasMany
    {
        return $this->hasMany(MarketingSuggestionActivityLog::class, 'suggestion_id')->latest('id');
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(MarketingApiToken::class, 'created_by_token_id');
    }

    public function creatorAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function applier(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'applied_by');
    }

    /** The approval that currently authorises an apply: approved decision, not invalidated, bound to this version + hash, not expired. */
    public function validApproval(): ?MarketingSuggestionApproval
    {
        return MarketingSuggestionApproval::query()->where('suggestion_id', $this->id)->where('decision', 'approved')->whereNull('invalidated_at')
            ->where('version', $this->version)->where('content_hash', $this->content_hash)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->latest('id')->first();
    }

    public function lastSuccessfulApply(): ?MarketingSuggestionExecution
    {
        return MarketingSuggestionExecution::query()->where('suggestion_id', $this->id)->where('kind', 'apply')->where('status', 'succeeded')->latest('id')->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
