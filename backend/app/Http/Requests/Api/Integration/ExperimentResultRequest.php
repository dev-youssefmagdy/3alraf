<?php

namespace App\Http\Requests\Api\Integration;

use Illuminate\Foundation\Http\FormRequest;

class ExperimentResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['metric' => ['required', 'string', 'max:100'], 'value' => ['nullable', 'numeric', 'between:-999999999999,999999999999'], 'spend' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'recorded_on' => ['nullable', 'date'], 'note' => ['nullable', 'string', 'max:300']];
    }
}
