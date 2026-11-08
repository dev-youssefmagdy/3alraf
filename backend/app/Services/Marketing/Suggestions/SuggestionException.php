<?php

namespace App\Services\Marketing\Suggestions;

use RuntimeException;

/**
 * Business refusal of the suggestion engine. `errorCode` is stable (API `error`, docs/marketing/APPROVAL_AND_EXECUTION_ENGINE.md):
 * illegal_transition, duplicate_suggestion, recently_rejected, validation_failed, target_not_found, not_submitted, approval_required,
 * expired, stale_target, not_applicable, rollback_not_supported, forbidden, not_owner, apply_failed.
 */
class SuggestionException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly int $status = 422, public readonly array $data = [], public readonly array $errors = [])
    {
        parent::__construct($message);
    }

    public static function make(string $code, int $status = 422, array $data = [], array $replace = [], array $errors = []): self
    {
        return new self($code, __('marketing.suggestions.errors.'.$code, $replace), $status, $data, $errors);
    }

    public static function validation(array $errors): self
    {
        $first = collect($errors)->flatten()->first();

        return new self('validation_failed', (string) ($first ?: __('marketing.api.validation_failed')), 422, [], array_map(fn ($v) => (array) $v, $errors));
    }
}
