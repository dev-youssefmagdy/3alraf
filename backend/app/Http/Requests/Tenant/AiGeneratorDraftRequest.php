<?php

namespace App\Http\Requests\Tenant;

/** Inline edit of a draft; the service re-validates and normalises every field, so only the shape is checked here. */
class AiGeneratorDraftRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['draft' => ['required', 'array'], 'draft.items' => ['nullable', 'array', 'max:30'], 'draft.items.*' => ['array'], 'draft.product_ids' => ['nullable', 'array', 'max:50']];
    }
}
