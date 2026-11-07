<?php

namespace App\Models\Erp;

use Illuminate\Database\Eloquent\Model;

class BackfillRun extends Model
{

    protected $table = 'erp_backfill_runs';

    protected $fillable = ['from_date', 'started_at', 'finished_at', 'status', 'dry_run', 'counts', 'options', 'triggered_by', 'user_id'];

    protected function casts(): array
    {
        return ['from_date' => 'date', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'counts' => 'array', 'options' => 'array', 'dry_run' => 'bool'];
    }
}
