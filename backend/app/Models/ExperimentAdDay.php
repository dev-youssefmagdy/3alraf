<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ExperimentAdDay extends Model
{
    use BelongsToTenant;

    protected $table = 'experiment_ad_days';

    protected $fillable = ['growth_experiment_id', 'day', 'creative_key', 'platform', 'platform_campaign_id', 'platform_ad_id', 'spend', 'impressions', 'clicks'];

    protected function casts(): array
    {
        return ['day' => 'date', 'spend' => 'decimal:2'];
    }
}
