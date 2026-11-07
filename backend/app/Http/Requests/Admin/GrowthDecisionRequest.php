<?php

namespace App\Http\Requests\Admin;

class GrowthDecisionRequest extends BaseRequest
{
    public function rules(): array
    {
        $reject = $this->routeIs('admin.growth.approvals.reject');

        return ['reason' => [$reject ? 'required' : 'nullable', 'string', 'max:500']];
    }

    public function attributes(): array
    {
        return trans('growth_hub.fields');
    }
}
