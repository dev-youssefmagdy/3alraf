<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum AiModelKind: string
{
    use HasLabels;

    case TEXT = 'text';
    case IMAGE = 'image';
    case VIDEO = 'video';
    // catalog-only kinds (no built-in feature consumes them yet)
    case AUDIO = 'audio';
    case EMBEDDING = 'embedding';
    case RERANK = 'rerank';
    case OTHER = 'other';

    public function label(): string
    {
        return __('ai.enums.'.\Illuminate\Support\Str::snake('AiModelKind').'.'.$this->value);
    }
}
