<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\CustomerMailPolicy;

/** setting `notifications` = {placed,status,payment,return: bool} — which customer e-mails the shop sends (default all on). */
class NotificationSettingsService
{
    public function get(): array
    {
        $saved = (array) setting('notifications', []);

        return collect(CustomerMailPolicy::GROUPS)->mapWithKeys(fn ($g) => [$g => (bool) ($saved[$g] ?? true)])->all();
    }

    public function update(array $flags): array
    {
        $clean = collect(CustomerMailPolicy::GROUPS)->mapWithKeys(fn ($g) => [$g => (bool) ($flags[$g] ?? false)])->all();
        Setting::put('notifications', $clean);

        return $clean;
    }
}
