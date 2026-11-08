<?php

namespace App\Enums\Marketing;

/** Who produced the suggestion: the MCP server (Claude), a panel user, or a scheduled job (n8n / Laravel scheduler via the API). */
enum SuggestionSource: string
{
    case MCP = 'mcp';
    case PANEL = 'panel';
    case SCHEDULED = 'scheduled';

    public function label(): string
    {
        return __('marketing.suggestions.sources.'.$this->value);
    }

    public static function values(): array
    {
        return array_map(fn ($c) => $c->value, self::cases());
    }
}
