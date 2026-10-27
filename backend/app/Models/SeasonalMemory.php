<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SeasonalMemory extends Model
{
    use BelongsToTenant;

    protected $table = 'seasonal_memories';

    protected $fillable = ['seasonal_event_id', 'performance', 'content_formats', 'start_timing', 'objections', 'lessons'];

    protected function casts(): array
    {
        return ['performance' => 'array'];
    }

    public function event()
    {
        return $this->belongsTo(SeasonalEvent::class, 'seasonal_event_id');
    }
}
