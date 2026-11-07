<?php

namespace App\Http\Controllers\Admin\Erp;

class HubController extends ErpController
{
    /** [title key, description key, icon path, route|null (null = planned), permission] */
    public const CARDS = [
        ['accounts', 'M4 6h16M4 12h16M4 18h10', 'admin.erp.accounts.index', 'erp.accounts.view'],
        ['journals', 'M9 12h6M9 16h6M7 3h8l4 4v14H7z', 'admin.erp.journals.index', 'erp.journals.view'],
        ['periods', 'M8 7V3m8 4V3M4 11h16M5 5h14v16H5z', 'admin.erp.periods.index', 'erp.periods.view'],
        ['trial_balance', 'M12 3v18M5 7h14M7 7l-3 7a3 3 0 006 0zM17 7l-3 7a3 3 0 006 0z', 'admin.erp.reports.show:trial-balance', 'erp.reports.view'],
        ['ledger', 'M5 4h14v16H5zM8 8h8M8 12h8M8 16h5', 'admin.erp.reports.show:general-ledger', 'erp.reports.view'],
        ['profit_loss', 'M3 17l6-6 4 4 8-8M15 7h6v6', 'admin.erp.reports.show:profit-loss', 'erp.reports.view'],
        ['balance_sheet', 'M4 19V9m5 10V5m5 14v-7m5 7V8', 'admin.erp.reports.show:balance-sheet', 'erp.reports.view'],
        ['expenses', 'M12 8c-2 0-3 1-3 2s1 2 3 2 3 1 3 2-1 2-3 2m0-10V6m0 12v-2', 'admin.expenses.index', 'expenses.view'],
        ['customers', 'M16 11a4 4 0 10-8 0 4 4 0 008 0zM4 21a8 8 0 0116 0', 'admin.erp.parties.index:type=customer', 'erp.accounts.view'],
        ['suppliers', 'M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M10 10a4 4 0 100-8 4 4 0 000 8z', 'admin.erp.parties.index:type=supplier', 'erp.accounts.view'],
        ['cash_bank', 'M3 10h18M5 10V20h14V10M12 3l9 5H3z', 'admin.erp.cash-bank.index', 'erp.accounts.view'],
        ['owners', 'M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z', 'admin.erp.owners.index', 'erp.owners.view|erp.owners.manage'],
        ['distribution', 'M12 3v18M6 9l6-6 6 6', 'admin.erp.profits.index', 'erp.profits.view'],
        ['assets', 'M3 21h18M5 21V7l7-4 7 4v14', 'admin.erp.assets.index', 'erp.assets.view|erp.assets.manage'],
        ['branches', 'M3 9l1-5h16l1 5M5 9v11h14V9M9 20v-6h6v6', 'admin.erp.branches.index', 'erp.branches.view'],
        ['dashboard', 'M3 12l9-9 9 9M5 10v10h14V10', 'admin.erp.dashboard', 'erp.dashboard.view'],
        ['reconciliation', 'M5 13l4 4L19 7', 'admin.erp.reports.show:reconciliation', 'erp.reports.view'],
        ['reports', 'M4 4h16v16H4zM8 9h8M8 13h8M8 17h4', 'admin.erp.reports.index', 'erp.reports.view'],
        ['anomalies', 'M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z', 'admin.erp.anomalies', 'erp.audit.view'],
        ['audit', 'M9 12l2 2 4-4M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z', 'admin.erp.audit.index', 'erp.audit.view'],
        ['migration', 'M8 7h12M8 7l3-3M8 7l3 3M16 17H4m12 0l-3-3m3 3l-3 3', 'admin.erp.migration.index', 'erp.settings.manage'],
        ['settings', 'M12 15a3 3 0 100-6 3 3 0 000 6zM19 12l2-1-2-4-2 1-2-1-1-2h-4l-1 2-2 1-2-1-2 4 2 1v2l-2 1 2 4 2-1 2 1 1 2h4l1-2 2-1 2 1 2-4-2-1z', 'admin.erp.settings.edit', 'erp.settings.manage'],
    ];

    public function index()
    {
        $this->allow('erp.access'); // ERP-028: any ERP permission opens the hub (a reports-only accountant navigates from here)
        $this->ensureSetup();
        $user = auth('admin')->user();
        $cards = collect(self::CARDS)->filter(fn ($c) => collect(explode('|', $c[3]))->contains(fn ($p) => $user->can($p)))->map(function ($c) {
            [$route, $param] = array_pad(explode(':', (string) $c[2], 2), 2, null);

            $args = [];
            if ($param) {
                $args = str_contains($param, '=') ? [explode('=', $param, 2)[0] => explode('=', $param, 2)[1]] : ['report' => $param];
            }

            return ['key' => $c[0], 'icon' => $c[1], 'url' => $c[2] ? route($route, $args) : null];
        })->values();

        $alerts = $user->can('erp.dashboard.view') || $user->can('erp.settings.manage') ? app(\App\Services\Erp\Audit\AnomalyService::class)->detect() : null;

        return view('admin.erp.hub', compact('cards', 'alerts'));
    }
}
