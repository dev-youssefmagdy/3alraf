<?php

namespace App\Http\Requests\Tenant;

use App\Enums\BannerPlacement;
use App\Models\Banner;
use App\Support\SafeUrl;
use Illuminate\Validation\Rule;

class BannerRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->translatable('title', false) + [
            'placement' => ['required', Rule::enum(BannerPlacement::class)],
            'images' => ['nullable', 'array'],
            'images.*' => ['nullable', 'image', 'max:4096'],
            'remove_images' => ['nullable', 'array'],
            'link_url' => ['nullable', 'string', 'max:500', fn ($attr, $value, $fail) => SafeUrl::isSafe($value) ? null : $fail(__('ops.link_url_invalid'))],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** At least one locale image must remain. */
    public function after(): array
    {
        return [function ($v) {
            $existing = $this->id() ? (Banner::find($this->id())?->images ?? []) : [];
            $remove = array_keys(array_filter((array) $this->input('remove_images')));
            $kept = array_diff_key($existing, array_flip($remove));
            if (! $kept && ! array_filter((array) $this->file('images'))) {
                $v->errors()->add('images', __('ops.banner_image_required'));
            }
        }];
    }
}
