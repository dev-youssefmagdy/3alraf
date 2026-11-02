<?php

return [
    'f' => ['party' => 'Party', 'amount' => 'Amount', 'date' => 'Date', 'account' => 'Account', 'ref' => 'Reference', 'note' => 'Note', 'branch' => 'Branch', 'save' => 'Save', 'name' => 'Name',
        'phone' => 'Phone', 'actions' => 'Actions', 'view' => 'Open', 'as_of' => 'As of', 'from' => 'From', 'to' => 'To', 'filter' => 'Filter', 'balance' => 'Balance', 'total' => 'Total',
        'debit' => 'Debit', 'credit' => 'Credit', 'number' => 'Number', 'none' => 'No records yet.', 'status' => 'Status', 'unattributed' => 'Without a party (guest / manual entries)'],
    'roles' => ['cash' => 'Cash on hand', 'bank' => 'Bank', 'pos_cash' => 'POS cash register', 'gateway' => 'Payment gateway', 'wallet' => 'E-wallet', 'payable' => 'Supplier payable'],
    'party' => [
        'customer' => 'Customers', 'supplier' => 'Suppliers', 'customer_one' => 'Customer', 'supplier_one' => 'Supplier',
        'outstanding_customer' => 'Owed by customer', 'outstanding_supplier' => 'Owed to supplier', 'advance' => 'Advance held', 'net' => 'Net balance',
        'ageing' => 'Ageing report', 'statement' => 'Statement', 'opening' => 'Opening balance', 'closing' => 'Closing balance', 'back' => 'Back to list',
        'pay_supplier' => 'Pay supplier', 'receive_customer' => 'Receive from customer', 'advance_only' => 'Book entirely as an advance', 'pay_hint' => 'Partial payments are fine; anything above the open amount becomes an advance.',
        'apply_advance' => 'Apply advance to open balance', 'note_title' => 'Credit / debit note', 'credit_note' => 'Credit note', 'debit_note' => 'Debit note', 'counter' => 'Counter account (optional)',
        'opening_title' => 'Opening balance', 'opening_hint_customer' => 'Amount the customer owed on the start date.', 'opening_hint_supplier' => 'Amount owed to the supplier on the start date.',
        'recon' => 'Reconciliation with the control accounts', 'recon_ok' => 'Reconciled', 'recon_bad' => 'Difference found', 'recon_parties' => 'Sum of party balances', 'recon_control' => 'Control accounts (ledger)',
        'buckets' => ['b0' => '0-30', 'b31' => '31-60', 'b61' => '61-90', 'b90' => '90+', 'credit' => 'Credit / unapplied', 'total' => 'Open total'], 'select' => 'Choose', 'add_hint' => 'Open a customer or supplier from the list.',
        'open_party' => 'Open party', 'search' => 'Select a party',
    ],
    'own' => [
        'owners' => 'Owners', 'add' => 'Add owner', 'edit' => 'Update', 'percent' => 'Ownership %', 'eligible' => 'Eligible for profit distribution', 'user' => 'Linked user (optional)',
        'percent_total' => 'Total ownership allocated', 'capital' => 'Capital', 'drawings' => 'Withdrawals', 'distributions' => 'Distributions', 'equity' => 'Equity balance',
        'txn' => 'Record transaction', 'type' => 'Type', 'cash_account' => 'Cash / bank account', 'statement' => 'Owner statement', 'recent' => 'Recent transactions',
        't' => ['contribution' => 'Capital contribution', 'withdrawal' => 'Withdrawal', 'distribution' => 'Distribution paid'],
        'branch_alloc' => 'Branch capital allocation', 'branch_title' => 'Branch capital allocation', 'branch_hint' => 'Dr branch cash / Cr branch capital, tagged with the branch.',
    ],
    'fa' => [
        'assets' => 'Fixed assets', 'add' => 'Register asset', 'category' => 'Category', 'acquired' => 'Acquisition date', 'cost' => 'Cost', 'salvage' => 'Salvage value', 'life' => 'Useful life (months)',
        'method' => 'Method', 'methods' => ['straight_line' => 'Straight line', 'declining_balance' => 'Declining balance'], 'paid_from' => 'Paid from', 'supplier' => 'Supplier (if on credit)',
        'accumulated' => 'Accumulated depreciation', 'nbv' => 'Net book value', 'status' => ['active' => 'Active', 'disposed' => 'Disposed'], 'depreciate' => 'Run depreciation', 'period' => 'Period (YYYY-MM)',
        'depreciated' => ':n depreciation entries posted.', 'dispose' => 'Dispose', 'proceeds' => 'Sale proceeds', 'gain_loss' => 'Gain / (loss)', 'transfer' => 'Transfer to branch', 'by_category' => 'By category',
        'recon' => 'Ledger reconciliation', 'recon_ok' => 'Register matches the fixed-asset accounts', 'recon_bad' => 'Register differs from the fixed-asset accounts', 'entries' => 'Journal entries',
        'memo' => ['register' => 'Asset registration', 'depreciation' => 'Depreciation :period', 'disposal' => 'Asset disposal', 'transfer' => 'Asset branch transfer'],
    ],
    'memo' => ['supplier_payment' => 'Supplier payment', 'customer_receipt' => 'Customer receipt', 'apply_advance' => 'Advance applied', 'opening' => 'Opening balance', 'credit_note' => 'Credit note', 'debit_note' => 'Debit note'],
    'errors' => [
        'amount_positive' => 'Amount must be greater than zero.', 'bad_account' => 'Invalid account or document type.', 'nothing_to_apply' => 'There is no advance to apply against an open balance.',
        'percent_over' => 'Total ownership cannot exceed 100% (available: :max%).', 'asset_invalid' => 'Invalid asset values (cost, salvage, life or date).', 'asset_disposed' => 'The asset is already disposed.',
    ],
];
