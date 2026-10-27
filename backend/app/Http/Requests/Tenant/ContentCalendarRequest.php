<?php

namespace App\Http\Requests\Tenant;

use App\Services\Growth\ContentCalendarService;
use Illuminate\Validation\Rule;

class ContentCalendarRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'content_asset_id' => ['required', 'integer', 'exists:content_assets,id,tenant_id,'.current_tenant()->id],
            'channel' => ['required', Rule::in(ContentCalendarService::CHANNELS)],
            'scheduled_for' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function attributes(): array
    {
        return trans('growth_hub.content.fields');
    }
}
