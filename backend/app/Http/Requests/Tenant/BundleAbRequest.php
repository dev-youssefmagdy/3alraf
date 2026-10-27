<?php

namespace App\Http\Requests\Tenant;

class BundleAbRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'bundle_id' => ['required', 'integer', 'exists:bundles,id,tenant_id,'.current_tenant()->id],
            'name' => ['nullable', 'array'], 'name.*' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'array'], 'description.*' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'max:2048'],
            'split_b' => ['required', 'integer', 'between:5,95'],
            'min_sample' => ['required', 'integer', 'between:20,100000'],
        ];
    }

    public function attributes(): array
    {
        return trans('growth_hub.bundles.fields');
    }
}
