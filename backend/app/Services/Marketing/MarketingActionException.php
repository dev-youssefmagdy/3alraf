<?php

namespace App\Services\Marketing;

use RuntimeException;

/**
 * Business refusal of the P6 services (media library, Meta drafts / publishing). `errorCode` is stable (API `error`); the message comes from
 * lang `marketing.{group}.errors.{code}`. Rendered as JSON by the marketer controllers and as the MarketingResponse envelope by the API.
 */
class MarketingActionException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly int $status = 422, public readonly array $data = [], public readonly array $errors = [])
    {
        parent::__construct($message);
    }

    public static function make(string $group, string $code, int $status = 422, array $data = [], array $replace = []): self
    {
        return new self($code, __("marketing.{$group}.errors.{$code}", $replace), $status, $data);
    }

    public static function validation(array $errors): self
    {
        $first = collect($errors)->flatten()->first();

        return new self('validation_failed', (string) ($first ?: __('marketing.api.validation_failed')), 422, [], array_map(fn ($v) => (array) $v, $errors));
    }
}
