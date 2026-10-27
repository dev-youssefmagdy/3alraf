<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** What the owner decided about a brief recommendation (learning loop). One row per recommendation key. */
class GrowthBriefDecision extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['accepted', 'dismissed', 'done'];

    protected $fillable = ['recommendation_key', 'kind', 'status', 'owner', 'note', 'decided_by', 'experiment_id', 'outcome_metric', 'metric_before', 'metric_after', 'evidence'];

    protected function casts(): array
    {
        return ['evidence' => 'array', 'metric_before' => 'decimal:2', 'metric_after' => 'decimal:2'];
    }
}
