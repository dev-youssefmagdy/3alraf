<?php

namespace App\Services\Push;

use App\Models\Customer;
use App\Models\Device;

/** Register / unregister mobile push tokens for the current tenant. */
class DeviceService
{
    /** Upsert by token (a re-install or a different customer logging in on the same device re-binds the row). */
    public function register(array $d, ?Customer $customer): Device
    {
        $device = Device::firstOrNew(['token_hash' => Device::hash($d['token'])]);
        $device->fill([
            'token' => $d['token'], 'platform' => $d['platform'], 'locale' => $d['locale'] ?? app()->getLocale(),
            'app_version' => $d['app_version'] ?? null, 'customer_id' => $customer?->id, 'last_seen_at' => now(),
        ])->save();

        return $device;
    }

    public function unregister(string $token): bool
    {
        return (bool) Device::where('token_hash', Device::hash($token))->delete();
    }
}
