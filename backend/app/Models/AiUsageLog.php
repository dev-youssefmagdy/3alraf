<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = ['user_type', 'user_id', 'feature', 'ai_model_id', 'tokens_in', 'tokens_out', 'images', 'video_secs', 'est_cost', 'latency_ms', 'status', 'country', 'error'];

    protected function casts(): array
    {
        return ['est_cost' => 'decimal:6', 'tokens_in' => 'int', 'tokens_out' => 'int', 'images' => 'int', 'video_secs' => 'int'];
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }
}
