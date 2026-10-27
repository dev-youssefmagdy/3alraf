<?php

namespace App\Http\Requests\Api\Integration;

use App\Services\Growth\InteractionService as S;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InteractionIngestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['channel' => ['required', Rule::in(S::CHANNELS)], 'external_id' => ['required', 'string', 'max:120'], 'thread_id' => ['nullable', 'string', 'max:120'], 'product_id' => ['nullable', 'integer', 'min:1'],
            'text' => ['required', 'string', 'max:5000'], 'language' => ['nullable', 'string', 'max:5'], 'consent' => ['nullable', 'boolean'], 'opted_out' => ['nullable', 'boolean'], 'received_at' => ['nullable', 'date'],
            'category' => ['nullable', Rule::in(S::CATEGORIES)], 'intent' => ['nullable', Rule::in(S::INTENTS)], 'urgency' => ['nullable', Rule::in(S::URGENCY)], 'sentiment' => ['nullable', Rule::in(S::SENTIMENT)]];
    }
}
