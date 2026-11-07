<?php

// ERP anomalies (hub alerts + Anomalies page) and the exchange-rate card of ERP settings.
return [
    'hub_empty_title' => 'No ERP pages available to you yet',
    'f' => ['all' => 'All', 'filter' => 'Filter', 'deep' => 'Include deep checks (ledger vs Net Profit report, slower)'],
    'export_csv' => 'Export CSV',
    'col' => ['detail' => 'Detail', 'severity' => 'Severity', 'check' => 'Check', 'count' => 'Count'],
    'severity' => ['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'], 'no_anomalies' => 'No anomalies detected.', 'entry' => 'Entry',
    'an' => [
        'unbalanced_entry' => ['Unbalanced journal entry', 'Posted entry whose debits differ from credits (must be impossible).'],
        'old_draft' => ['Old draft entries', 'Draft journals older than 7 days that were never posted or deleted.'],
        'posting_failed' => ['Failed automatic postings', 'posting.failed audit rows in the last 30 days (a business operation was not recorded in the ledger).'],
        'backdated_entry' => ['Backdated entries', 'Entries posted more than 3 days after the end of the period they belong to.'],
        'negative_cash' => ['Negative cash balance', 'Cash, bank, POS, gateway or wallet account with a credit balance.'],
        'negative_inventory' => ['Negative inventory balance', 'Inventory ledger account with a credit balance.'],
        'negative_stock' => ['Negative stock levels', 'Stock rows with on-hand below zero.'],
        'control_mismatch' => ['Control account mismatch', 'Receivable/payable control balance differs from the sum of its posted lines (party ledgers).'],
        'order_without_journal' => ['Orders without a journal', 'Confirmed orders (after the ledger went live) with no journal entry.'],
        'large_manual' => ['Large manual journals', 'Manual or adjusting entries at or above the review threshold.'],
        'period_reopened' => ['Reopened periods', 'Periods or years reopened in the last 90 days.'],
        'profit_mismatch' => ['Ledger vs Net Profit report', 'Ledger net profit differs from the Net Profit report (depreciation or manual revenue explain small gaps).'],
    ],
    'rates' => ['title' => 'Exchange rates', 'hint' => '1 unit of the first currency = rate units of the second, from the effective date. Reference rates for documents in a foreign currency; the ledger stays in the shop currency and is never converted automatically.', 'from' => 'From', 'to' => 'To', 'rate' => 'Rate', 'date' => 'Effective date'],
    'alerts' => ['title' => 'Alerts', 'none' => 'No alerts - the books look healthy.'],
];
