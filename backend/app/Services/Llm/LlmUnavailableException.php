<?php

namespace App\Services\Llm;

use RuntimeException;

/**
 * FreeLLMAPI gave no usable answer on any configured model. `code` follows the Product AI error contract
 * (not_configured | gateway_down | quota_exceeded | no_vision_model | timeout); httpStatus() is what the admin endpoint answers.
 */
class LlmUnavailableException extends RuntimeException
{
    public const NOT_CONFIGURED = 'not_configured';

    public const GATEWAY_DOWN = 'gateway_down';

    public const QUOTA_EXCEEDED = 'quota_exceeded';

    public const NO_VISION_MODEL = 'no_vision_model';

    public const TIMEOUT = 'timeout';

    /** @param  array<string,string>  $failures  model => reason, for logs and llm:check */
    public function __construct(public readonly string $errorCode, string $message = '', public readonly array $failures = [], public readonly ?int $retryAfter = null)
    {
        parent::__construct($message !== '' ? $message : $errorCode);
    }

    public function httpStatus(): int
    {
        return match ($this->errorCode) {
            self::QUOTA_EXCEEDED => 429,
            self::NO_VISION_MODEL => 422,
            self::TIMEOUT => 504,
            default => 503,
        };
    }

    /** The admin-facing message for this code (admin.product_ai.*). */
    public function userMessage(): string
    {
        return __(match ($this->errorCode) {
            self::NOT_CONFIGURED => 'admin.product_ai.not_configured',
            self::QUOTA_EXCEEDED => 'admin.product_ai.error_quota',
            self::NO_VISION_MODEL => 'admin.product_ai.error_no_vision',
            self::TIMEOUT => 'admin.product_ai.error_timeout',
            default => str_starts_with($this->getMessage(), 'bad_answer') ? 'admin.product_ai.error_bad_answer' : 'admin.product_ai.error_gateway_down',
        });
    }
}
