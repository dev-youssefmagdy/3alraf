<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GrowthApproval extends Model
{

    public const PENDING = 'pending', APPROVED = 'approved', REJECTED = 'rejected', EXPIRED = 'expired';

    protected $fillable = ['type', 'subject_type', 'subject_id', 'title', 'payload', 'status', 'requested_by', 'decided_by', 'reason', 'result', 'expires_at', 'decided_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'result' => 'array', 'expires_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }
}
