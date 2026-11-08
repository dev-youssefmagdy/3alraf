<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Approve / reject decision bound to one (version, content_hash). An edit after approval sets invalidated_at. */
class MarketingSuggestionApproval extends Model
{
    protected $fillable = ['suggestion_id', 'version', 'content_hash', 'approved_by', 'decision', 'note', 'expires_at', 'invalidated_at', 'invalidated_reason'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'invalidated_at' => 'datetime', 'version' => 'int'];
    }

    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(MarketingSuggestion::class, 'suggestion_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }
}
