<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeasonalTask extends Model
{

    protected $table = 'seasonal_tasks';

    protected $fillable = ['seasonal_event_id', 'code', 'title', 'due_on', 'done_at'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'done_at' => 'datetime'];
    }
}
