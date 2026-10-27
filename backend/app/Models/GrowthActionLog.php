<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Append-only: who did what and why (source admin|n8n|system), with outcome. Used by every growth module. */
class GrowthActionLog extends Model
{
    use BelongsToTenant;

    public $timestamps = false;
    protected $table = 'growth_action_log';
    protected $fillable = ['action', 'source', 'actor_id', 'reason', 'outcome', 'subject_type', 'subject_id', 'meta', 'created_at'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
