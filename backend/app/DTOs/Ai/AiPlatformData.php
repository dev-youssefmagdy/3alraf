<?php

namespace App\DTOs\Ai;

use App\Enums\AiFeature;
use App\Http\Requests\Admin\Ai\AiPlatformRequest;

/** AI settings → platform card: monthly quotas, allowed models, feature toggles. */
final readonly class AiPlatformData
{
    public function __construct(public ?int $tokenQuota, public ?int $imageQuota, public ?int $videoQuota, public ?array $allowedModelIds, public array $features) {}

    public static function fromRequest(AiPlatformRequest $r): self
    {
        $v = $r->validated();
        $int = fn ($k) => filled($v[$k] ?? null) ? (int) $v[$k] : null;
        $ids = array_values(array_map('intval', (array) ($v['allowed_model_ids'] ?? [])));
        $features = [];
        foreach (AiFeature::values() as $f) {
            $features[$f] = in_array($f, (array) ($v['features'] ?? []), true);
        }

        return new self($int('monthly_token_quota'), $int('monthly_image_quota'), $int('monthly_video_quota'), $ids ?: null, $features);
    }
}
