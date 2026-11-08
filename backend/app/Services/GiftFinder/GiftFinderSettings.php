<?php

namespace App\Services\GiftFinder;

use App\Models\Setting;

/** Storefront switch of the Gift Finder: setting `gift_finder.enabled` (Settings → General), default config('gift_finder.enabled_default') = ON. */
class GiftFinderSettings
{
    public function enabled(): bool
    {
        $flag = setting('gift_finder.enabled');

        return $flag === null ? (bool) config('gift_finder.enabled_default', true) : (bool) $flag;
    }

    public function set(bool $on): void
    {
        $row = (array) (setting('gift_finder') ?? []);
        $row['enabled'] = $on;
        Setting::put('gift_finder', $row);
    }
}
