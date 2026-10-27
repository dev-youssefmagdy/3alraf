<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SeasonalTask extends Model
{
    use BelongsToTenant;

    protected $table = 'seasonal_tasks';

    protected $fillable = ['seasonal_event_id', 'code', 'title', 'due_on', 'done_at'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'done_at' => 'datetime'];
    }
}
