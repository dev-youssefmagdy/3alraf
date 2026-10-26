<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum AiFeature: string
{
    use HasLabels;

    case CHAT = 'chat';
    case TRENDS = 'trends';
    case STUDIO = 'studio';
    case EDITOR = 'editor';
    case GENERATORS = 'generators';

    public function label(): string
    {
        return __('ai.enums.'.\Illuminate\Support\Str::snake('AiFeature').'.'.$this->value);
    }
}
