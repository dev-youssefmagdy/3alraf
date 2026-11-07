<?php

namespace App\Services;

use App\DTOs\Orders\CodFeeConfig;
use App\Models\Setting;

/** Reads/writes the COD extra fee keys of the `settings` table (other keys are untouched). */
class CodFeeSettingsService
{
    public function get(): CodFeeConfig
    {
        return CodFeeConfig::fromSettings(Setting::map());
    }

    public function update(CodFeeConfig $data): CodFeeConfig
    {
        Setting::putMany($data->toSettings());

        return $this->get();
    }
}
