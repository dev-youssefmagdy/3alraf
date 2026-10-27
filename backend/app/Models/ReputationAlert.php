<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ReputationAlert extends Model
{
    use BelongsToTenant;

    protected $table = 'reputation_alerts';

    protected $fillable = ['product_id', 'topic', 'rate', 'baseline_rate', 'z_score', 'sample', 'pause_auto_reply', 'status', 'examples', 'detected_on', 'resolved_at'];

    protected function casts(): array
    {
        return ['examples' => 'array', 'pause_auto_reply' => 'bool', 'detected_on' => 'date', 'resolved_at' => 'datetime'];
    }
}
