<?php

namespace App\Services\Ai;

use App\DTOs\Ai\AiPreferencesData;
use App\DTOs\Ai\AiTenantAiData;
use App\Models\AiTenantSetting;
use App\Models\Tenant;
use App\Repositories\Contracts\AiTenantSettingRepositoryInterface;

class AiTenantSettingService
{
    public function __construct(private AiTenantSettingRepositoryInterface $repo, private AiAccess $access) {}

    public function get(Tenant $t): AiTenantSetting
    {
        return $this->repo->forTenant($t->id);
    }

    /** Central: enable / quotas / allowed models / features. */
    public function updatePlatform(Tenant $t, AiTenantAiData $d): AiTenantSetting
    {
        $row = $this->repo->save($t->id, ['ai_enabled' => $d->enabled, 'monthly_token_quota' => $d->tokenQuota, 'monthly_image_quota' => $d->imageQuota,
            'monthly_video_quota' => $d->videoQuota, 'allowed_model_ids' => $d->allowedModelIds, 'features' => $d->features]);
        // a default model that is no longer allowed is cleared
        if ($row->default_ai_model_id && ! $row->modelAllowed($row->default_ai_model_id)) {
            $row->update(['default_ai_model_id' => null]);
        }
        $this->access->forget();

        return $row;
    }

    /** Tenant: own preferences only (platform columns untouched). */
    public function updatePreferences(Tenant $t, AiPreferencesData $d): AiTenantSetting
    {
        $row = $this->repo->save($t->id, ['default_ai_model_id' => $d->defaultModelId, 'brand_voice' => $d->brandVoice, 'forbidden_words' => $d->forbiddenWords, 'default_platforms' => $d->defaultPlatforms]);
        $this->access->forget();

        return $row;
    }
}
