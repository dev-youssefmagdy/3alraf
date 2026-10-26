<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum AiDriverType: string
{
    use HasLabels;

    case ANTHROPIC = 'anthropic';
    case OPENAI = 'openai';
    case MOONSHOT = 'moonshot';
    case OPENROUTER = 'openrouter';
    case CUSTOM_OPENAI = 'custom_openai';

    public function label(): string
    {
        return __('ai.enums.'.\Illuminate\Support\Str::snake('AiDriverType').'.'.$this->value);
    }
}
