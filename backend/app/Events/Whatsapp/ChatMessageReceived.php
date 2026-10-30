<?php

namespace App\Events\Whatsapp;

/** New message (in, or sent from the phone): {message, conversation}. */
class ChatMessageReceived extends ChatBroadcast
{
    public function __construct(?int $tenantId, public array $message, public array $conversation)
    {
        parent::__construct($tenantId);
    }

    public function broadcastAs(): string
    {
        return 'chat.message.received';
    }

    public function broadcastWith(): array
    {
        return ['message' => $this->message, 'conversation' => $this->conversation];
    }
}
