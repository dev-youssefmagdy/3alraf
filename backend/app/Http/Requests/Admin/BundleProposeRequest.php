<?php

namespace App\Http\Requests\Tenant;

use App\Services\Growth\BundleProposalService;
use Illuminate\Validation\Rule;

class BundleProposeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(BundleProposalService::MODES)],
            'budgets' => ['nullable', 'string', 'max:100'],
            'event_id' => ['nullable', 'integer', 'exists:seasonal_events,id,tenant_id,'.current_tenant()->id],
        ];
    }

    public function attributes(): array
    {
        return trans('growth_hub.bundles.fields');
    }
}
