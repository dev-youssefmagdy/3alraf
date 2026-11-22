<?php

namespace App\Services\Casting\Exceptions;

class CastingProviderException extends \RuntimeException
{
    /** $retryable false = configuration / capability problem: retrying cannot help. */
    public function __construct(string $message, public readonly bool $retryable = true, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function unsupported(string $what): self
    {
        return new self(__('casting.errors.unsupported', ['what' => $what]), false);
    }
}
