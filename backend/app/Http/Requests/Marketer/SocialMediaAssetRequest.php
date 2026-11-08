<?php

namespace App\Http\Requests\Marketer;

use App\DTOs\Marketing\SocialMediaAssetData;
use App\Http\Requests\Admin\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Private social-media library: create (upload / post draft), metadata edit, new version. Permissions: route middleware (ai_media.upload /
 * ai_media.edit). The file is checked again by SocialMediaLibraryService::inspect (real MIME, decodable image, video signature, limits).
 * Comma separated `tags` / space separated `hashtags` from the panel form are accepted as well as arrays.
 */
class SocialMediaAssetRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['tags' => '/\s*,\s*/', 'hashtags' => '/[\s,]+/'] as $k => $re) {
            if (is_string($this->input($k))) {
                $this->merge([$k => array_values(array_filter(preg_split($re, trim($this->input($k))) ?: []))]);
            }
        }
    }

    public function rules(): array
    {
        $mode = $this->route()?->getName() ?? '';
        $creating = str_ends_with($mode, '.store');
        $version = str_ends_with($mode, '.version');
        $mimes = implode(',', array_merge((array) config('marketing.media.image_mimes'), (array) config('marketing.media.video_mimes')));
        $r = [
            'title' => [$creating ? 'nullable' : 'sometimes', 'nullable', 'string', 'max:200'],
            'caption' => ['sometimes', 'nullable', 'array'], 'caption.*' => ['nullable', 'string', 'max:2200'],
            'platforms' => ['sometimes', 'nullable', 'array'], 'platforms.*' => [Rule::in(SocialMediaAssetData::PLATFORMS)],
            'format' => ['sometimes', 'nullable', Rule::in(SocialMediaAssetData::FORMATS)],
            'aspect_ratio' => ['sometimes', 'nullable', Rule::in(SocialMediaAssetData::RATIOS)],
            'purpose' => ['sometimes', 'nullable', 'string', 'max:500'],
            'campaign' => ['sometimes', 'nullable', 'string', 'max:120'],
            'tags' => ['sometimes', 'nullable', 'array', 'max:20'], 'tags.*' => ['string', 'max:60'],
            'hashtags' => ['sometimes', 'nullable', 'array', 'max:30'], 'hashtags.*' => ['string', 'max:60', 'regex:/^#?[\pL\pN_]+$/u'],
            'cta' => ['sometimes', 'nullable', 'string', 'max:200'],
            'brief' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'suggested_publish_at' => ['sometimes', 'nullable', 'date'],
            'canva_url' => ['sometimes', 'nullable', 'string', 'max:500', fn ($a, $v, $fail) => $v === null || \App\Support\Marketing\CanvaUrl::normalize($v) ? null : $fail(__('marketing.suggestions.errors.canva_url'))],
            'duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3600'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
        if ($creating) {
            $r['product_id'] = ['required', 'integer', $this->existsRule('products', true)];
            $r['kind'] = ['nullable', Rule::in(['image', 'video', 'post'])];
            $r['file'] = ['nullable', 'required_unless:kind,post', 'file', 'max:'.(int) config('marketing.media.video_max_kb'), 'mimetypes:'.$mimes];
        }
        if ($version) {
            $r['file'] = ['required', 'file', 'max:'.(int) config('marketing.media.video_max_kb'), 'mimetypes:'.$mimes];
        }

        return $r;
    }

    public function attributes(): array
    {
        return parent::attributes() + (array) trans('marketing.media.fields');
    }

    public function assetData(): SocialMediaAssetData
    {
        return SocialMediaAssetData::fromArray(array_intersect_key($this->validated(), array_flip(['kind', 'title', 'caption', 'platforms', 'format', 'aspect_ratio',
            'purpose', 'campaign', 'tags', 'hashtags', 'cta', 'brief', 'suggested_publish_at', 'canva_url', 'duration_seconds', 'note'])));
    }
}
