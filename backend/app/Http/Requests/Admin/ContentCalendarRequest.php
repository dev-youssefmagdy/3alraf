<?php

namespace App\Http\Requests\Admin;

use App\Services\Growth\ContentCalendarService;
use Illuminate\Validation\Rule;

class ContentCalendarRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'content_asset_id' => ['required', 'integer', 'exists:content_assets,id'],
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
