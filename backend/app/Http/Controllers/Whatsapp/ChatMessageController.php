<?php

namespace App\Http\Controllers\Whatsapp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Whatsapp\Concerns\ResolvesChatOwner;
use App\Http\Requests\Whatsapp\SendChatMessageRequest;
use App\Models\WhatsappMessage;
use App\Services\Whatsapp\Chat\ChatSendService;
use App\Services\Whatsapp\WhatsappUnavailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** Message thread + send pipeline entry points for BOTH panels. */
class ChatMessageController extends Controller implements HasMiddleware
{
    use ResolvesChatOwner;

    public function __construct(private ChatSendService $send) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:whatsapp.chat.view', only: ['index']),
            new Middleware('can:whatsapp.chat.send', except: ['index']),
        ];
    }

    public static function present(WhatsappMessage $m): array
    {
        $reply = $m->reply_to_id ? $m->replyTo : null;

        return $m->toChat() + [
            'sent_by_admin_id' => $m->sent_by_admin_id, 'sent_by_guard' => $m->sent_by_guard,
            'reply_to' => $reply ? ['id' => $reply->id, 'direction' => $reply->direction, 'type' => $reply->type, 'text' => $reply->deleted_at ? null : mb_substr((string) ($reply->body ?: $reply->caption), 0, 120)] : null,
        ];
    }

    /**
     * GET conversations/{id}/messages?limit=&before=<message id>&after=<message id>
     * No cursor = newest page; before = older page; after = gap-fill since an id. `data` is always oldest -> newest (ordered by id = arrival order).
     */
    public function index(Request $request, int $conversation): JsonResponse
    {
        $conv = $this->conversation($request, $conversation);
        $v = $request->validate(['limit' => ['nullable', 'integer', 'between:1,100'], 'before' => ['nullable', 'integer', 'min:1'], 'after' => ['nullable', 'integer', 'min:1']]);
        $limit = $v['limit'] ?? 40;
        $q = WhatsappMessage::ownedBy($this->owner($request))->where('conversation_id', $conv->id)->with(['media', 'replyTo', 'reactions']);

        if (! empty($v['after'])) {
            $rows = (clone $q)->where('id', '>', $v['after'])->orderBy('id')->limit($limit + 1)->get();
            $more = $rows->count() > $limit;
            $rows = $rows->take($limit);
            $moreBefore = false;
        } else {
            if (! empty($v['before'])) {
                $q->where('id', '<', $v['before']);
            }
            $rows = $q->orderByDesc('id')->limit($limit + 1)->get();
            $moreBefore = $rows->count() > $limit;
            $rows = $rows->take($limit)->reverse()->values();
            $more = false;
        }

        return response()->json(['data' => $rows->map(fn ($m) => self::present($m))->all(), 'has_more_before' => $moreBefore, 'has_more_after' => $more]);
    }

    /** POST conversations/{id}/messages -> 202 {data: message(pending)}. See SendChatMessageRequest for the body. */
    public function store(SendChatMessageRequest $request, int $conversation): JsonResponse
    {
        $owner = $this->owner($request);
        $conv = $this->conversation($request, $conversation);
        try {
            $this->send->assertConnected($owner);
        } catch (WhatsappUnavailable) {
            return $this->fail('not_connected', __('whatsapp.chat.errors.not_connected'), 409);
        }
        $msg = $this->send->queue($owner, $conv, $request->validated(), $request->file('file'), $this->guardName($request), (int) $request->user($this->guardName($request))->getKey());
        $this->audit($request, 'message.sent', $msg, ['conversation_id' => $conv->id, 'type' => $msg->type]);

        return response()->json(['data' => self::present($msg->load(['media', 'replyTo']))], 202);
    }

    public function resend(Request $request, int $id): JsonResponse
    {
        $msg = $this->message($request, $id);
        if ($msg->direction !== 'out' || $msg->status !== 'failed' || $msg->wa_message_id) {
            return $this->fail('not_resendable', __('whatsapp.chat.errors.not_resendable'), 422);
        }
        try {
            $this->send->assertConnected($this->owner($request));
        } catch (WhatsappUnavailable) {
            return $this->fail('not_connected', __('whatsapp.chat.errors.not_connected'), 409);
        }

        return response()->json(['data' => self::present($this->send->resend($this->owner($request), $msg)->load(['media', 'replyTo']))], 202);
    }

    /** POST messages/{id}/react {emoji: ""|emoji} (empty removes our reaction) */
    public function react(Request $request, int $id): JsonResponse
    {
        $msg = $this->message($request, $id);
        $v = $request->validate(['emoji' => ['present', 'nullable', 'string', 'max:16']]);
        if (! $msg->wa_message_id || $msg->deleted_at) {
            return $this->fail('not_sent_yet', __('whatsapp.chat.errors.not_actionable'), 422);
        }

        return $this->wa(fn () => $this->send->react($this->owner($request), $msg, (string) ($v['emoji'] ?? '')), $msg);
    }

    /** PUT messages/{id} {text} - edit our own text message (WhatsApp allows ~15 minutes). */
    public function update(Request $request, int $id): JsonResponse
    {
        $msg = $this->message($request, $id);
        $v = $request->validate(['text' => ['required', 'string', 'max:'.config('whatsapp_chat.max_text')]]);
        if ($msg->direction !== 'out' || $msg->type !== 'text' || ! $msg->wa_message_id || $msg->deleted_at || $msg->created_at->lt(now()->subMinutes(15))) {
            return $this->fail('not_editable', __('whatsapp.chat.errors.not_editable'), 422);
        }

        return $this->wa(fn () => $this->send->edit($this->owner($request), $msg, $v['text']), $msg);
    }

    /** DELETE messages/{id} - delete for everyone (our own sent messages only); the row stays as a tombstone. */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $msg = $this->message($request, $id);
        if ($msg->direction !== 'out' || ! $msg->wa_message_id || $msg->deleted_at) {
            return $this->fail('not_deletable', __('whatsapp.chat.errors.not_deletable'), 422);
        }
        $res = $this->wa(fn () => $this->send->revoke($this->owner($request), $msg), $msg);
        if ($res->getStatusCode() === 200) {
            $this->audit($request, 'message.deleted', $msg, ['conversation_id' => $msg->conversation_id]);
        }

        return $res;
    }

    private function message(Request $r, int $id): WhatsappMessage
    {
        return WhatsappMessage::ownedBy($this->owner($r))->findOrFail($id);
    }

    private function wa(\Closure $fn, WhatsappMessage $msg): JsonResponse
    {
        try {
            $fn();
        } catch (WhatsappUnavailable $e) {
            return $this->fail('wa_error', __('whatsapp.chat.errors.wa_error'), $e->getMessage() === 'WhatsApp is not connected' ? 409 : 502);
        }

        return response()->json(['data' => self::present($msg->refresh()->load(['media', 'replyTo']))]);
    }
}
