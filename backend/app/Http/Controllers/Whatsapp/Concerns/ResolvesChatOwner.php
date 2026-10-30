<?php

namespace App\Http\Controllers\Whatsapp\Concerns;

use App\Models\WhatsappConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The owner (tenant id or null = central) always comes from the authenticated panel, never from request input. */
trait ResolvesChatOwner
{
    protected function guardName(Request $r): string
    {
        return str_starts_with($r->route()->getName(), 'central.') ? 'central' : 'tenant';
    }

    protected function owner(Request $r): ?int
    {
        return $this->guardName($r) === 'tenant' ? current_tenant()->id : null;
    }

    protected function conversation(Request $r, int $id): WhatsappConversation
    {
        return WhatsappConversation::ownedBy($this->owner($r))->findOrFail($id);
    }

    protected function fail(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'code' => $code, 'message' => $message], $status);
    }

    /** Structural audit only (ids/types), never message content. */
    protected function audit(Request $r, string $event, $subject, array $props = []): void
    {
        $a = activity('whatsapp-chat')->performedOn($subject)->event($event)->withProperties($props);
        if ($u = $r->user($this->guardName($r))) {
            $a->causedBy($u);
        }
        $a->tap(fn ($l) => $l->tenant_id = $this->owner($r))->log($event);
    }
}
