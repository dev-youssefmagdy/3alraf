<?php

namespace App\Services\Whatsapp\Chat;

use App\Events\Whatsapp\ChatConversationUpdated;
use App\Events\Whatsapp\ChatMessageReceived;
use App\Events\Whatsapp\ChatMessageUpdated;
use App\Events\Whatsapp\ChatPresence;
use App\Jobs\PullWhatsappMedia;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMedia;
use App\Models\WhatsappMessage;
use App\Models\WhatsappReaction;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Applies HMAC-verified Node chat events for ONE owner (tenant id, or null = central; derived from the session id, never from the payload).
 * Idempotent: replays of any event are no-ops. Message content is never logged. Unknown event types are ignored.
 */
class ChatIngestService
{
    /** Entry point used by the event controller. */
    public function handle(?int $tenantId, string $type, array $d): void
    {
        match ($type) {
            'message.in' => $this->messageIn($tenantId, $d),
            'media.ready' => $this->mediaReady($tenantId, $d),
            'message.reaction' => $this->reaction($tenantId, $d),
            'message.edit' => $this->edit($tenantId, $d),
            'message.delete' => $this->revoke($tenantId, $d),
            'message.status' => $this->status($tenantId, $d),
            'presence' => $this->presence($tenantId, $d),
            'contact.update' => $this->contacts($tenantId, $d),
            default => null,
        };
    }

    /** @return WhatsappMessage|null the stored message, null when it was a duplicate or invalid */
    public function messageIn(?int $tenantId, array $d): ?WhatsappMessage
    {
        $waId = $this->str($d['id'] ?? null, 100);
        $jid = $this->jid($d['jid'] ?? null);
        if (! $waId || ! $jid) {
            return null;
        }
        $type = in_array($d['type'] ?? null, WhatsappMessage::TYPES, true) ? $d['type'] : 'unsupported';
        $fromMe = (bool) ($d['from_me'] ?? false);
        $ts = $this->time($d['timestamp'] ?? null);

        try {
            $result = DB::transaction(function () use ($tenantId, $d, $waId, $jid, $type, $fromMe, $ts) {
                if (WhatsappMessage::ownedBy($tenantId)->where('wa_message_id', $waId)->exists()) {
                    return null; // replay
                }
                $conv = $this->conversation($tenantId, $jid, $this->jid($d['jid_alt'] ?? null), $this->phone($d['phone'] ?? null), $fromMe ? null : $this->str($d['push_name'] ?? null, 150));

                // Echo of a message WE queued (Phase 3 sender): adopt the pending row instead of inserting a duplicate.
                if ($fromMe && ($adopted = $this->adoptPending($tenantId, $conv, $type, $this->text($d['body'] ?? null), $this->text($d['caption'] ?? null), $waId, $ts))) {
                    return [$adopted, $conv, true];
                }

                $media = null;
                if (is_array($m = $d['media'] ?? null)) {
                    $media = new WhatsappMedia(['disk' => config('whatsapp_chat.disk'), 'mime' => $this->str($m['mime'] ?? null, 120), 'size' => $this->int($m['size'] ?? null),
                        'original_name' => $this->fileName($m['file_name'] ?? null), 'width' => $this->int($m['width'] ?? null), 'height' => $this->int($m['height'] ?? null),
                        'duration' => $this->int($m['duration'] ?? null), 'status' => 'pending']);
                    $media->tenant_id = $tenantId;
                    $media->save();
                    $this->storeThumbnail($media, $m['thumbnail_b64'] ?? null);
                }

                $payload = is_array($d['payload'] ?? null) ? $d['payload'] : null;
                if (is_array($m ?? null) && ! empty($m['animated'])) {
                    $payload = ($payload ?? []) + ['animated' => true];
                }
                $quotedId = is_array($d['quoted'] ?? null) ? $this->str($d['quoted']['id'] ?? null, 100) : null;
                $msg = new WhatsappMessage([
                    'conversation_id' => $conv->id, 'wa_message_id' => $waId, 'direction' => $fromMe ? 'out' : 'in', 'type' => $type,
                    'body' => $this->text($d['body'] ?? null), 'caption' => $this->text($d['caption'] ?? null), 'payload' => $payload,
                    'reply_to_id' => $quotedId ? WhatsappMessage::ownedBy($tenantId)->where('conversation_id', $conv->id)->where('wa_message_id', $quotedId)->value('id') : null,
                    'media_id' => $media?->id, 'status' => $fromMe ? 'sent' : 'delivered', 'wa_timestamp' => $ts,
                ]);
                $msg->tenant_id = $tenantId;
                $msg->save();

                // last_message_* only moves forward (out-of-order delivery must not rewind the list)
                if (! $conv->last_message_at || $ts->gte($conv->last_message_at)) {
                    $conv->fill(['last_message_id' => $msg->id, 'last_message_at' => $ts, 'last_message_preview' => $this->preview($msg)]);
                }
                if (! $fromMe) {
                    $conv->unread_count++;
                }
                $conv->save();

                return [$msg, $conv, false];
            });
        } catch (UniqueConstraintViolationException) {
            return null; // concurrent replay lost the race
        }
        if (! $result) {
            return null;
        }
        [$msg, $conv, $adopted] = $result;
        if ($adopted) {
            ChatMessageUpdated::dispatch($tenantId, $msg->load('media')->toChat());

            return $msg;
        }
        ChatMessageReceived::dispatch($tenantId, $msg->load('media')->toChat(), $conv->toChat());

        return $msg;
    }

