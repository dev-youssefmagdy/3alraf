<?php

namespace App\Services\Marketing\Suggestions;

use App\Enums\Marketing\SuggestionStatus as S;

/**
 * The only allowed status moves (MCP.md §16.3). Anything else throws illegal_transition (409).
 * Rollback of an applied suggestion = applied → archived (+ rolled_back_at); a failed apply can be retried (failed → applying) with a valid approval.
 */
final class SuggestionStateMachine
{
    /** @var array<string, list<string>> */
    public const MAP = [
        'pending' => ['under_review', 'edited', 'approved', 'rejected', 'expired', 'archived'],
        'under_review' => ['edited', 'approved', 'rejected', 'expired', 'archived'],
        'edited' => ['edited', 'under_review', 'approved', 'rejected', 'expired', 'archived'],
        'approved' => ['applying', 'edited', 'rejected', 'expired', 'archived'],
        'applying' => ['applied', 'failed'],
        'applied' => ['archived'],
        'failed' => ['applying', 'edited', 'rejected', 'archived'],
        'rejected' => ['archived'],
        'expired' => ['archived'],
        'archived' => [],
    ];

    public static function can(S $from, S $to): bool
    {
        return in_array($to->value, self::MAP[$from->value], true);
    }

    public static function assert(S $from, S $to): void
    {
        if (! self::can($from, $to)) {
            throw SuggestionException::make('illegal_transition', 409, ['from' => $from->value, 'to' => $to->value], ['from' => $from->label(), 'to' => $to->label()]);
        }
    }

    /** @return list<S> */
    public static function next(S $from): array
    {
        return array_map(fn ($v) => S::from($v), self::MAP[$from->value]);
    }
}
