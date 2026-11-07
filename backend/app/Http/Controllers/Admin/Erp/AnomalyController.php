<?php

namespace App\Http\Controllers\Admin\Erp;

use App\Services\Erp\Audit\AnomalyService;
use App\Services\Erp\Reports\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** ERP anomalies (read-only): the full list behind the hub alerts, with severity/code filters and a CSV export. */
class AnomalyController extends ErpController
{
    /** @return Collection<int,array> */
    private function rows(Request $r): Collection
    {
        $this->allow('erp.audit.view');
        $r->validate(['severity' => ['nullable', 'in:high,medium,low'], 'code' => ['nullable', 'string', 'max:40'], 'deep' => ['nullable', 'boolean']]);

        return app(AnomalyService::class)->detect($r->boolean('deep'))
            ->when($r->filled('severity'), fn ($c) => $c->where('severity', $r->input('severity')))
            ->when($r->filled('code'), fn ($c) => $c->where('code', $r->input('code')))
            ->values();
    }

    public function anomalies(Request $r)
    {
        return view('admin.erp.anomalies', ['rows' => $this->rows($r), 'q' => $r->query()]);
    }

    public function exportAnomalies(Request $r)
    {
        $rows = $this->rows($r);

        return Csv::download('erp-anomalies-'.now()->format('Ymd').'.csv', ['severity', 'code', 'title', 'count', 'sample_entry_id', 'detail'],
            $rows->map(fn ($x) => [$x['severity'], $x['code'], __('erp_audit.an.'.$x['code'])[0], $x['count'], $x['entry_id'], $x['detail']])->all());
    }
}
