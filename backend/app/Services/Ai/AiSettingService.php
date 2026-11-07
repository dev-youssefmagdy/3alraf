<?php

namespace App\Services\Ai;

use App\DTOs\Ai\AiPlatformData;
use App\DTOs\Ai\AiPreferencesData;
use App\Models\AiSetting;
use App\Repositories\Contracts\AiSettingRepositoryInterface;

/** AI settings page: shop preferences + platform card (quotas / allowed models / features). The master switch lives on Settings → General. */
class AiSettingService
{
    public function __construct(private AiSettingRepositoryInterface $repo, private AiAccess $access) {}

    public function get(): AiSetting
    {
        return $this->repo->current();
    }

    /** Quotas / allowed models / features (permission ai_platform.edit). */
    public function updatePlatform(AiPlatformData $d): AiSetting
    {
        $row = $this->repo->save(['monthly_token_quota' => $d->tokenQuota, 'monthly_image_quota' => $d->imageQuota,
            'monthly_video_quota' => $d->videoQuota, 'allowed_model_ids' => $d->allowedModelIds, 'features' => $d->features]);
        // a default model that is no longer allowed is cleared
        if ($row->default_ai_model_id && ! $row->modelAllowed($row->default_ai_model_id)) {
            $row->update(['default_ai_model_id' => null]);
        }
        $this->access->forget();

        return $row;
    }

    /** Shop preferences only (platform columns untouched). */
    public function updatePreferences(AiPreferencesData $d): AiSetting
    {
        $row = $this->repo->save(['default_ai_model_id' => $d->defaultModelId, 'brand_voice' => $d->brandVoice, 'forbidden_words' => $d->forbiddenWords, 'default_platforms' => $d->defaultPlatforms]);
        $this->access->forget();

        return $row;
    }
}
