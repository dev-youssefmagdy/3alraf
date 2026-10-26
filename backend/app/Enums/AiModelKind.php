<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum AiModelKind: string
{
    use HasLabels;

    case TEXT = 'text';
    case IMAGE = 'image';
    case VIDEO = 'video';

    public function label(): string
    {
        return __('ai.enums.'.\Illuminate\Support\Str::snake('AiModelKind').'.'.$this->value);
    }
}
