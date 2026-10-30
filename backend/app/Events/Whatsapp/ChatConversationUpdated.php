<?php

namespace App\Events\Whatsapp;

/** Conversation list row changed (unread, name, archive, last message): {conversation}. */
class ChatConversationUpdated extends ChatBroadcast
{
    public function __construct(?int $tenantId, public array $conversation)
    {
        parent::__construct($tenantId);
    }

    public function broadcastAs(): string
    {
        return 'chat.conversation.updated';
    }

    public function broadcastWith(): array
    {
        return ['conversation' => $this->conversation];
    }
}
