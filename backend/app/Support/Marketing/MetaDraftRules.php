<?php

namespace App\Support\Marketing;

use Illuminate\Validation\Rule;

/**
 * Input schema of a Meta campaign draft (MCP.md §7 "a draft must contain"), shared by the marketer panel form and the internal API
 * (POST/PATCH /api/internal/marketing/meta/drafts). $partial = PATCH (every field optional). Business checks (products active, landing page
 * on this store, budget limits, approved creatives) are done by MetaCampaignDraftService::check().
 */
final class MetaDraftRules
{
    public static function rules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';
        $platforms = (array) config('meta.publisher_platforms');

        return [
            'name' => [$req, 'string', 'min:3', 'max:200'],
            'objective' => [$req, Rule::in(array_keys((array) config('meta.objectives')))],
            'product_ids' => [$req, 'array', 'min:1', 'max:20'], 'product_ids.*' => ['integer', 'min:1'],
            'audience' => ['sometimes', 'nullable', 'array'],
            'audience.description' => ['nullable', 'string', 'max:1000'],
            'audience.age_min' => ['nullable', 'integer', 'min:13', 'max:65'],
            'audience.age_max' => ['nullable', 'integer', 'min:13', 'max:65'],
            'audience.genders' => ['nullable', Rule::in(['all', 'male', 'female'])],
            'audience.interests' => ['nullable', 'array', 'max:25'], 'audience.interests.*' => ['string', 'max:100'],
            'locations' => ['sometimes', 'nullable', 'array'],
            'locations.countries' => ['nullable', 'array', 'max:25'], 'locations.countries.*' => ['string', 'size:2', 'alpha'],
            'locations.cities' => ['nullable', 'array', 'max:50'], 'locations.cities.*' => ['string', 'max:100'],
            'placements' => ['sometimes', 'nullable', 'array'],
            'placements.automatic' => ['nullable', 'boolean'],
            'placements.publisher_platforms' => ['nullable', 'array'], 'placements.publisher_platforms.*' => [Rule::in($platforms)],
            'placements.facebook_positions' => ['nullable', 'array'], 'placements.facebook_positions.*' => [Rule::in((array) config('meta.positions.facebook'))],
            'placements.instagram_positions' => ['nullable', 'array'], 'placements.instagram_positions.*' => [Rule::in((array) config('meta.positions.instagram'))],
            'budget_type' => [$req, Rule::in(['daily', 'lifetime'])],
            'budget_amount' => [$req, 'numeric', 'min:1', 'max:10000000'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3', 'alpha'],
            'start_at' => [$req, 'date'],
            'end_at' => ['sometimes', 'nullable', 'date', 'after:start_at'],
            'creative' => ['sometimes', 'nullable', 'array'],
            'creative.asset_ids' => ['nullable', 'array', 'max:10'], 'creative.asset_ids.*' => ['integer', 'min:1'],
            'creative.canva_url' => ['nullable', 'string', 'max:500', fn ($a, $v, $fail) => $v === null || CanvaUrl::normalize($v) ? null : $fail(__('marketing.suggestions.errors.canva_url'))],
            'creative.notes' => ['nullable', 'string', 'max:2000'],
            'copy' => [$req, 'array'],
            'copy.primary_text' => [$partial ? 'sometimes' : 'required', 'string', 'max:2000'],
            'copy.headline' => ['nullable', 'string', 'max:255'],
            'copy.description' => ['nullable', 'string', 'max:255'],
            'cta' => [$req, Rule::in((array) config('meta.ctas'))],
            'landing_url' => ['sometimes', 'nullable', 'string', 'max:500', fn ($a, $v, $fail) => $v === null || InternalLink::isInternal($v) ? null : $fail(__('marketing.meta.errors.landing_url'))],
            'optimization_goal' => ['sometimes', 'nullable', Rule::in((array) config('meta.optimization_goals'))],
            'expected_kpis' => ['sometimes', 'nullable', 'array', 'max:12'],
            'expected_kpis.*' => ['nullable', fn ($a, $v, $fail) => $v === null || (is_scalar($v) && mb_strlen((string) $v) <= 200) ? null : $fail(__('validation.max.string', ['attribute' => $a, 'max' => 200]))],
            'risks' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'assumptions' => ['sometimes', 'nullable', 'string', 'max:3000'],
        ];
    }
}
