<?php

namespace App\Services\Erp\Accounting;

use App\Models\Erp\Account;

/** Default professional chart of accounts, seeded once and fully editable afterwards. */
class CoaTemplate
{
    /** [code, en, ar, type, subtype, role_key|null, parent code|null, is_group] */
    public const ACCOUNTS = [
        ['1000', 'Assets', 'الأصول', 'asset', null, null, null, true],
        ['1100', 'Current Assets', 'الأصول المتداولة', 'asset', null, null, '1000', true],
        ['1110', 'Cash on Hand', 'النقدية بالصندوق', 'asset', 'cash', 'cash', '1100', false],
        ['1115', 'Cash Registers / POS Cash', 'صناديق نقاط البيع', 'asset', 'cash', 'pos_cash', '1100', false],
        ['1120', 'Bank Accounts', 'الحسابات البنكية', 'asset', 'bank', 'bank', '1100', false],
        ['1130', 'Payment Gateway Balances', 'أرصدة بوابات الدفع', 'asset', 'gateway', 'gateway', '1100', false],
        ['1135', 'E-Wallet Balances', 'أرصدة المحافظ الإلكترونية', 'asset', 'wallet', 'wallet', '1100', false],
        ['1140', 'Carrier COD Receivable', 'مستحقات شركات الشحن (تحصيل)', 'asset', 'receivable', 'carrier_receivable', '1100', false],
        ['1150', 'Inter-branch Transfers (Clearing)', 'تحويلات بين الفروع (وسيط)', 'asset', 'clearing', 'branch_transfer', '1100', false],
        ['1200', 'Accounts Receivable', 'العملاء (ذمم مدينة)', 'asset', 'receivable', 'receivable', '1100', false],
        ['1300', 'Inventory', 'المخزون', 'asset', 'inventory', 'inventory', '1100', false],
        ['1310', 'Inventory in Transit', 'مخزون في الطريق', 'asset', 'inventory', 'inventory_transit', '1100', false],
        ['1320', 'Goods Out for Delivery', 'بضاعة لدى شركات الشحن (قيد التسليم)', 'asset', 'inventory', 'goods_in_delivery', '1100', false],
        ['1330', 'Packaging Supplies', 'مخزون مواد التغليف', 'asset', 'supplies', 'packaging_supplies', '1100', false],
        ['1400', 'Supplier Advances', 'دفعات مقدمة للموردين', 'asset', 'advance', 'supplier_advances', '1100', false],
        ['1410', 'Prepaid Expenses', 'مصروفات مدفوعة مقدماً', 'asset', 'prepaid', 'prepaid', '1100', false],
        ['1420', 'Employee Advances', 'سلف الموظفين', 'asset', 'advance', 'employee_advances', '1100', false],
        ['1500', 'Fixed Assets', 'الأصول الثابتة', 'asset', 'fixed', 'fixed_assets', '1000', false],
        ['1510', 'Accumulated Depreciation', 'مجمع الإهلاك', 'asset', 'contra', 'accumulated_depreciation', '1000', false],
        ['1900', 'Other Assets', 'أصول أخرى', 'asset', null, 'other_assets', '1000', false],

        ['2000', 'Liabilities', 'الالتزامات', 'liability', null, null, null, true],
        ['2100', 'Accounts Payable', 'الموردون (ذمم دائنة)', 'liability', 'payable', 'payable', '2000', false],
        ['2110', 'Customer Advances / Credit', 'دفعات مقدمة من العملاء', 'liability', 'advance', 'customer_advances', '2000', false],
        ['2200', 'Tax / VAT Payable', 'ضريبة القيمة المضافة المستحقة', 'liability', 'tax', 'vat_payable', '2000', false],
        ['2300', 'Shipping & Delivery Payable', 'مستحقات الشحن والتوصيل', 'liability', 'payable', 'shipping_payable', '2000', false],
        ['2400', 'Employee Payables', 'مستحقات الموظفين', 'liability', 'payable', 'employee_payable', '2000', false],
        ['2450', 'Affiliate Commissions Payable', 'عمولات المسوقين المستحقة', 'liability', 'payable', 'affiliate_payable', '2000', false],
        ['2500', 'Loans & Financing', 'القروض والتمويل', 'liability', 'loan', 'loans', '2000', false],
        ['2600', 'Owner Distributions Payable', 'توزيعات الملاك المستحقة', 'liability', 'payable', 'distributions_payable', '2000', false],
        ['2900', 'Other Liabilities', 'التزامات أخرى', 'liability', null, 'other_liabilities', '2000', false],

        ['3000', 'Equity', 'حقوق الملكية', 'equity', null, null, null, true],
        ['3100', "Owners' Capital", 'رأس المال', 'equity', 'capital', 'owner_capital', '3000', false],
        ['3150', 'Branch Capital Allocations', 'تخصيص رأس مال الفروع', 'equity', 'capital', 'branch_capital', '3000', false],
        ['3200', "Owners' Drawings / Withdrawals", 'مسحوبات الملاك', 'equity', 'drawings', 'owner_drawings', '3000', false],
        ['3300', 'Retained Earnings', 'الأرباح المرحلة', 'equity', 'retained', 'retained_earnings', '3000', false],
        ['3400', 'Current Period Earnings (Income Summary)', 'أرباح الفترة الحالية', 'equity', 'summary', 'income_summary', '3000', false],
        ['3500', 'Profit Reserves', 'احتياطيات الأرباح', 'equity', 'reserve', 'reserves', '3000', false],
        ['3900', 'Opening Balance Equity', 'أرصدة افتتاحية', 'equity', 'opening', 'opening_equity', '3000', false],

        ['4000', 'Revenue', 'الإيرادات', 'revenue', null, null, null, true],
        ['4100', 'Product Sales', 'مبيعات المنتجات', 'revenue', 'sales', 'sales_revenue', '4000', false],
        ['4150', 'Sales Returns & Allowances', 'مردودات ومسموحات المبيعات', 'revenue', 'contra', 'sales_returns', '4000', false],
        ['4160', 'Sales Discounts', 'خصومات المبيعات', 'revenue', 'contra', 'sales_discounts', '4000', false],
        ['4200', 'Shipping Revenue', 'إيرادات الشحن', 'revenue', 'shipping', 'shipping_revenue', '4000', false],
        ['4250', 'COD / Payment Fee Income', 'إيرادات رسوم الدفع', 'revenue', 'fees', 'fee_income', '4000', false],
        ['4300', 'Other Operating Revenue', 'إيرادات تشغيلية أخرى', 'revenue', null, 'other_revenue', '4000', false],
        ['4900', 'Other Income / FX Gains', 'إيرادات أخرى وأرباح عملة', 'revenue', 'other', 'other_income', '4000', false],

        ['5000', 'Cost of Goods Sold', 'تكلفة البضاعة المباعة', 'expense', 'cogs', 'cogs', null, false],
        ['5100', 'Inventory Shrinkage / Write-offs', 'عجز وهالك المخزون', 'expense', 'shrinkage', 'inventory_loss', null, false],
        ['6000', 'Operating Expenses', 'المصروفات التشغيلية', 'expense', null, null, null, true],
        ['6100', 'Salaries & Wages', 'الرواتب والأجور', 'expense', 'opex', 'salaries', '6000', false],
        ['6200', 'Rent', 'الإيجار', 'expense', 'opex', 'rent', '6000', false],
        ['6300', 'Marketing & Advertising', 'التسويق والإعلان', 'expense', 'opex', 'marketing', '6000', false],
        ['6350', 'Affiliate Commissions', 'عمولات المسوقين', 'expense', 'opex', 'affiliate_expense', '6000', false],
        ['6400', 'Shipping Expense', 'مصروفات الشحن', 'expense', 'opex', 'shipping_expense', '6000', false],
        ['6500', 'Payment Gateway Fees', 'رسوم بوابات الدفع', 'expense', 'opex', 'gateway_fees', '6000', false],
        ['6600', 'Packaging', 'التغليف', 'expense', 'opex', 'packaging', '6000', false],
        ['6700', 'Utilities', 'المرافق', 'expense', 'opex', 'utilities', '6000', false],
        ['6800', 'Maintenance', 'الصيانة', 'expense', 'opex', 'maintenance', '6000', false],
        ['6850', 'Taxes & Fees', 'الضرائب والرسوم', 'expense', 'opex', 'taxes_fees', '6000', false],
        ['6870', 'Depreciation Expense', 'مصروف الإهلاك', 'expense', 'opex', 'depreciation_expense', '6000', false],
        ['6880', 'Purchase-related Expenses', 'مصروفات المشتريات', 'expense', 'opex', 'purchase_expenses', '6000', false],
        ['6890', 'Bad Debt Expense', 'ديون معدومة', 'expense', 'opex', 'bad_debt', '6000', false],
        ['6900', 'FX Losses', 'خسائر فروق العملة', 'expense', 'opex', 'fx_loss', '6000', false],
        ['6990', 'Other Business Expenses', 'مصروفات أخرى', 'expense', 'opex', 'other_expense', '6000', false],
    ];

