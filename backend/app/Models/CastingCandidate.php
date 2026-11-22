<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CastingCandidate extends Model
{
    protected $fillable = ['casting_session_id', 'name', 'gender', 'age_range', 'appearance', 'hairstyle', 'clothing', 'personality', 'ad_role', 'suggested_poses',
        'suggested_locations', 'rationale', 'prompt', 'seed', 'status', 'attempts', 'error', 'disk', 'portrait_path', 'job_token', 'virtual_model_id', 'generation_meta'];

    protected $hidden = ['disk', 'portrait_path', 'job_token'];

    protected function casts(): array
    {
        return ['suggested_poses' => 'array', 'suggested_locations' => 'array', 'generation_meta' => 'array', 'seed' => 'int', 'attempts' => 'int'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CastingSession::class, 'casting_session_id');
    }

    public function virtualModel(): BelongsTo
    {
        return $this->belongsTo(VirtualModel::class);
    }

    public function isBusy(): bool
    {
        return in_array($this->status, ['queued', 'generating'], true);
    }
}
