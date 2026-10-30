<?php

namespace App\Events\Whatsapp;

/** Typing / online presence (not persisted): {jid, participant, state, last_seen}. */
class ChatPresence extends ChatBroadcast
{
    public function __construct(?int $tenantId, public array $presence)
    {
        parent::__construct($tenantId);
    }

    public function broadcastAs(): string
    {
        return 'chat.presence';
    }

    public function broadcastWith(): array
    {
        return $this->presence;
    }
}
