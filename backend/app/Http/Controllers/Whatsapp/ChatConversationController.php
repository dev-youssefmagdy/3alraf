<?php

namespace App\Http\Controllers\Whatsapp;

use App\Events\Whatsapp\ChatConversationUpdated;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Whatsapp\Concerns\ResolvesChatOwner;
use App\Models\CentralAdmin;
use App\Models\TenantAdmin;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMedia;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSession;
use App\Services\Whatsapp\Chat\ChatSendService;
use App\Services\Whatsapp\NodeClient;
use App\Services\Whatsapp\WhatsappUnavailable;
use App\Support\AdminPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;

/** Conversation list + per-conversation actions for BOTH panels (guard from the route-name prefix). */
class ChatConversationController extends Controller implements HasMiddleware
{
    use ResolvesChatOwner;

    public function __construct(private ChatSendService $send) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:whatsapp.chat.view', only: ['index', 'show', 'limits', 'read']),
            new Middleware('can:whatsapp.chat.send', only: ['store', 'presence']),
            new Middleware('can:whatsapp.chat.manage', only: ['update', 'assign', 'destroy']),
        ];
    }

    public static function present(WhatsappConversation $c): array
    {
        return $c->toChat() + ['assigned_admin_id' => $c->assigned_admin_id, 'assigned_guard' => $c->assigned_guard, 'customer_id' => $c->customer_id];
    }

    /** GET conversations?q=&archived=0|1&unread=1&pinned=1&mine=1&limit=&cursor= */
    public function index(Request $request): JsonResponse
    {
        $v = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'archived' => ['nullable', 'boolean'], 'unread' => ['nullable', 'boolean'], 'pinned' => ['nullable', 'boolean'],
            'mine' => ['nullable', 'boolean'], 'limit' => ['nullable', 'integer', 'between:1,100'], 'cursor' => ['nullable', 'string', 'max:500'],
        ]);
        $q = WhatsappConversation::ownedBy($this->owner($request))->whereNotNull('last_message_at')->where('is_archived', (bool) ($v['archived'] ?? false));
        if (! empty($v['unread'])) {
            $q->where('unread_count', '>', 0);
        }
        if (! empty($v['pinned'])) {
            $q->where('is_pinned', true);
        }
        if (! empty($v['mine'])) {
            $q->where('assigned_guard', $this->guardName($request))->where('assigned_admin_id', $request->user($this->guardName($request))->getKey());
        }
        if (($term = trim((string) ($v['q'] ?? ''))) !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('push_name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('last_message_preview', 'like', $like));
        }
        $page = $q->orderByDesc('is_pinned')->orderByDesc('last_message_at')->orderByDesc('id')->cursorPaginate($v['limit'] ?? 30);

        return response()->json([
            'data' => collect($page->items())->map(fn ($c) => self::present($c))->all(),
            'next_cursor' => $page->nextCursor()?->encode(), 'has_more' => $page->hasMorePages(),
            'unread_total' => (int) WhatsappConversation::ownedBy($this->owner($request))->where('is_archived', false)->sum('unread_count'),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => self::present($this->conversation($request, $id))]);
    }

    /** Upload caps + connection state, so the composer can validate client-side. */
    public function limits(Request $request): JsonResponse
    {
        $s = WhatsappSession::where('tenant_id', $this->owner($request))->first();

        return response()->json(['data' => [
            'limits' => config('whatsapp_chat.limits'), 'mimes' => config('whatsapp_chat.mimes'), 'max_text' => config('whatsapp_chat.max_text'),
            'connected' => (bool) ($s && $s->is_enabled && $s->isConnected()),
        ]]);
    }

    /** POST conversations {country, phone}: find or create the chat for a number (nothing is sent). */
    public function store(Request $request): JsonResponse
    {
        $v = $request->validate(['country' => ['required', 'string', 'size:2'], 'phone' => ['required', 'string', 'max:30']], [], trans('whatsapp.fields'));
        $digits = AdminPhone::digits($v['country'], $v['phone']);
        if (! $digits) {
            return $this->fail('invalid_phone', __('whatsapp.invalid_phone'), 422);
        }
        $conv = $this->send->conversationForPhone($this->owner($request), $digits);

        return response()->json(['data' => self::present($conv)], $conv->wasRecentlyCreated ? 201 : 200);
    }

    /** POST conversations/{id}/read: reset unread and send read receipts through Node. */
    public function read(Request $request, int $id): JsonResponse
    {
        $conv = $this->conversation($request, $id);
        $synced = $this->send->markRead($this->owner($request), $conv);

        return response()->json(['data' => self::present($conv->refresh()), 'node_synced' => $synced]);
    }

    /** POST conversations/{id}/presence {state: composing|recording|paused|available} */
    public function presence(Request $request, int $id): JsonResponse
    {
        $conv = $this->conversation($request, $id);
        $v = $request->validate(['state' => ['required', 'in:composing,recording,paused,available']]);
        try {
            $this->send->assertConnected($this->owner($request));
            app(NodeClient::class)->presence(WhatsappSession::idFor($this->owner($request)), $conv->jid, $v['state']);
        } catch (WhatsappUnavailable $e) {
            return $this->fail('not_connected', __('whatsapp.chat.errors.not_connected'), 409);
        }

        return response()->json(['success' => true]);
    }

    /** PATCH conversations/{id} {is_archived?, is_pinned?} */
    public function update(Request $request, int $id): JsonResponse
    {
        $conv = $this->conversation($request, $id);
        $v = $request->validate(['is_archived' => ['sometimes', 'boolean'], 'is_pinned' => ['sometimes', 'boolean']]);
        $conv->fill($v)->save();
        ChatConversationUpdated::dispatch($this->owner($request), $conv->toChat());

        return response()->json(['data' => self::present($conv)]);
    }

    /** PUT conversations/{id}/assign {admin_id: int|null} */
    public function assign(Request $request, int $id): JsonResponse
    {
        $conv = $this->conversation($request, $id);
        $v = $request->validate(['admin_id' => ['nullable', 'integer']]);
        $guard = $this->guardName($request);
        $admin = null;
        if (! empty($v['admin_id'])) {
            $admin = $guard === 'central'
                ? CentralAdmin::find($v['admin_id'])
                : TenantAdmin::withoutGlobalScopes()->where('tenant_id', $this->owner($request))->find($v['admin_id']);
            if (! $admin || ! $admin->is_active || ! $admin->can('whatsapp.chat.view')) {
                return $this->fail('invalid_assignee', __('whatsapp.chat.errors.invalid_assignee'), 422);
            }
        }
        $conv->update(['assigned_admin_id' => $admin?->getKey(), 'assigned_guard' => $admin ? $guard : null]);
        ChatConversationUpdated::dispatch($this->owner($request), $conv->toChat());
        $this->audit($request, 'conversation.assigned', $conv, ['assigned_admin_id' => $admin?->getKey()]);

        return response()->json(['data' => self::present($conv)]);
    }

    /** DELETE conversations/{id}: removes the LOCAL copy (messages + media files); nothing is deleted on WhatsApp. */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $owner = $this->owner($request);
        $conv = $this->conversation($request, $id);
        $mediaIds = WhatsappMessage::ownedBy($owner)->where('conversation_id', $conv->id)->whereNotNull('media_id')->pluck('media_id');
        foreach (WhatsappMedia::ownedBy($owner)->whereIn('id', $mediaIds)->get() as $m) {
            foreach (array_filter([$m->path, $m->thumbnail_path]) as $p) {
                Storage::disk($m->disk)->delete($p);
            }
        }
        $this->audit($request, 'conversation.deleted', $conv, ['conversation_id' => $conv->id]);
        WhatsappMessage::ownedBy($owner)->where('conversation_id', $conv->id)->update(['reply_to_id' => null]);
        $conv->delete();
        WhatsappMedia::ownedBy($owner)->whereIn('id', $mediaIds)->delete();

        return $this->ok(__('admin.deleted'));
    }
}