    /**
     * Reconcile Node's from_me echo with the outgoing row the sender queued: the oldest still-pending, id-less row of the
     * same conversation/type/text that was created recently. Runs inside the ingest transaction (row lock), so it cannot
     * race with the send job's own write of wa_message_id (see ChatSendService::finalize, which handles the other order).
     */
    private function adoptPending(?int $tenantId, WhatsappConversation $conv, string $type, ?string $body, ?string $caption, string $waId, Carbon $ts): ?WhatsappMessage
    {
        $q = WhatsappMessage::ownedBy($tenantId)->where('conversation_id', $conv->id)->where('direction', 'out')->whereNull('wa_message_id')
            ->where('status', 'pending')->where('type', $type)->where('created_at', '>=', now()->subMinutes(5));
        foreach (['body' => $body, 'caption' => $caption] as $col => $val) {
            $val === null ? $q->whereNull($col) : $q->where($col, $val);
        }
        $row = $q->orderBy('id')->lockForUpdate()->first();
        if (! $row) {
            return null;
        }
        $row->forceFill(['wa_message_id' => $waId, 'status' => 'sent', 'error' => null, 'wa_timestamp' => $ts])->save();

        return $row;
    }

    /** media.ready: failed -> mark the media; ready -> pull the bytes from Node (queued, token is short-lived). */
    public function mediaReady(?int $tenantId, array $d): void
    {
        $msg = $this->target($tenantId, $d['message_id'] ?? null);
        if (! $msg || ! $msg->media_id) {
            return;
        }
        $media = WhatsappMedia::ownedBy($tenantId)->find($msg->media_id);
        if (! $media || $media->status === 'ready') {
            return;
        }
        if (($d['status'] ?? '') === 'ready' && is_string($d['token'] ?? null) && preg_match('/^[a-f0-9]{16,64}$/i', $d['token'])) {
            $media->fill(['mime' => $this->str($d['mime'] ?? null, 120) ?: $media->mime, 'size' => $this->int($d['size'] ?? null) ?: $media->size, 'sha256' => $this->str($d['sha256'] ?? null, 64)])->save();
            PullWhatsappMedia::dispatch($tenantId, $media->id, $d['token']);

            return;
        }
        $media->update(['status' => 'failed']);
        ChatMessageUpdated::dispatch($tenantId, $msg->load('media')->toChat());
    }

