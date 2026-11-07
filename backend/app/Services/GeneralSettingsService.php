<?php

namespace App\Services;

use App\DTOs\GeneralSettingsData;
use App\Models\AiSetting;
use App\Models\Setting;
use App\Services\Ai\AiAccess;
use App\Services\Growth\ActionLogger;
use App\Services\Growth\GrowthAccess;
use Illuminate\Support\Facades\DB;

/** Settings → General: writes the shop profile keys of `settings` and the AI / Growth master switches (audited). */
class GeneralSettingsService
{
    public function __construct(private GrowthAccess $growth, private AiAccess $ai, private ActionLogger $log) {}

    /** @return array<string,mixed> current values for the form */
    public function current(): array
    {
        return [
            'name' => setting('name'), 'country' => setting('country'), 'currency_code' => setting('currency_code'), 'currency_symbol' => setting('currency_symbol'),
            'default_locale' => setting('default_locale'), 'locales' => (array) setting('locales', []), 'timezone' => setting('timezone'),
            'ai_enabled' => $this->ai->enabled(), 'growth_enabled' => $this->growth->enabled(),
        ];
    }

    public function update(GeneralSettingsData $d, ?int $actorId): void
    {
        DB::transaction(function () use ($d, $actorId) {
            Setting::putMany($d->profile());

            if ($this->ai->enabled() !== $d->aiEnabled) {
                $row = AiSetting::query()->first() ?? new AiSetting;
                $row->forceFill(['ai_enabled' => $d->aiEnabled])->save();
                $this->ai->forget();
            }
            if ($this->growth->enabled() !== $d->growthEnabled) {
                $this->growth->set($d->growthEnabled);
                $this->log->log($d->growthEnabled ? 'growth.enabled' : 'growth.disabled', 'admin', $actorId, null, null, 'ok', ['admin_id' => $actorId]);
            }
        });
        Setting::flush();
    }
}
