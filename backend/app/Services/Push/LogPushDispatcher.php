<?php

namespace App\Services\Push;

use App\Models\Device;
use Illuminate\Support\Facades\Log;

/** No-op dispatcher: logs that a push would have been sent (never the token itself). */
class LogPushDispatcher implements PushDispatcher
{
    public function send(Device $device, string $title, string $body, array $data = []): void
    {
        Log::info('push.noop', ['device_id' => $device->id, 'platform' => $device->platform, 'title' => $title]);
    }
}
