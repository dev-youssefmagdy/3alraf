<?php

namespace App\DTOs\Marketing;

/** Edit of a suggestion (panel edit form or API PATCH). Null = field not sent (unchanged); payload is the FULL new payload when present. */
final readonly class SuggestionEditData
{
    public function __construct(public ?string $title, public ?string $description, public ?string $reasoning, public ?string $priority,
        public ?array $payload, public ?string $expiresAt, public bool $expiresAtSent, public ?string $note) {}

    public static function fromArray(array $v): self
    {
        return new self(isset($v['title']) ? trim((string) $v['title']) : null, array_key_exists('description', $v) ? trim((string) $v['description']) : null,
            array_key_exists('reasoning', $v) ? trim((string) $v['reasoning']) : null, $v['priority'] ?? null, isset($v['payload']) ? (array) $v['payload'] : null,
            $v['expires_at'] ?? null, array_key_exists('expires_at', $v), $v['note'] ?? null);
    }
}
