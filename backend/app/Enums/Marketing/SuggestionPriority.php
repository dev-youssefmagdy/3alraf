<?php

namespace App\Enums\Marketing;

enum SuggestionPriority: string
{
    case LOW = 'low';
    case NORMAL = 'normal';
    case HIGH = 'high';
    case URGENT = 'urgent';

    public function label(): string
    {
        return __('marketing.suggestions.priorities.'.$this->value);
    }

    public function rank(): int
    {
        return ['low' => 1, 'normal' => 2, 'high' => 3, 'urgent' => 4][$this->value];
    }

    public static function values(): array
    {
        return array_map(fn ($c) => $c->value, self::cases());
    }
}
