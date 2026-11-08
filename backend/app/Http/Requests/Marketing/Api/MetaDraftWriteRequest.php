<?php

namespace App\Http\Requests\Marketing\Api;

use App\Support\Marketing\MarketingResponse;
use App\Support\Marketing\MetaDraftRules;
use App\Support\Marketing\UnknownFields;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * POST /meta/drafts (create, optional `submit: true`) and PATCH /meta/drafts/{id} (partial: each sent top-level field replaces the stored one)
 * — scope meta:draft (P6). Schema = MetaDraftRules (same as the panel). Unknown fields ⇒ 422. No approve / publish field exists.
 */
class MetaDraftWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    private function creating(): bool
    {
        return $this->isMethod('post');
    }

    public function rules(): array
    {
        return MetaDraftRules::rules(! $this->creating()) + ($this->creating() ? ['submit' => ['sometimes', 'boolean']] : []);
    }

    public function withValidator(Validator $v): void
    {
        $v->after(function (Validator $v) {
            foreach (UnknownFields::find($this->all(), array_keys($this->rules()), ['expected_kpis']) as $k) {
                $v->errors()->add($k, __('marketing.suggestions.errors.unknown_field', ['field' => $k]));
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(MarketingResponse::error(422, 'validation_failed', $validator->errors()->first(), $validator->errors()->toArray()));
    }

    public function draftInput(): array
    {
        return array_diff_key($this->validated(), ['submit' => 1]);
    }
}
