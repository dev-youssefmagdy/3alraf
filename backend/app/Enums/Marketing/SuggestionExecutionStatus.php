<?php

namespace App\Enums\Marketing;

enum SuggestionExecutionStatus: string
{
    case RUNNING = 'running';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';

    public function label(): string
    {
        return __('marketing.suggestions.exec_statuses.'.$this->value);
    }
}
