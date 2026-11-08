<?php

namespace App\Services\Marketing\Meta;

use App\Models\MetaAuditLog;
use App\Services\Marketing\Suggestions\SuggestionActor;

/**
 * Writes meta_audit_logs (MCP.md §8): draft creation / modification, approval requests / decisions, every Graph API call, publish results,
 * failures — with the acting admin / token. Only the Graph path is stored (never query strings, tokens or appsecret_proof).
 * The acting user is set once per request (actingAs) so the low-level client can attribute its calls.
 */
class MetaAudit
{
    private ?SuggestionActor $actor = null;

    public function actingAs(?SuggestionActor $actor): void
    {
        $this->actor = $actor;
    }

    public function log(?int $draftId, string $action, ?SuggestionActor $actor = null, array $meta = [], ?string $endpoint = null, ?int $status = null): void
    {
        $a = $actor ?? $this->actor ?? SuggestionActor::system();
        try {
            MetaAuditLog::create(['draft_id' => $draftId, 'action' => mb_substr($action, 0, 40), 'actor_type' => $a->type, 'actor_id' => $a->id,
                'endpoint' => $endpoint ? mb_substr($endpoint, 0, 200) : null, 'http_status' => $status,
                'meta' => array_filter($meta, fn ($v) => $v !== null && $v !== []) ?: null, 'created_at' => now()]);
        } catch (\Throwable $e) {
            report($e);   // auditing never breaks the flow
        }
    }
}
