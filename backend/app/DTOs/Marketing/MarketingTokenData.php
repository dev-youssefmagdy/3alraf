<?php

namespace App\DTOs\Marketing;

use App\Http\Requests\Marketer\MarketingTokenRequest;

/** New Marketing API service token (marketer panel → API tokens). */
final readonly class MarketingTokenData
{
    /** @param list<string> $scopes */
    public function __construct(
        public string $name,
        public array $scopes,
        public ?int $expiresInDays,
        public ?int $createdBy,
    ) {}

    public static function fromRequest(MarketingTokenRequest $r): self
    {
        $days = $r->validated('expires_in_days');

        return new self(trim((string) $r->validated('name')), array_values(array_unique((array) $r->validated('scopes'))),
            ($days === null || $days === '' || $days === 'never') ? null : (int) $days, auth('admin')->id());
    }
}
