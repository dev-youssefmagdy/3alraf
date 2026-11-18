<?php

namespace App\Http\Requests\Marketing\Api;

use App\Support\Marketing\MarketingResponse;
use App\Support\Marketing\UnknownFields;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/** POST image-embeddings/fill-missing (scope embeddings:write): optional `limit`. Unknown fields ⇒ 422. */
class FillMissingEmbeddingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.(int) config('image_search.fill_limit_max', 5000)]];
    }

    public function withValidator(Validator $v): void
    {
        $v->after(function (Validator $v) {
            foreach (UnknownFields::find($this->all(), array_keys($this->rules())) as $k) {
                $v->errors()->add($k, __('marketing.suggestions.errors.unknown_field', ['field' => $k]));
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(MarketingResponse::error(422, 'validation_failed', $validator->errors()->first(), $validator->errors()->toArray()));
    }
}
