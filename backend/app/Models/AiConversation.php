<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConversation extends Model
{

    protected $fillable = ['user_id', 'title', 'ai_model_id', 'summary', 'summarized_until_id'];

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class);
    }
}
