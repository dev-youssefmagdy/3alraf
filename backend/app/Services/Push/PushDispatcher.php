<?php

namespace App\Services\Push;

use App\Models\Device;

/** Sends a push notification to a device. Implementations: LogPushDispatcher (default, no-op); an FCM one is a follow-up. */
interface PushDispatcher
{
    /** @param array<string,string> $data */
    public function send(Device $device, string $title, string $body, array $data = []): void;
}
