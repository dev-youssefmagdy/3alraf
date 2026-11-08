<?php

namespace App\Enums\Marketing;

/**
 * Meta campaign draft lifecycle (P6, MCP.md §7/§8): draft → pending_approval → approved → publishing → published (all objects PAUSED in Meta).
 * rejected / failed go back to draft on edit. Any edit invalidates approvals and returns the draft to `draft`.
 */
enum MetaDraftStatus: string
{
    case DRAFT = 'draft';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case PUBLISHING = 'publishing';
    case PUBLISHED = 'published';
    case FAILED = 'failed';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return __('marketing.meta.statuses.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::DRAFT, self::ARCHIVED => 'badge-muted',
            self::PENDING_APPROVAL, self::PUBLISHING => 'badge-warn',
            self::APPROVED, self::PUBLISHED => 'badge-success',
            self::REJECTED, self::FAILED => 'badge-danger',
        };
    }

    /** Content can still be edited (nothing created in Meta yet is checked separately). */
    public function editable(): bool
    {
        return in_array($this, [self::DRAFT, self::PENDING_APPROVAL, self::APPROVED, self::REJECTED, self::FAILED], true);
    }

    public static function values(): array
    {
        return array_map(fn ($c) => $c->value, self::cases());
    }
}
