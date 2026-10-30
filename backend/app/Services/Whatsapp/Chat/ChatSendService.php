<?php

namespace App\Services\Whatsapp\Chat;

use App\Events\Whatsapp\ChatConversationUpdated;
use App\Events\Whatsapp\ChatMessageReceived;
use App\Events\Whatsapp\ChatMessageUpdated;
use App\Jobs\SendWhatsappMessage;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMedia;
use App\Models\WhatsappMessage;
use App\Models\WhatsappReaction;
use App\Models\WhatsappSession;
use App\Services\Whatsapp\NodeClient;
use App\Services\Whatsapp\WhatsappService;
use App\Services\Whatsapp\WhatsappUnavailable;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Outgoing chat pipeline for ONE owner (tenant id, null = central). Every method takes the owner from the authenticated
 * panel; the Node session id is always WhatsappSession::idFor($owner). Message content is never logged.
 * OTP traffic does not pass through here (it keeps using WhatsappService::sendText).
 */
class ChatSendService
{
    public function __construct(private NodeClient $node, private WhatsappService $wa) {}

    /** @throws WhatsappUnavailable when the owner's session is not usable */
    public function assertConnected(?int $tenantId): WhatsappSession
    {
        $s = $this->wa->session($tenantId);
        if (! $s->is_enabled || ! $s->isConnected()) {
            throw new WhatsappUnavailable('WhatsApp is not connected');
        }

        return $s;
    }

    /** Find or create the 1:1 conversation for an E.164 digit string (no network call: Node validates the number on first send). */
    public function conversationForPhone(?int $tenantId, string $digits): WhatsappConversation
    {
        $jid = $digits.'@s.whatsapp.net';
        $conv = WhatsappConversation::ownedBy($tenantId)->where(fn ($q) => $q->where('jid', $jid)->orWhere('phone', $digits))->first();
        if (! $conv) {
            $conv = new WhatsappConversation(['jid' => $jid, 'phone' => $digits, 'unread_count' => 0]);
            $conv->tenant_id = $tenantId;
            $conv->save();
        }

        return $conv;
    }

    /**
     * Create the pending row, broadcast it, and queue the send.
     * $d: type, text?, caption?, location?{lat,lng,name?,address?}, contacts?[{name,phone}], poll?{name,options[],selectable_count?}, reply_to_id?
     */
    public function queue(?int $tenantId, WhatsappConversation $conv, array $d, ?UploadedFile $file, string $guard, int $adminId): WhatsappMessage
    {
        $type = $d['type'];
        $reply = ! empty($d['reply_to_id']) ? WhatsappMessage::ownedBy($tenantId)->where('conversation_id', $conv->id)->find($d['reply_to_id']) : null;
        $media = $file ? $this->storeUpload($tenantId, $file, $type) : null;

        $body = $caption = $payload = null;
        match (true) {
            $type === 'text' => $body = $d['text'],
            isset(config('whatsapp_chat.limits')[$type]) => $caption = $d['caption'] ?? null,
            $type === 'location' => $payload = array_filter(['lat' => (float) $d['location']['lat'], 'lng' => (float) $d['location']['lng'], 'name' => $d['location']['name'] ?? null, 'address' => $d['location']['address'] ?? null], fn ($v) => $v !== null),
            $type === 'contact' => $payload = ['contacts' => array_map(fn ($c) => ['name' => $c['name'], 'phone' => $c['phone']], array_values($d['contacts']))],
            $type === 'poll' => [$body, $payload] = [$d['poll']['name'], ['options' => array_values($d['poll']['options']), 'selectable_count' => (int) ($d['poll']['selectable_count'] ?? 1)]],
        };

        $msg = DB::transaction(function () use ($tenantId, $conv, $type, $body, $caption, $payload, $reply, $media, $guard, $adminId) {
            $msg = new WhatsappMessage([
                'conversation_id' => $conv->id, 'direction' => 'out', 'type' => $type, 'body' => $body, 'caption' => $caption, 'payload' => $payload,
                'reply_to_id' => $reply?->id, 'media_id' => $media?->id, 'status' => 'pending', 'sent_by_admin_id' => $adminId, 'sent_by_guard' => $guard, 'wa_timestamp' => now(),
            ]);
            $msg->tenant_id = $tenantId;
            $msg->save();
            $fresh = WhatsappConversation::ownedBy($tenantId)->lockForUpdate()->find($conv->id);
            $fresh->fill(['last_message_id' => $msg->id, 'last_message_at' => now(), 'last_message_preview' => $this->preview($msg)])->save();

            return $msg;
        });

        ChatMessageReceived::dispatch($tenantId, $msg->load('media')->toChat(), $msg->conversation()->first()->toChat());
        SendWhatsappMessage::dispatch($tenantId, $msg->id);

        return $msg;
    }

