<?php

namespace App\Http\Requests\Marketing\Api;

use App\Enums\Marketing\SuggestionPriority;
use App\Services\Marketing\Suggestions\Contracts\SuggestionType;
use App\Support\Marketing\MarketingResponse;
use App\Support\Marketing\UnknownFields;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Base of the suggestion write requests (API, scope suggestions:write:{type}): common fields + the type handler's payload schema; unknown fields
 * (anything not declared by those rules) ⇒ 422 `validation_failed` with errors["field"] = unknown_field. Errors use the API envelope.
 */
abstract class SuggestionWriteRequest extends FormRequest
{
    abstract protected function handler(): ?SuggestionType;

    abstract protected function targetType(): ?string;

    abstract protected function creating(): bool;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $req = $this->creating() ? 'required' : 'sometimes';
        $common = [
            'title' => [$req, 'string', 'min:3', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'reasoning' => ['nullable', 'string', 'max:5000'],
            'priority' => ['nullable', Rule::in(SuggestionPriority::values())],
            'expires_at' => ['nullable', 'date', 'after:now', 'before:'.now()->addYear()->toDateString()],
            'payload' => [$req, 'array'],
        ];
        if ($this->creating()) {
            $common += [
                'target_entity_type' => ['nullable', 'string', Rule::in($this->handler()?->targetTypes() ?? [])],
                'target_entity_id' => ['nullable', 'integer', 'min:1'],
                'source' => ['nullable', Rule::in(['mcp', 'scheduled'])],
                'source_meta' => ['nullable', 'array'],
                'source_meta.model' => ['nullable', 'string', 'max:80'], 'source_meta.tool' => ['nullable', 'string', 'max:80'],
                'source_meta.run_id' => ['nullable', 'string', 'max:80'], 'source_meta.conversation_id' => ['nullable', 'string', 'max:80'],
                'source_meta.workflow' => ['nullable', 'string', 'max:80'],
                'source_data' => ['nullable', 'array'],
                'force_reason' => ['nullable', 'string', 'min:10', 'max:500'],
                'draft' => ['nullable', 'boolean'],
            ];
        } else {
            $common['note'] = ['nullable', 'string', 'max:500'];
        }
        // PATCH without a payload changes only common fields: the payload schema (with its required keys) applies only when a payload is sent
        $payload = ($this->creating() || $this->has('payload')) ? ($this->handler()?->rules($this->targetType()) ?? []) : [];
        unset($payload['payload']);

        return $common + $payload;
    }

    public function withValidator(Validator $v): void
    {
        $v->after(function (Validator $v) {
            foreach (UnknownFields::find($this->all(), array_keys($this->rules()), ['source_data']) as $k) {
                $v->errors()->add($k, __('marketing.suggestions.errors.unknown_field', ['field' => $k]));
            }
            if ($this->has('source_data') && strlen((string) json_encode($this->input('source_data'))) > (int) config('marketing.suggestions.source_data_max_bytes', 20000)) {
                $v->errors()->add('source_data', __('marketing.suggestions.errors.source_data_too_big'));
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(MarketingResponse::error(422, 'validation_failed', $validator->errors()->first(), $validator->errors()->toArray()));
    }
}
