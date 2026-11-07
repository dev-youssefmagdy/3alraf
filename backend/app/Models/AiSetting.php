<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;

/** The store's AI settings (single row): platform part (enable, quotas, allowed models, features) + preferences (default model, brand voice, forbidden words). */
class AiSetting extends Model
{
    use Audited;

    protected $fillable = ['ai_enabled', 'monthly_token_quota', 'monthly_image_quota', 'monthly_video_quota', 'allowed_model_ids', 'features',
        'default_ai_model_id', 'brand_voice', 'forbidden_words', 'default_platforms'];

    protected function casts(): array
    {
        return ['ai_enabled' => 'bool', 'allowed_model_ids' => 'array', 'features' => 'array', 'forbidden_words' => 'array', 'default_platforms' => 'array',
            'monthly_token_quota' => 'int', 'monthly_image_quota' => 'int', 'monthly_video_quota' => 'int'];
    }

    public function featureOn(string $feature): bool
    {
        return $this->ai_enabled && (bool) (($this->features ?? [])[$feature] ?? false);
    }

    public function modelAllowed(int $modelId): bool
    {
        return $this->allowed_model_ids === null || in_array($modelId, array_map('intval', $this->allowed_model_ids), true);
    }
}