    /** Failed -> pending again, same row (no duplicate bubble). */
    public function resend(?int $tenantId, WhatsappMessage $msg): WhatsappMessage
    {
        $msg->forceFill(['status' => 'pending', 'error' => null, 'created_at' => now()])->save();
        ChatMessageUpdated::dispatch($tenantId, $msg->load('media')->toChat());
        SendWhatsappMessage::dispatch($tenantId, $msg->id);

        return $msg;
    }

    /**
     * Queue worker entry: send one pending row. Never throws for Node/validation problems (the row becomes `failed`),
     * and never sends twice (only a pending row without a wa id is eligible).
     */
    public function deliver(?int $tenantId, int $messageId): void
    {
        $msg = WhatsappMessage::ownedBy($tenantId)->with(['conversation', 'media', 'replyTo'])->find($messageId);
        if (! $msg || $msg->direction !== 'out' || $msg->status !== 'pending' || $msg->wa_message_id) {
            return;
        }
        $sid = WhatsappSession::idFor($tenantId);
        try {
            $this->assertConnected($tenantId);
            $resp = $this->node->sendTyped($sid, $this->payload($tenantId, $msg, $sid));
        } catch (WhatsappUnavailable $e) {
            $this->fail($tenantId, $msg, $e->getMessage());

            return;
        } catch (\Throwable $e) {
            $this->fail($tenantId, $msg, 'send_failed');
            report($e);

            return;
        }
        $this->finalize($tenantId, $msg, $resp);
    }

    public function fail(?int $tenantId, WhatsappMessage $msg, string $reason): void
    {
        $msg->refresh();
        if ($msg->status !== 'pending') {
            return; // echo/receipt already proved it left
        }
        $msg->forceFill(['status' => 'failed', 'error' => mb_substr($reason, 0, 190)])->save();
        ChatMessageUpdated::dispatch($tenantId, $msg->load('media')->toChat());
    }

    /**
     * Record Node's answer. Echo race, both orders: if the from_me echo already adopted this row, the wa id is set and we only
     * keep the (possibly further advanced) status; if an echo row was inserted with this id first (no matching pending row),
     * it is merged into ours so the thread never shows the message twice.
     */
    private function finalize(?int $tenantId, WhatsappMessage $msg, array $resp): void
    {
        $waId = is_string($resp['message_id'] ?? null) && $resp['message_id'] !== '' ? mb_substr($resp['message_id'], 0, 100) : null;
        DB::transaction(function () use ($tenantId, $msg, $waId, $resp) {
            $row = WhatsappMessage::ownedBy($tenantId)->lockForUpdate()->find($msg->id);
            if (! $row) {
                return;
            }
            if ($waId && ! $row->wa_message_id) {
                $dup = WhatsappMessage::ownedBy($tenantId)->where('wa_message_id', $waId)->where('id', '!=', $row->id)->first();
                if ($dup) {
                    WhatsappConversation::ownedBy($tenantId)->where('last_message_id', $dup->id)->update(['last_message_id' => $row->id]);
                    WhatsappMessage::ownedBy($tenantId)->where('reply_to_id', $dup->id)->update(['reply_to_id' => $row->id]);
                    WhatsappReaction::where('message_id', $dup->id)->update(['message_id' => $row->id]);
                    $dup->delete();
                }
                $row->wa_message_id = $waId;
            }
            if ($row->status === 'pending') {
                $row->status = 'sent';
            }
            $row->error = null;
            if (is_numeric($resp['timestamp'] ?? null) && $resp['timestamp'] > 0) {
                $row->wa_timestamp = Carbon::createFromTimestamp((int) $resp['timestamp']);
            }
            $row->save();
            $this->adoptJid($tenantId, $row->conversation_id, $resp['to_jid'] ?? null);
        });
        ChatMessageUpdated::dispatch($tenantId, $msg->fresh()->load('media')->toChat());
    }

