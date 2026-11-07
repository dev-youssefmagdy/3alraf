<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrendCandidate extends Model
{

    protected $table = 'trend_candidates';

    protected $fillable = ['week', 'item_key', 'name', 'source', 'product_id', 'score', 'components', 'evidence', 'uncertainty', 'confidence', 'status', 'experiment_id'];

    protected function casts(): array
    {
        return ['components' => 'array', 'evidence' => 'array', 'uncertainty' => 'array', 'score' => 'float'];
    }
}
