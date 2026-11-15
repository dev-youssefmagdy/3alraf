<?php

use App\Models\Setting;
use App\Support\SystemSettings;
use Illuminate\Database\Migrations\Migration;

/** Copies the current .env/config values of the former custom keys into the `settings` table (Admin → System settings). Never overwrites a stored value. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (SystemSettings::flat() as $name => [$type, $paths]) {
            $key = SystemSettings::PREFIX.$name;
            if (Setting::query()->where('key', $key)->exists()) {
                continue;
            }
            $v = config($paths[0]);
            if ($v === null || $v === '' || $v === []) {
                continue; // nothing configured: keep following the config default
            }
            Setting::put($key, SystemSettings::cast($type, $v), true);
        }
    }

    public function down(): void
    {
        Setting::query()->where('key', 'like', SystemSettings::PREFIX.'%')->delete();
    }
};
