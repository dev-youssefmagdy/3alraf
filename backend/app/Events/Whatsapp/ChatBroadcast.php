<?php

namespace App\Events\Whatsapp;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Base for chat broadcasts: private channel `whatsapp-chat` (guard admin), dispatched after the DB commit. */
abstract class ChatBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public const CHANNEL = 'whatsapp-chat';

    /** The single chat channel. */
    public static function channelName(): string
    {
        return self::CHANNEL;
    }

    /** @return array<int,Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(self::CHANNEL)];
    }
}
