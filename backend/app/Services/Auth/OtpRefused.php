<?php

namespace App\Services\Auth;

class OtpRefused extends \RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $retryAfter)
    {
        parent::__construct($reason);
    }
}
