<?php

namespace App\Http\Requests\Tenant;

class GrowthDecisionRequest extends BaseRequest
{
    public function rules(): array
    {
        $reject = $this->routeIs('tenant.growth.approvals.reject');

        return ['reason' => [$reject ? 'required' : 'nullable', 'string', 'max:500']];
    }

    public function attributes(): array
    {
        return trans('growth_hub.fields');
    }
}
