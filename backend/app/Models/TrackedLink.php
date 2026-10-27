<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TrackedLink extends Model
{
    use BelongsToTenant;

    protected $table = 'tracked_links';

    protected $fillable = ['code', 'channel', 'interaction_id', 'product_id', 'utm_source', 'utm_campaign', 'clicks', 'growth_experiment_id', 'ambassador_id'];

    protected function casts(): array
    {
        return [];
    }
}