    /** A new-chat-by-phone conversation starts with a provisional jid; Node tells us the real one (may be @lid). */
    private function adoptJid(?int $tenantId, int $convId, mixed $jid): void
    {
        if (! is_string($jid) || ! preg_match('/^[0-9A-Za-z._:-]{1,64}@(s\.whatsapp\.net|lid)$/', $jid)) {
            return;
        }
        $conv = WhatsappConversation::ownedBy($tenantId)->lockForUpdate()->find($convId);
        if (! $conv || $conv->jid === $jid) {
            return;
        }
        $existing = WhatsappConversation::ownedBy($tenantId)->where('jid', $jid)->where('id', '!=', $conv->id)->first();
        if (! $existing) {
            $conv->update(['jid' => $jid]);

            return;
        }
        WhatsappMessage::ownedBy($tenantId)->where('conversation_id', $conv->id)->update(['conversation_id' => $existing->id]);
        $existing->fill(['last_message_id' => $conv->last_message_id, 'last_message_at' => $conv->last_message_at, 'last_message_preview' => $conv->last_message_preview])->save();
        $conv->delete();
        ChatConversationUpdated::dispatch($tenantId, $existing->toChat());
    }

    /** Typed Node payload. `to` is the phone (Node does the onWhatsApp lookup) until the conversation has a confirmed message, then the jid. */
    private function payload(?int $tenantId, WhatsappMessage $msg, string $sid): array
    {
        $conv = $msg->conversation;
        $confirmed = WhatsappMessage::ownedBy($tenantId)->where('conversation_id', $conv->id)->whereNotNull('wa_message_id')->exists();
        $p = ['to' => (! $confirmed && $conv->phone && str_ends_with($conv->jid, '@s.whatsapp.net')) ? $conv->phone : $conv->jid, 'type' => $msg->type];

        switch ($msg->type) {
            case 'text':
                $p['text'] = (string) $msg->body;
                break;
            case 'location':
                $p['location'] = $msg->payload;
                break;
            case 'contact':
                $p['contacts'] = $msg->payload['contacts'];
                break;
            case 'poll':
                $p['poll'] = ['name' => $msg->body, 'options' => $msg->payload['options'], 'selectable_count' => $msg->payload['selectable_count'] ?? 1];
                break;
            default: // media
                $m = $msg->media;
                if (! $m || ! $m->path) {
                    throw new WhatsappUnavailable('media_missing');
                }
                $bytes = Storage::disk($m->disk)->get($m->path);
                $up = $this->node->upload($sid, $bytes, $m->mime ?: 'application/octet-stream', $m->original_name ?: 'file');
                $p['media'] = ['upload_id' => $up['upload_id'], 'file_name' => $m->original_name];
                if ($msg->caption !== null) {
                    $p['caption'] = $msg->caption;
                }
        }
        if ($r = $msg->replyTo) {
            if ($r->wa_message_id) {
                $p['reply_to'] = ['id' => $r->wa_message_id, 'from_me' => $r->direction === 'out'];
            }
        }

        return $p;
    }

    /** Send an emoji reaction (empty = remove). Synchronous: tiny payload. @throws WhatsappUnavailable */
    public function react(?int $tenantId, WhatsappMessage $target, string $emoji): void
    {
        $this->assertConnected($tenantId);
        $this->node->sendTyped(WhatsappSession::idFor($tenantId), [
            'to' => $target->conversation()->value('jid'), 'type' => 'reaction',
            'reaction' => ['message_id' => $target->wa_message_id, 'from_me' => $target->direction === 'out', 'emoji' => $emoji],
        ]);
        $emoji === ''
            ? WhatsappReaction::where(['message_id' => $target->id, 'reactor_jid' => 'me'])->delete()
            : WhatsappReaction::updateOrCreate(['message_id' => $target->id, 'reactor_jid' => 'me'], ['emoji' => $emoji]);
        ChatMessageUpdated::dispatch($tenantId, $target->load('media')->toChat());
    }

