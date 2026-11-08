<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Immutable snapshot of one suggestion version (who edited, what changed). */
class MarketingSuggestionRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['suggestion_id', 'version', 'title', 'payload', 'content_hash', 'editor_type', 'editor_id', 'diff', 'note', 'created_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'diff' => 'array', 'version' => 'int'];
    }

    public function editorName(): string
    {
        return MarketingSuggestionActivityLog::actorName($this->editor_type, $this->editor_id);
    }
}
