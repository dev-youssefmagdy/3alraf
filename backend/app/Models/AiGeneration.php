<?php

namespace App\Models;

use App\Enums\AiGenerationStatus;
use App\Enums\AiGenerationType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** AI output is always a draft here; approval goes through the real Service (docs/plans/ai-features.md principle 4). */
class AiGeneration extends Model
{
    use BelongsToTenant;

    protected $fillable = ['user_id', 'type', 'status', 'inputs', 'output', 'media', 'ai_model_id', 'target_type', 'target_id', 'reviewed_by', 'reviewed_at', 'reject_reason', 'error'];

    protected function casts(): array
    {
        return ['type' => AiGenerationType::class, 'status' => AiGenerationStatus::class, 'inputs' => 'array', 'output' => 'array', 'media' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function aiModel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
