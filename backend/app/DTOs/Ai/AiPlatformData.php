<?php

namespace App\DTOs\Ai;

use App\Http\Requests\Central\Ai\AiTenantRequest;

/** Platform-owned per-tenant AI switches. */
final readonly class AiTenantAiData
{
    public function __construct(public bool $enabled, public ?int $tokenQuota, public ?int $imageQuota, public ?int $videoQuota, public ?array $allowedModelIds, public array $features) {}

    public static function fromRequest(AiTenantRequest $r): self
    {
        $v = $r->validated();
        $int = fn ($k) => filled($v[$k] ?? null) ? (int) $v[$k] : null;
        $ids = array_values(array_map('intval', (array) ($v['allowed_model_ids'] ?? [])));
        $features = [];
        foreach (\App\Enums\AiFeature::values() as $f) {
            $features[$f] = in_array($f, (array) ($v['features'] ?? []), true);
        }

        return new self($r->boolean('ai_enabled'), $int('monthly_token_quota'), $int('monthly_image_quota'), $int('monthly_video_quota'), $ids ?: null, $features);
    }
}
