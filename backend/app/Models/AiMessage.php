<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{

    protected $fillable = ['ai_conversation_id', 'role', 'content', 'tool_calls', 'tokens_in', 'tokens_out', 'status'];

    protected function casts(): array
    {
        return ['tool_calls' => 'array'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }
}
