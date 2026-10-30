<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWhatsappOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One chat per (owner, remote jid). @lid chats may have no phone, so the jid is the identity. */
class WhatsappConversation extends Model
{
    use BelongsToWhatsappOwner, HasFactory;

    protected $fillable = ['jid', 'phone', 'name', 'push_name', 'avatar_path', 'last_message_id', 'last_message_at', 'last_message_preview', 'unread_count', 'is_archived', 'is_pinned', 'assigned_admin_id', 'assigned_guard', 'customer_id'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime', 'is_archived' => 'bool', 'is_pinned' => 'bool', 'unread_count' => 'int'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class, 'conversation_id');
    }

    /** Minimal render data for lists and broadcasts. */
    public function toChat(): array
    {
        return [
            'id' => $this->id, 'jid' => $this->jid, 'phone' => $this->phone, 'name' => $this->name ?: $this->push_name,
            'last_message_id' => $this->last_message_id, 'last_message_at' => $this->last_message_at?->toIso8601String(),
            'last_message_preview' => $this->last_message_preview, 'unread_count' => $this->unread_count,
            'is_archived' => $this->is_archived, 'is_pinned' => $this->is_pinned,
            'assigned_admin_id' => $this->assigned_admin_id, 'assigned_guard' => $this->assigned_guard, 'customer_id' => $this->customer_id,
        ];
    }
}
