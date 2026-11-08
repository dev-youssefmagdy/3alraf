<?php

namespace App\Enums\Marketing;

/** Review / approval status of a private social-media library item (P6, MCP.md §21). */
enum MediaAssetStatus: string
{
    case DRAFT = 'draft';
    case PENDING_REVIEW = 'pending_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return __('marketing.media.statuses.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::DRAFT => 'badge-muted',
            self::PENDING_REVIEW => 'badge-warn',
            self::APPROVED => 'badge-success',
            self::REJECTED => 'badge-danger',
            self::ARCHIVED => 'badge-muted',
        };
    }

    public static function values(): array
    {
        return array_map(fn ($c) => $c->value, self::cases());
    }
}
