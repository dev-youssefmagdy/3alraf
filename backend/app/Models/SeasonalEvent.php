<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SeasonalEvent extends Model
{
    use BelongsToTenant;

    protected $table = 'seasonal_events';

    protected $fillable = ['key', 'year', 'name_ar', 'name_en', 'starts_on', 'ends_on', 'hijri', 'category_ids', 'lead_time_days', 'notes', 'status'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'hijri' => 'bool', 'category_ids' => 'array'];
    }

    public function tasks()
    {
        return $this->hasMany(SeasonalTask::class)->orderBy('due_on')->orderBy('id');
    }

    public function memory()
    {
        return $this->hasOne(SeasonalMemory::class);
    }

    public function name(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }
}