    private function reaction(?int $tenantId, array $d): void
    {
        $msg = $this->target($tenantId, $d['target_id'] ?? null);
        if (! $msg) {
            return;
        }
        $reactor = ($d['from_me'] ?? false) ? 'me' : ($this->jid($d['jid'] ?? null) ?? null);
        if (! $reactor) {
            return;
        }
        $emoji = $this->str($d['emoji'] ?? '', 32) ?? '';
        $emoji === ''
            ? WhatsappReaction::where(['message_id' => $msg->id, 'reactor_jid' => $reactor])->delete()
            : WhatsappReaction::updateOrCreate(['message_id' => $msg->id, 'reactor_jid' => $reactor], ['emoji' => $emoji]);
        ChatMessageUpdated::dispatch($tenantId, $msg->load('media')->toChat());
    }

    private function edit(?int $tenantId, array $d): void
    {
        $msg = $this->target($tenantId, $d['target_id'] ?? null);
        if (! $msg || $msg->deleted_at) {
            return;
        }
        $body = $this->text($d['body'] ?? null);
        // media messages carry their text in `caption`, text messages in `body`
        $msg->fill($msg->type === 'text' ? ['body' => $body] : ['caption' => $body]);
        $msg->edited_at = $this->time($d['edited_at'] ?? $d['timestamp'] ?? null);
        $msg->save();
        $this->refreshPreview($tenantId, $msg);
        ChatMessageUpdated::dispatch($tenantId, $msg->load('media')->toChat());
    }

    private function revoke(?int $tenantId, array $d): void
    {
        $msg = $this->target($tenantId, $d['target_id'] ?? null);
        if (! $msg || $msg->deleted_at) {
            return;
        }
        // tombstone: content is erased, the row stays so the thread still shows "this message was deleted"
        $msg->forceFill(['deleted_at' => now(), 'body' => null, 'caption' => null, 'payload' => null])->save();
        $this->refreshPreview($tenantId, $msg);
        ChatMessageUpdated::dispatch($tenantId, $msg->load('media')->toChat());
    }

    private function status(?int $tenantId, array $d): void
    {
        $msg = $this->target($tenantId, $d['id'] ?? null);
        $new = $d['status'] ?? null;
        if (! $msg || $msg->direction !== 'out' || ! in_array($new, ['pending', 'sent', 'delivered', 'read', 'failed'], true)) {
            return;
        }
        $rank = WhatsappMessage::STATUS_RANK;
        // forward only; 'failed' never overrides a message that already left (sent or later)
        if ($new === 'failed' ? $msg->status !== 'pending' : ($rank[$new] <= ($rank[$msg->status] ?? -1))) {
            return;
        }
        $msg->update(['status' => $new]);
        ChatMessageUpdated::dispatch($tenantId, $msg->load('media')->toChat());
    }

    private function presence(?int $tenantId, array $d): void
    {
        $jid = $this->jid($d['jid'] ?? null);
        $state = $d['state'] ?? null;
        if (! $jid || ! in_array($state, ['composing', 'recording', 'available', 'unavailable', 'paused'], true)) {
            return;
        }
        ChatPresence::dispatch($tenantId, ['jid' => $jid, 'participant' => $this->jid($d['participant'] ?? null), 'state' => $state, 'last_seen' => $this->int($d['last_seen'] ?? null)]);
    }

    /** Names for EXISTING conversations only (no conversation is created from a contact sync). */
    private function contacts(?int $tenantId, array $d): void
    {
        foreach (array_slice(is_array($d['contacts'] ?? null) ? $d['contacts'] : [], 0, 200) as $c) {
            $jid = is_array($c) ? $this->jid($c['jid'] ?? null) : null;
            $conv = $jid ? WhatsappConversation::ownedBy($tenantId)->where('jid', $jid)->first() : null;
            if (! $conv) {
                continue;
            }
            $conv->fill(array_filter(['name' => $this->str($c['name'] ?? $c['verified_name'] ?? null, 150), 'push_name' => $this->str($c['notify'] ?? null, 150)]));
            if ($conv->isDirty()) {
                $conv->save();
                ChatConversationUpdated::dispatch($tenantId, $conv->toChat());
            }
        }
    }

