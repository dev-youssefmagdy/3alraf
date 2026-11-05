<?php

namespace App\Http\Controllers\Central\Erp;

class HubController extends CentralErpController
{
    /** [key, icon path, route, permission] - titles/descriptions in erp_central.cards.{key} */
    public const CARDS = [
        ['overview', 'M3 21h18M5 21V7l7-4 7 4v14', 'central.erp.overview', 'erp_central.reports'],
        ['performance', 'M4 19V9m5 10V5m5 14v-7m5 7V8', 'central.erp.performance', 'erp_central.reports'],
        ['consolidated', 'M12 3v18M5 7h14M7 7l-3 7a3 3 0 006 0zM17 7l-3 7a3 3 0 006 0z', 'central.erp.consolidated', 'erp_central.reports'],
        ['transactions', 'M9 12h6M9 16h6M7 3h8l4 4v14H7z', 'central.erp.transactions', 'erp_central.reports'],
        ['parties', 'M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M10 10a4 4 0 100-8 4 4 0 000 8z', 'central.erp.parties', 'erp_central.reports'],
        ['inventory', 'M20 7l-8-4-8 4m16 0v10l-8 4m8-14l-8 4m0 10L4 17V7m8 14V11', 'central.erp.inventory', 'erp_central.reports'],
        ['periods', 'M8 7V3m8 4V3M4 11h16M5 5h14v16H5z', 'central.erp.periods', 'erp_central.reports'],
        ['anomalies', 'M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z', 'central.erp.anomalies', 'erp_central.audit'],
        ['migration', 'M8 7h12M8 7l3-3M8 7l3 3M16 17H4m12 0l-3-3m3 3l-3 3', 'central.erp.migration', 'erp_central.audit'],
        ['audit', 'M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z', 'central.erp.audit', 'erp_central.audit'],
    ];

    public function index()
    {
        $this->allow('erp_central.view');
        $user = auth('central')->user();
        $cards = collect(self::CARDS)->filter(fn ($c) => $user->can($c[3]))->map(fn ($c) => ['key' => $c[0], 'icon' => $c[1], 'url' => route($c[2])])->values();

        return view('central.erp.hub', compact('cards'));
    }
}
