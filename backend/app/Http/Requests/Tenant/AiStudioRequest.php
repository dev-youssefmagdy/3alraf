<?php

namespace App\Http\Requests\Tenant;

use App\Enums\SocialPlatform;
use App\Services\Ai\AiAccess;
use Illuminate\Validation\Rule;

class AiStudioRequest extends BaseRequest
{
    public const CAMPAIGNS = ['flash_sale', 'seasonal', 'new_arrival', 'clearance', 'restock', 'abandoned_cart', 'review_spotlight'];

    public function rules(): array
    {
        $tid = current_tenant()->id;
        $access = app(AiAccess::class);
        $ids = fn ($kind) => Rule::in($access->modelsFor(current_tenant(), $kind)->pluck('id')->all());

        return [
            'mode' => ['required', Rule::in(['social', 'marketing'])],
            'platform' => ['required', Rule::enum(SocialPlatform::class)],
            'goal' => ['required', 'string', 'max:200'],
            'tone' => ['required', 'string', 'max:60'],
            'languages' => ['required', 'array', 'min:1', 'max:4'],
            'languages.*' => ['string', Rule::in((array) current_tenant()->locales)],   // tenant locales only
            'cta' => ['nullable', 'string', 'max:120'],
            'format' => ['required', Rule::in(['text', 'image', 'video', 'all'])],
            'product_ids' => ['nullable', 'array', 'max:5'],
            'product_ids.*' => ['integer', 'exists:tenant_products,product_id,tenant_id,'.$tid],
            'bundle_id' => ['nullable', 'integer', $this->tenantExists('bundles', true)],
            'flash_sale_id' => ['nullable', 'integer', $this->tenantExists('flash_sales')],
            'brief' => ['nullable', 'string', 'max:1500'],
            'campaign_type' => ['nullable', Rule::in(self::CAMPAIGNS)],
            'event' => ['nullable', 'string', 'max:40'],
            'variants' => ['nullable', 'integer', 'min:1', 'max:3'],
            'ai_model_id' => ['nullable', 'integer', $ids('text')],
            'image_model_id' => ['nullable', 'integer', $ids('image')],
            'video_model_id' => ['nullable', 'integer', $ids('video')],
        ];
    }

    public function withValidator($v): void
    {
        $v->after(function ($v) {
            $d = $this->all();
            if (! $this->filled('brief') && ! $this->filled('bundle_id') && ! $this->filled('flash_sale_id') && ! array_filter((array) ($d['product_ids'] ?? []))) {
                $v->errors()->add('brief', __('ai.studio.need_subject'));
            }
            $f = $this->input('format');
            if (in_array($f, ['video', 'all'], true) && ! app(AiAccess::class)->modelsFor(current_tenant(), 'video')->count()) {
                $v->errors()->add('format', __('ai.studio.no_video_model'));
            }
            if (in_array($f, ['image', 'all'], true) && ! app(AiAccess::class)->modelsFor(current_tenant(), 'image')->count()) {
                $v->errors()->add('format', __('ai.studio.no_image_model'));
            }
            if (in_array($f, ['video', 'all'], true) && ! SocialPlatform::tryFrom((string) $this->input('platform'))?->supportsVideo()) {
                $v->errors()->add('format', __('ai.studio.no_video_platform'));
            }
        });
    }

    public function attributes(): array
    {
        return trans('ai.studio.fields');
    }
}
