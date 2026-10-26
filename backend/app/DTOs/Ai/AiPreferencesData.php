<?php

namespace App\DTOs\Ai;

use App\Http\Requests\Tenant\AiSettingsRequest;

/** Tenant-owned AI preferences. */
final readonly class AiPreferencesData
{
    public function __construct(public ?int $defaultModelId, public ?string $brandVoice, public array $forbiddenWords, public array $defaultPlatforms) {}

    public static function fromRequest(AiSettingsRequest $r): self
    {
        $v = $r->validated();
        $words = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,\n،]+/u', (string) ($v['forbidden_words'] ?? '')) ?: []))));

        return new self(filled($v['default_ai_model_id'] ?? null) ? (int) $v['default_ai_model_id'] : null, filled($v['brand_voice'] ?? null) ? $v['brand_voice'] : null, $words,
            array_values(array_filter((array) ($v['default_platforms'] ?? []))));
    }
}
