<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWhatsappOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappMessage extends Model
{
    use BelongsToWhatsappOwner, HasFactory;

    public const TYPES = ['text', 'image', 'video', 'audio', 'ptt', 'document', 'sticker', 'location', 'live_location', 'contact', 'poll', 'unsupported'];

    /** Delivery ladder: a status only moves forward (receipts can arrive out of order); 'failed' is set by the sender, not by receipts. */
    public const STATUS_RANK = ['pending' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3];

    protected $fillable = ['conversation_id', 'wa_message_id', 'direction', 'type', 'body', 'caption', 'payload', 'reply_to_id', 'media_id', 'status', 'error', 'sent_by_admin_id', 'sent_by_guard', 'wa_timestamp', 'edited_at', 'deleted_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'wa_timestamp' => 'datetime', 'edited_at' => 'datetime', 'deleted_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversation::class, 'conversation_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(WhatsappMedia::class, 'media_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(WhatsappReaction::class, 'message_id');
    }

    /** Render data for the UI and broadcasts (ids + minimal fields, never media bytes). Revoked messages expose no content. */
    public function toChat(): array
    {
        $revoked = $this->deleted_at !== null;
        $m = $this->relationLoaded('media') ? $this->media : $this->media()->first();

        return [
            'id' => $this->id, 'conversation_id' => $this->conversation_id, 'wa_message_id' => $this->wa_message_id,
            'direction' => $this->direction, 'type' => $this->type, 'status' => $this->status, 'error' => $this->error,
            'body' => $revoked ? null : $this->body, 'caption' => $revoked ? null : $this->caption, 'payload' => $revoked ? null : $this->payload,
            'reply_to_id' => $this->reply_to_id, 'wa_timestamp' => $this->wa_timestamp?->toIso8601String(),
            'edited_at' => $this->edited_at?->toIso8601String(), 'deleted_at' => $this->deleted_at?->toIso8601String(),
            'media' => $m && ! $revoked ? ['id' => $m->id, 'mime' => $m->mime, 'size' => $m->size, 'width' => $m->width, 'height' => $m->height, 'duration' => $m->duration, 'status' => $m->status, 'name' => $m->original_name] : null,
            'reactions' => ($this->relationLoaded('reactions') ? $this->reactions : $this->reactions()->get(['reactor_jid', 'emoji']))->map->only(['reactor_jid', 'emoji'])->values()->all(),
        ];
    }
}
