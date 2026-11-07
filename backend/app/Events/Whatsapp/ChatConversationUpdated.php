<?php

namespace App\Events\Whatsapp;

/** Conversation list row changed (unread, name, archive, last message): {conversation}. */
class ChatConversationUpdated extends ChatBroadcast
{
    public function __construct(public array $conversation) {}

    public function broadcastAs(): string
    {
        return 'chat.conversation.updated';
    }

    public function broadcastWith(): array
    {
        return ['conversation' => $this->conversation];
    }
}
