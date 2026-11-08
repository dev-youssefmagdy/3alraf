<?php

namespace App\Http\Requests\Storefront;

use App\Services\GiftFinder\GiftFinderService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GiftFinderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'budget' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'occasion' => ['nullable', Rule::in(GiftFinderService::OCCASIONS)],
            'recipient' => ['nullable', Rule::in(GiftFinderService::RECIPIENTS)],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'restrictions' => ['nullable', 'string', 'max:200'],
            'type' => ['nullable', Rule::in(GiftFinderService::TYPES)],
            'sort' => ['nullable', Rule::in(GiftFinderService::SORTS)],
        ];
    }

    public function attributes(): array
    {
        return trans('gift_finder.fields');
    }
}
