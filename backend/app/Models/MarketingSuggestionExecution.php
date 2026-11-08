<?php

namespace App\Models;

use App\Enums\Marketing\SuggestionExecutionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One apply / rollback / publish / schedule run: unique idempotency key, before / after values, error. */
class MarketingSuggestionExecution extends Model
{
    protected $fillable = ['suggestion_id', 'kind', 'idempotency_key', 'status', 'version', 'content_hash', 'options', 'before', 'after', 'warnings', 'error',
        'executed_by', 'rollback_of', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['status' => SuggestionExecutionStatus::class, 'options' => 'array', 'before' => 'array', 'after' => 'array', 'warnings' => 'array',
            'started_at' => 'datetime', 'finished_at' => 'datetime', 'version' => 'int'];
    }

    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(MarketingSuggestion::class, 'suggestion_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'executed_by');
    }
}