    /** Edit one of our text messages. @throws WhatsappUnavailable */
    public function edit(?int $tenantId, WhatsappMessage $msg, string $text): void
    {
        $this->assertConnected($tenantId);
        $this->node->sendTyped(WhatsappSession::idFor($tenantId), [
            'to' => $msg->conversation()->value('jid'), 'type' => 'edit', 'edit' => ['message_id' => $msg->wa_message_id, 'text' => $text],
        ]);
        $msg->forceFill(['body' => $text, 'edited_at' => now()])->save();
        $this->refreshPreview($tenantId, $msg);
        ChatMessageUpdated::dispatch($tenantId, $msg->load('media')->toChat());
    }

    /** Delete for everyone; the row stays as a tombstone, like inbound revokes. @throws WhatsappUnavailable */
    public function revoke(?int $tenantId, WhatsappMessage $msg): void
    {
        $this->assertConnected($tenantId);
        $this->node->sendTyped(WhatsappSession::idFor($tenantId), [
            'to' => $msg->conversation()->value('jid'), 'type' => 'delete', 'delete' => ['message_id' => $msg->wa_message_id, 'from_me' => $msg->direction === 'out'],
        ]);
        $msg->forceFill(['deleted_at' => now(), 'body' => null, 'caption' => null, 'payload' => null])->save();
        $this->refreshPreview($tenantId, $msg);
        ChatMessageUpdated::dispatch($tenantId, $msg->load('media')->toChat());
    }

    /** Reset unread and tell Node to send read receipts for the latest incoming messages (Node failure is non-fatal). @return bool whether Node was told */
    public function markRead(?int $tenantId, WhatsappConversation $conv): bool
    {
        $keys = WhatsappMessage::ownedBy($tenantId)->where('conversation_id', $conv->id)->where('direction', 'in')->whereNotNull('wa_message_id')
            ->orderByDesc('id')->limit(max(1, min(50, $conv->unread_count ?: 1)))->pluck('wa_message_id')->map(fn ($id) => ['id' => $id])->all();
        $hadUnread = $conv->unread_count > 0;
        $conv->update(['unread_count' => 0]);
        if ($hadUnread) {
            ChatConversationUpdated::dispatch($tenantId, $conv->toChat());
        }
        if (! $keys || ! $hadUnread) {
            return false;
        }
        try {
            $this->node->markRead(WhatsappSession::idFor($tenantId), $conv->jid, $keys);

            return true;
        } catch (WhatsappUnavailable) {
            return false;
        }
    }

    private function refreshPreview(?int $tenantId, WhatsappMessage $msg): void
    {
        $conv = WhatsappConversation::ownedBy($tenantId)->where('last_message_id', $msg->id)->first();
        if ($conv) {
            $conv->update(['last_message_preview' => $this->preview($msg)]);
            ChatConversationUpdated::dispatch($tenantId, $conv->toChat());
        }
    }

    private function preview(WhatsappMessage $m): ?string
    {
        $t = $m->deleted_at ? '' : trim((string) ($m->body ?: $m->caption));

        return $t !== '' ? mb_substr($t, 0, 100) : null;
    }

    /** Sniffed MIME + size already validated by the request; store on the private disk under a random name. */
    private function storeUpload(?int $tenantId, UploadedFile $file, string $type): WhatsappMedia
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()) ?: 'application/octet-stream';
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'video/mp4' => 'mp4', 'audio/ogg' => 'ogg', 'audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a', 'application/pdf' => 'pdf'][$mime] ?? 'bin';
        $disk = config('whatsapp_chat.disk');
        $path = MediaPaths::dir((int) $tenantId).'/out-'.bin2hex(random_bytes(16)).'.'.$ext;
        Storage::disk($disk)->put($path, file_get_contents($file->getRealPath()));
        $name = preg_replace('/[\x00-\x1F\x7F\\\\\/]+/u', '_', basename(str_replace('\\', '/', (string) $file->getClientOriginalName())));
        $name = mb_substr($name !== '' && $name !== '.' && $name !== '..' ? $name : 'file.'.$ext, 0, 200);

        $media = new WhatsappMedia(['disk' => $disk, 'path' => $path, 'mime' => $mime, 'size' => $file->getSize(), 'original_name' => $name, 'sha256' => hash_file('sha256', $file->getRealPath()), 'status' => 'ready']);
        $media->tenant_id = $tenantId;
        $media->save();

        return $media;
    }
}
