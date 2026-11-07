<?php

namespace App\Http\Controllers\Whatsapp\Concerns;

use App\Models\WhatsappConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Chat helpers of the admin panel (guard `admin`, single WhatsApp session). */
trait ResolvesChatOwner
{
    protected function guardName(Request $r): string
    {
        return 'admin';
    }

    protected function conversation(Request $r, int $id): WhatsappConversation
    {
        return WhatsappConversation::query()->findOrFail($id);
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
        $a->log($event);
    }
}
