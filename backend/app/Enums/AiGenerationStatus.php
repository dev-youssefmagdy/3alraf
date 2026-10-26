<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum AiGenerationStatus: string
{
    use HasLabels;

    case QUEUED = 'queued';
    case DRAFT = 'draft';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case FAILED = 'failed';

    public function label(): string
    {
        return __('ai.enums.'.\Illuminate\Support\Str::snake('AiGenerationStatus').'.'.$this->value);
    }
}
