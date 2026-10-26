<?php

namespace App\Exceptions;

use RuntimeException;

/** Always safe to show to the user: never carries provider bodies, keys or stack details. */
class AiException extends RuntimeException
{
    public function __construct(string $userMessage, public readonly string $reason = 'error', ?\Throwable $previous = null)
    {
        parent::__construct($userMessage, 0, $previous);
    }

    public static function quota(string $what = 'tokens'): self
    {
        return new self(__('ai.errors.quota'), 'quota_'.$what);
    }

    public static function disabled(): self
    {
        return new self(__('ai.errors.disabled'), 'disabled');
    }

    public static function throttled(): self
    {
        return new self(__('ai.errors.throttled'), 'throttled');
    }

    public static function unsupported(): self
    {
        return new self(__('ai.errors.unsupported'), 'unsupported');
    }

    public static function provider(?\Throwable $e = null): self
    {
        return new self(__('ai.errors.provider'), 'provider', $e);
    }
}
