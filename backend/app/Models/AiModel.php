<?php

namespace App\Models;

use App\Enums\AiModelKind;
use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiModel extends Model
{
    use Audited, SoftDeletes;

    protected $fillable = ['ai_provider_id', 'model_id', 'label', 'kind', 'capabilities', 'input_cost', 'output_cost', 'max_output', 'is_active', 'is_default'];

    protected function casts(): array
    {
        return ['kind' => AiModelKind::class, 'capabilities' => 'array', 'input_cost' => 'decimal:4', 'output_cost' => 'decimal:4', 'max_output' => 'int', 'is_active' => 'bool', 'is_default' => 'bool'];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'ai_provider_id');
    }

    public function can(string $cap): bool
    {
        return (bool) (($this->capabilities ?? [])[$cap] ?? false);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->whereHas('provider', fn ($p) => $p->where('is_active', true));
    }

    public function estimateCost(int $in, int $out): float
    {
        return round($in / 1e6 * (float) $this->input_cost + $out / 1e6 * (float) $this->output_cost, 6);
    }
}
