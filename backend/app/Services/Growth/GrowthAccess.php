<?php

namespace App\Services\Growth;

use App\Models\Setting;

/** Master `growth` switch: setting `growth.enabled`, falling back to config('growth.enabled_default'). */
class GrowthAccess
{
    public function enabled(): bool
    {
        $flag = setting('growth.enabled');

        return $flag === null ? (bool) config('growth.enabled_default') : (bool) $flag;
    }

    public function set(bool $on): void
    {
        $growth = (array) (setting('growth') ?? []);
        $growth['enabled'] = $on;
        Setting::put('growth', $growth);
    }
}
