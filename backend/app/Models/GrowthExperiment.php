<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrowthExperiment extends Model
{
    use Audited, BelongsToTenant;

    protected $fillable = ['name', 'hypothesis', 'objective', 'metric', 'starts_on', 'ends_on', 'budget_cap', 'status', 'outcome', 'learnings', 'created_by'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'budget_cap' => 'decimal:2'];
    }

    public function results(): HasMany
    {
        return $this->hasMany(GrowthExperimentResult::class)->orderByDesc('recorded_on')->orderByDesc('id');
    }
}
