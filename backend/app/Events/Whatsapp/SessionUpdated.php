<?php

namespace App\Events\Whatsapp;

use App\Models\WhatsappSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** QR / status change, sent to the private channel `whatsapp` (guard admin). */
class SessionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public WhatsappSession $session, public ?string $qr = null) {}

    public const CHANNEL = 'whatsapp';

    /** The single connection channel. */
    public static function channelName(): string
    {
        return self::CHANNEL;
    }

    /** @return array<int,Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(self::CHANNEL)];
    }

    public function broadcastAs(): string
    {
        return 'session.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'status' => $this->session->status, 'phone' => $this->session->phone, 'error' => $this->session->last_error,
            'last_connected_at' => $this->session->last_connected_at?->toIso8601String(), 'qr' => $this->session->status === 'qr' ? $this->qr : null,
        ];
    }
}
