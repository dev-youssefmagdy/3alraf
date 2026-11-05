<x-resource-form :route="$route" :editing="$editing" :model="$model">
    <x-translatable-tabs name="name" :label="__('admin.fields.name')" :languages="$languages" :values="$model->getTranslations('name')" />
    @can('erp.expenses.manage')
        {{-- ERP-013: ledger account the category's expenses are debited to (unmapped = Other business expenses) --}}
        <x-select name="erp_account_id" :label="__('erp_acc.exp.ledger_account')" :options="$erpAccounts" :value="$model->erp_account_id" :placeholder="__('erp_acc.exp.ledger_default')" search />
        <p class="-mt-2 text-xs text-slate-500">{{ __('erp_acc.exp.ledger_hint') }}</p>
    @endcan
    <x-checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$model->is_active" />
</x-resource-form>
