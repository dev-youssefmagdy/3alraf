<?php

namespace App\Http\Requests\Tenant;

use App\Enums\BannerPlacement;
use App\Services\Ai\AiAccess;
use App\Services\Ai\Generators\DraftValidator;
use Illuminate\Validation\Rule;

class AiGeneratorRequest extends BaseRequest
{
    public function rules(): array
    {
        $tid = current_tenant()->id;
        $ids = fn ($kind) => Rule::in(app(AiAccess::class)->modelsFor(current_tenant(), $kind)->pluck('id')->all());

        return [
            'kind' => ['required', Rule::in(DraftValidator::KINDS)],
            'goal' => ['required', 'string', 'max:300'],
            'season' => ['nullable', 'string', 'max:80'],
            'margin_floor' => ['nullable', 'numeric', 'min:0', 'max:95'],
            'product_ids' => ['nullable', 'array', 'max:20'],
            'product_ids.*' => ['integer', 'exists:tenant_products,product_id,tenant_id,'.$tid],
            'brief' => ['nullable', 'string', 'max:1500'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'placement' => ['nullable', Rule::enum(BannerPlacement::class)],
            'ai_model_id' => ['nullable', 'integer', $ids('text')],
            'image_model_id' => ['nullable', 'integer', $ids('image')],
        ];
    }

    public function attributes(): array
    {
        return trans('ai.gen.fields');
    }
}