    /** Upsert by (owner, jid). A known alt jid (phone <-> lid) adopts the existing conversation instead of splitting the thread. */
    private function conversation(?int $tenantId, string $jid, ?string $alt, ?string $phone, ?string $pushName): WhatsappConversation
    {
        $q = fn () => WhatsappConversation::ownedBy($tenantId)->lockForUpdate();
        $conv = $q()->where('jid', $jid)->first();
        if (! $conv && $alt) {
            if ($conv = $q()->where('jid', $alt)->first()) {
                $conv->jid = $jid;
            }
        }
        if (! $conv) {
            $conv = new WhatsappConversation(['jid' => $jid, 'unread_count' => 0]);
            $conv->tenant_id = $tenantId;
        }
        $conv->phone = $phone ?: $conv->phone;
        $conv->push_name = $pushName ?: $conv->push_name;
        if (! $conv->customer_id && $tenantId && $conv->phone) {
            $conv->customer_id = $this->customerByPhone($tenantId, $conv->phone);
        }
        $conv->save();

        return $conv;
    }

    /** Tenant-scoped (explicit tenant_id, never cross-tenant); only an UNAMBIGUOUS single match links a customer. */
    private function customerByPhone(int $tenantId, string $digits): ?int
    {
        $ids = DB::table('customers')->where('tenant_id', $tenantId)->whereNull('deleted_at')->whereNotNull('phone')
            ->whereRaw("CONCAT(REPLACE(COALESCE(phone_country_code, ''), '+', ''), TRIM(LEADING '0' FROM phone)) = ?", [$digits])->limit(2)->pluck('id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    private function target(?int $tenantId, mixed $waId): ?WhatsappMessage
    {
        $id = $this->str($waId, 100);

        return $id ? WhatsappMessage::ownedBy($tenantId)->where('wa_message_id', $id)->first() : null;
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

    private function storeThumbnail(WhatsappMedia $media, mixed $b64): void
    {
        if (! is_string($b64) || $b64 === '' || strlen($b64) > 200_000 || ! ($raw = base64_decode($b64, true))) {
            return;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($raw);
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return;
        }
        $path = MediaPaths::dir($media->owner_key).'/thumb-'.bin2hex(random_bytes(12)).'.'.(['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime]);
        Storage::disk($media->disk)->put($path, $raw);
        $media->update(['thumbnail_path' => $path]);
    }

    private function str(mixed $v, int $max): ?string
    {
        return is_string($v) && $v !== '' ? mb_substr($v, 0, $max) : null;
    }

    private function text(mixed $v): ?string
    {
        return is_string($v) && $v !== '' ? mb_substr($v, 0, 65000) : null;
    }

    private function int(mixed $v): ?int
    {
        return is_numeric($v) && $v >= 0 ? (int) $v : null;
    }

    /** Only 1:1 user chats; groups/broadcast/status are dropped by Node and rejected here too. */
    private function jid(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^[0-9A-Za-z._:-]{1,64}@(s\.whatsapp\.net|lid)$/', $v) ? $v : null;
    }

    private function phone(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^[0-9]{5,20}$/', $v) ? $v : null;
    }

    private function time(mixed $v): Carbon
    {
        return is_numeric($v) && $v > 0 && $v < 4102444800 ? Carbon::createFromTimestamp((int) $v) : now();
    }

    /** Display name only: strip path parts and control characters. */
    private function fileName(mixed $v): ?string
    {
        $n = is_string($v) ? preg_replace('/[\x00-\x1F\x7F\\\\\/]+/u', '_', basename(str_replace('\\', '/', $v))) : null;

        return $n !== null && $n !== '' && $n !== '.' && $n !== '..' ? mb_substr($n, 0, 200) : null;
    }
}
