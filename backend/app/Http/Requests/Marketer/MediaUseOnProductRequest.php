<?php

namespace App\Http\Requests\Marketer;

use App\Http\Requests\Admin\BaseRequest;

/** Use an approved library image on the product: gallery / main image / one variant's image. Permission: route middleware + service. */
class MediaUseOnProductRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'target' => ['required', 'in:gallery,main,variant'],
            'variant_id' => ['required_if:target,variant', 'nullable', 'integer', 'min:1'],
        ];
    }
}