    public function seed(): int
    {
        $currency = setting('currency_code');
        $created = 0;
        $ids = [];
        foreach (self::ACCOUNTS as [$code, $en, $ar, $type, $sub, $role, $parent, $group]) {
            $acc = Account::withoutGlobalScopes()->firstOrCreate(['code' => $code], [
                'name' => ['en' => $en, 'ar' => $ar], 'type' => $type, 'subtype' => $sub, 'normal_balance' => Account::normalBalanceFor($type),
                'role_key' => $role, 'is_group' => $group, 'is_system' => true, 'currency' => $currency,
                'parent_id' => $parent ? ($ids[$parent] ?? null) : null,
            ]);
            $created += (int) $acc->wasRecentlyCreated;
            $ids[$code] = $acc->id;
        }

        return $created;
    }

    /**
     * Idempotent, lazy top-up for a chart seeded before a role existed: creates only the missing role accounts (never touches existing ones;
     * if the template code is taken by a custom account the next free code is used). Returns the number created.
     *
     * @param  array<int,string>  $roles
     */
    public function ensureRoles(array $roles): int
    {
        $currency = setting('currency_code');
        $created = 0;
        foreach (self::ACCOUNTS as [$code, $en, $ar, $type, $sub, $role, $parent, $group]) {
            if (! $role || ! in_array($role, $roles, true)) {
                continue;
            }
            if (Account::withoutGlobalScopes()->where('role_key', $role)->exists()) {
                continue;
            }
            $free = $code;
            while (Account::withoutGlobalScopes()->where('code', $free)->exists()) {
                $free = (string) ((int) $free + 1);
            }
            $parentId = $parent ? Account::withoutGlobalScopes()->where('code', $parent)->value('id') : null;
            Account::withoutGlobalScopes()->create(['code' => $free, 'name' => ['en' => $en, 'ar' => $ar], 'type' => $type, 'subtype' => $sub,
                'normal_balance' => Account::normalBalanceFor($type), 'role_key' => $role, 'is_group' => $group, 'is_system' => true, 'currency' => $currency, 'parent_id' => $parentId]);
            $created++;
        }
        if ($created) {
            app(AccountResolver::class)->flush();
        }

        return $created;
    }
}
