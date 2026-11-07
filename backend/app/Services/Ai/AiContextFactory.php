<?php

namespace App\Services\Ai;

use App\Models\AiSetting;

class AiContextFactory
{
    /** Country/locale/currency-aware context of the shop (General settings + AI brand voice). */
    public function make(): AiContext
    {
        $s = AiSetting::query()->first();

        return new AiContext(setting('name'), setting('country'), setting('currency_code'), setting('default_locale'),
            array_values((array) setting('locales', [])), setting('timezone'), $s?->brand_voice, array_values((array) ($s?->forbidden_words ?? [])));
    }
}
