<?php

namespace App\Enums\Marketing;

/** Suggestion lifecycle (MCP.md §16.3). Allowed moves: App\Services\Marketing\Suggestions\SuggestionStateMachine. */
enum SuggestionStatus: string
{
    case PENDING = 'pending';
    case UNDER_REVIEW = 'under_review';
    case EDITED = 'edited';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case APPLYING = 'applying';
    case APPLIED = 'applied';
    case FAILED = 'failed';
    case EXPIRED = 'expired';
    case ARCHIVED = 'archived';

    /** Statuses a person can still edit in the marketer panel (an edit after approval cancels the approval). */
    public const PANEL_EDITABLE = [self::PENDING, self::UNDER_REVIEW, self::EDITED, self::APPROVED, self::FAILED];

    public function label(): string
    {
        return __('marketing.suggestions.statuses.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::PENDING, self::UNDER_REVIEW, self::EDITED => 'badge-warn',
            self::APPROVED, self::APPLIED => 'badge-success',
            self::REJECTED, self::FAILED => 'badge-danger',
            default => 'badge-muted',
        };
    }

    /** Still "alive": counts for duplicate prevention and can still change. */
    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }

    /** @return list<self> */
    public static function open(): array
    {
        return [self::PENDING, self::UNDER_REVIEW, self::EDITED, self::APPROVED, self::APPLYING, self::FAILED];
    }

    public static function values(): array
    {
        return array_map(fn ($c) => $c->value, self::cases());
    }
}
