<?php

namespace App\Http\Requests\Marketer;

use App\Http\Requests\Admin\BaseRequest;
use App\Support\Marketing\MetaDraftRules;

/**
 * Meta campaign draft form of the marketer panel (create + edit, AJAX validate first). Schema = MetaDraftRules (same as the internal API).
 * Panel conveniences: interests / cities / countries as comma separated text, KPI rows kpi_key[] / kpi_value[], placements.automatic checkbox.
 */
class MetaDraftRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        $csv = fn ($v) => is_string($v) ? array_values(array_filter(array_map('trim', explode(',', $v)), fn ($x) => $x !== '')) : $v;
        $aud = (array) $this->input('audience', []);
        $loc = (array) $this->input('locations', []);
        if (isset($aud['interests'])) {
            $aud['interests'] = $csv($aud['interests']);
        }
        foreach (['countries', 'cities'] as $k) {
            if (isset($loc[$k])) {
                $loc[$k] = $csv($loc[$k]);
            }
        }
        $kpis = [];
        foreach ((array) $this->input('kpi_key', []) as $i => $k) {
            if (is_string($k) && trim($k) !== '' && ($v = $this->input('kpi_value.'.$i)) !== null && $v !== '') {
                $kpis[trim($k)] = $v;
            }
        }
        $pl = (array) $this->input('placements', []);
        if (array_key_exists('automatic', $pl)) {   // the form always sends it (data-bool); absent = default (automatic)
            $pl['automatic'] = filter_var($pl['automatic'], FILTER_VALIDATE_BOOL);
        }
        $this->merge(['audience' => $aud, 'locations' => $loc, 'placements' => $pl] + ($this->has('kpi_key') ? ['expected_kpis' => $kpis] : []));
    }

    public function rules(): array
    {
        return MetaDraftRules::rules() + ['kpi_key' => ['nullable', 'array'], 'kpi_value' => ['nullable', 'array'], 'submit' => ['nullable', 'boolean']];
    }

    public function attributes(): array
    {
        return parent::attributes() + (array) trans('marketing.meta.fields');
    }

    public function draftInput(): array
    {
        return array_diff_key($this->validated(), array_flip(['kpi_key', 'kpi_value', 'submit']));
    }
}
