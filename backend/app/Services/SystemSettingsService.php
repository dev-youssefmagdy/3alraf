<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Setting;
use App\Support\SystemSettings;

/** Admin → System settings: persists the former .env keys. A blank secret keeps the stored one; changed key names (never values) are audited. */
class SystemSettingsService
{
    /** @param array<string,mixed> $input validated form values keyed by setting name */
    public function update(array $input, ?int $actorId): void
    {
        $changed = [];
        foreach (SystemSettings::flat() as $name => [$type, $paths]) {
            if (! array_key_exists($name, $input)) {
                continue;
            }
            $raw = $input[$name];
            if ($type === 'secret' && ($raw === null || $raw === '')) {
                continue;
            }
            $value = SystemSettings::cast($type, $raw);
            if ($value !== SystemSettings::cast($type, config($paths[0]))) {
                $changed[] = $name;
            }
            Setting::put(SystemSettings::PREFIX.$name, $value, true);
        }
        Setting::flush();
        if ($changed) {
            activity('settings')->causedBy($actorId ? Admin::find($actorId) : null)->withProperties(['keys' => $changed])->log('system_settings.updated');
        }
    }
}
