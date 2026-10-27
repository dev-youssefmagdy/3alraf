<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MissedOpportunity extends Model
{
    use BelongsToTenant;

    protected $table = 'missed_opportunities';

    protected $fillable = ['rule', 'dedupe_key', 'product_id', 'title', 'evidence', 'probable_cause', 'confidence', 'suggestion', 'owner', 'due_on', 'status', 'result_note', 'experiment_id'];

    protected function casts(): array
    {
        return ['evidence' => 'array', 'due_on' => 'date'];
    }
}
