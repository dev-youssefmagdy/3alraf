<?php

namespace App\Http\Requests\Tenant;

use App\Services\Growth\GrowthConfig;

class GrowthSettingsRequest extends BaseRequest
{
    public function rules(): array
    {
        $r = [];
        foreach (GrowthConfig::OVERRIDABLE as $k => [$min, $max]) {
            $r[$k] = ['nullable', 'numeric', 'min:'.$min, 'max:'.$max];
        }

        return $r + ['auto_reply_allowlist' => ['nullable', 'string', 'max:2000']];
    }

    public function attributes(): array
    {
        return trans('growth_hub.settings.fields');
    }
}
