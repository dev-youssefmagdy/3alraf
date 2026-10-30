<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Whatsapp\Chat\ChatIngestService;
use App\Services\Whatsapp\WhatsappService;
use Illuminate\Http\Request;

/** Node -> Laravel events: session (status, qr) and chat (message.*, media.ready, presence, contact.update). Authenticated by an HMAC over "timestamp.body" with a 5-minute window; no cookies, no CSRF. */
class WhatsappEventController extends Controller
{
    public function __invoke(Request $request, WhatsappService $wa, ChatIngestService $chat)
    {
        $secret = (string) config('services.whatsapp.callback_secret');
        $ts = (string) $request->header('X-WA-Timestamp');
        $sig = (string) $request->header('X-WA-Signature');
        $valid = $secret !== '' && ctype_digit($ts) && abs(time() - (int) $ts) <= 300
            && hash_equals(hash_hmac('sha256', $ts.'.'.$request->getContent(), $secret), $sig);
        abort_unless($valid, 401);

        $body = $request->json()->all();
        abort_unless(is_string($body['session_id'] ?? null) && is_array($body['data'] ?? null), 422);
        $type = is_string($body['type'] ?? null) ? $body['type'] : 'status';
        if (in_array($type, ['status', 'qr'], true)) {
            $wa->applyEvent($body['session_id'], $body['data']);
        } elseif ($owner = $wa->ownerOf($body['session_id'])) {
            $chat->handle($owner['tenant'], $type, $body['data']); // unknown types are ignored (200) so Node never retries them
        }

        return response()->json(['ok' => true]);
    }
}
