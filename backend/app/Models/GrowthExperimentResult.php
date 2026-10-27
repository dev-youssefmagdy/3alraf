<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class GrowthExperimentResult extends Model
{
    use BelongsToTenant;

    protected $fillable = ['growth_experiment_id', 'recorded_on', 'metric', 'value', 'spend', 'note', 'source'];

    protected function casts(): array
    {
        return ['recorded_on' => 'date', 'value' => 'decimal:4', 'spend' => 'decimal:2'];
    }
}
