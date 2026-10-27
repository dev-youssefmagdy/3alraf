<?php

namespace App\Services\Growth;

use App\Models\GrowthActionLog;
use Illuminate\Database\Eloquent\Model;

/** Single writer of growth_action_log (who / what / why / source / outcome). PII is scrubbed from meta. */
class ActionLogger
{
    public function log(string $action, string $source, ?int $actorId = null, ?string $reason = null, ?Model $subject = null, string $outcome = 'ok', array $meta = []): GrowthActionLog
    {
        return GrowthActionLog::create([
            'action' => $action, 'source' => $source, 'actor_id' => $actorId, 'reason' => $reason !== null ? mb_substr($reason, 0, 500) : null, 'outcome' => $outcome,
            'subject_type' => $subject?->getMorphClass(), 'subject_id' => $subject?->getKey(), 'meta' => $meta ? PiiScrubber::scrub($meta) : null, 'created_at' => now(),
        ]);
    }
}
