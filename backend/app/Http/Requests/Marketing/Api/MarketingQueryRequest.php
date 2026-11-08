<?php

namespace App\Http\Requests\Marketing\Api;

use App\DTOs\Marketing\MarketingQuery;
use App\Support\Marketing\MarketingResponse;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base query of every GET /api/internal/marketing/* endpoint: `from` / `to` (Y-m-d, to ≥ from, at most marketing.max_range_days days),
 * `page` ≥ 1, `per_page` 1..marketing.per_page_max. Authorisation = the `marketing.token:{scope}` route middleware. 422 in the API envelope.
 */
class MarketingQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.(int) config('marketing.per_page_max', 100)],
        ], $this->extraRules());
    }

    /** Endpoint-specific filters. */
    protected function extraRules(): array
    {
        return [];
    }

    public function withValidator(Validator $v): void
    {
        $v->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $q = MarketingQuery::make(...$this->range());
            $max = (int) config('marketing.max_range_days', 366);
            if ($q->days() > $max) {
                $v->errors()->add('to', __('marketing.api.range_too_long', ['max' => $max]));
            }
            if ($q->from > now()->addDay()->toDateString()) {
                $v->errors()->add('from', __('marketing.api.range_in_future'));
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(MarketingResponse::error(422, 'validation_failed', $validator->errors()->first(), $validator->errors()->toArray()));
    }

    /** Endpoint filters (validated keys beyond the common ones). */
    protected function filters(): array
    {
        return array_intersect_key($this->validated(), $this->extraRules());
    }

    /** [from, to] from the input; `from` alone ⇒ to = from + default range (capped at today, never before from). */
    private function range(): array
    {
        $from = $this->input('from') ?: null;
        $to = $this->input('to') ?: null;
        if ($from && ! $to) {
            $to = max($from, min(now()->toDateString(), CarbonImmutable::parse($from)->addDays((int) config('marketing.default_range_days', 30) - 1)->toDateString()));
        }

        return [$from, $to];
    }

    /** Call after validation (controllers). */
    public function toQuery(): MarketingQuery
    {
        [$from, $to] = $this->range();

        return MarketingQuery::make($from, $to, (int) $this->input('page', 1), $this->filled('per_page') ? (int) $this->input('per_page') : null, $this->filters());
    }
}
