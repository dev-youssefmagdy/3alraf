<?php

namespace App\Events\Whatsapp;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Base for chat broadcasts: private channel per owner (whatsapp-chat.central | whatsapp-chat.tenant.{id}), dispatched after the DB commit. */
abstract class ChatBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public ?int $tenantId) {}

    public static function channelName(?int $tenantId): string
    {
        return $tenantId ? "whatsapp-chat.tenant.$tenantId" : 'whatsapp-chat.central';
    }

    /** @return array<int,Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(self::channelName($this->tenantId))];
    }
}
