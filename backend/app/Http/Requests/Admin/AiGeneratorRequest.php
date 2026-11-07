<?php

namespace App\Http\Requests\Admin;

use App\Enums\BannerPlacement;
use App\Services\Ai\AiAccess;
use App\Services\Ai\ContentGeneratorService;
use App\Services\Ai\Generators\DraftValidator;
use Illuminate\Validation\Rule;

class AiGeneratorRequest extends BaseRequest
{
    public function rules(): array
    {
        $ids = fn ($kind) => Rule::in(app(AiAccess::class)->modelsFor($kind)->pluck('id')->all());

        return [
            'kind' => ['required', Rule::in(array_merge(DraftValidator::KINDS, ContentGeneratorService::KINDS))],
            'goal' => ['required', 'string', 'max:300'],
            'season' => ['nullable', 'string', 'max:80'],
            'margin_floor' => ['nullable', 'numeric', 'min:0', 'max:95'],
            'product_ids' => ['nullable', 'array', 'max:20'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'brief' => ['nullable', 'string', 'max:1500'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'placement' => ['nullable', Rule::enum(BannerPlacement::class)],
            'tone' => ['nullable', Rule::in(['friendly', 'professional', 'playful', 'luxury', 'inspiring', 'informative'])],
            'length' => ['nullable', Rule::in(['short', 'medium', 'long'])],
            'ai_model_id' => ['nullable', 'integer', $ids('text')],
            'image_model_id' => ['nullable', 'integer', $ids('image')],
        ];
    }

    public function withValidator($v): void
    {
        $v->after(function ($v) {
            if ($this->input('kind') !== ContentGeneratorService::PACKAGE) {
                return;
            }
            if (! app(\App\Services\Growth\GrowthFeatures::class)->active('content_factory') || ! $this->user('admin')?->can('growth.manage')) {
                $v->errors()->add('kind', __('growth_hub.content.not_allowed'));
            } elseif (count((array) $this->input('product_ids', [])) !== 1) {
                $v->errors()->add('product_ids', __('growth_hub.content.one_product'));
            }
        });
    }

    public function attributes(): array
    {
        return trans('ai.gen.fields');
    }
}
