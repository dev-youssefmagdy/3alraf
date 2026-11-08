<?php

namespace App\DTOs\Marketing;

/** New suggestion (API POST /suggestions/{type} or a scheduled job). Payload is raw: the type handler normalises it. */
final readonly class SuggestionInput
{
    public function __construct(
        public string $type, public string $title, public ?string $description, public ?string $reasoning, public string $priority,
        public ?string $targetType, public ?int $targetId, public array $payload, public ?array $sourceData, public ?string $expiresAt,
        public string $source, public array $sourceMeta, public ?string $forceReason, public bool $draft,
    ) {}

    public static function fromArray(string $type, array $v, string $source): self
    {
        return new self($type, trim((string) $v['title']), isset($v['description']) ? trim((string) $v['description']) : null, isset($v['reasoning']) ? trim((string) $v['reasoning']) : null,
            (string) ($v['priority'] ?? 'normal'), $v['target_entity_type'] ?? null, isset($v['target_entity_id']) ? (int) $v['target_entity_id'] : null,
            (array) ($v['payload'] ?? []), isset($v['source_data']) ? (array) $v['source_data'] : null, $v['expires_at'] ?? null,
            $source, array_filter((array) ($v['source_meta'] ?? []), fn ($x) => $x !== null && $x !== ''), $v['force_reason'] ?? null, (bool) ($v['draft'] ?? false));
    }
}
