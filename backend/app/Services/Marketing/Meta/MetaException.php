<?php

namespace App\Services\Marketing\Meta;

use RuntimeException;

/** Meta API / policy refusal. errorCode: not_configured, unsafe_request, connection_failed, meta_error. Message is already scrubbed of secrets. */
class MetaException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly int $status = 422, public readonly array $data = [])
    {
        parent::__construct($message);
    }
}
