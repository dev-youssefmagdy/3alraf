<?php

namespace App\Events\Whatsapp;

/** Status / edit / delete / reaction / media change: {message} (full render data incl. reactions). */
class ChatMessageUpdated extends ChatBroadcast
{
    public function __construct(public array $message) {}

    public function broadcastAs(): string
    {
        return 'chat.message.updated';
    }

    public function broadcastWith(): array
    {
        return ['message' => $this->message];
    }
}
