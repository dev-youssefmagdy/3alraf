<x-resource-form :route="$route" :editing="$editing" :model="$model">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-select name="expense_category_id" :label="__('ops.fields.category')" :options="$categories" :value="$model->expense_category_id" :placeholder="__('admin.select')" required />
        <x-select name="branch_id" :label="__('ops.fields.branch')" :options="$branches" :value="$model->branch_id" :placeholder="__('admin.select')" />
        <x-input name="amount" type="number" step="0.01" min="0" :label="__('ops.fields.amount')" :value="$model->amount" required />
        <x-input name="expense_date" type="date" :label="__('ops.fields.date')" :value="$model->expense_date?->toDateString()" required />
    </div>
    {{-- ERP-013: how the expense was settled drives the ledger credit (paid from cash/bank/... or an unpaid supplier bill on Accounts payable) --}}
    <fieldset class="rounded-lg border border-slate-200 p-4" data-expense-erp>
        <legend class="px-1 text-sm font-semibold text-slate-700">{{ __('erp_acc.exp.legend') }}</legend>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-select name="is_paid" :label="__('erp_acc.exp.status')" :options="['1' => __('erp_acc.exp.paid'), '0' => __('erp_acc.exp.unpaid')]" :value="$model->is_paid === false ? '0' : '1'" data-exp-paid />
            <x-select name="paid_from" :label="__('erp_acc.exp.paid_from')" :options="$paidFrom" :value="$model->paid_from ?: 'cash'" data-exp-from />
            <x-select name="supplier_id" :label="__('erp_acc.exp.supplier')" :options="$suppliers" :value="$model->supplier_id" :placeholder="__('admin.select')" search data-exp-supplier />
        </div>
        <p class="mt-2 text-xs text-slate-500" data-exp-hint-paid>{{ __('erp_acc.exp.hint_paid') }}</p>
        <p class="mt-2 hidden text-xs text-slate-500" data-exp-hint-unpaid>{{ __('erp_acc.exp.hint_unpaid') }}</p>
    </fieldset>
    <x-textarea name="note" :label="__('ops.fields.note')" :value="$model->note" />
    <div data-field>
        <label class="form-label" for="f-attachment">{{ __('ops.fields.attachment') }}</label>
        @if ($model->attachment_url)
            <a href="{{ $model->attachment_url }}" target="_blank" class="mb-2 block text-sm text-brand-600 underline">{{ __('ops.fields.attachment') }}</a>
            <x-checkbox name="remove_attachment" :label="__('ops.remove_image')" />
        @endif
        <input id="f-attachment" type="file" name="attachment" accept="image/*,.pdf" class="form-control">
    </div>
    <script>
    (function () {
        const box = document.querySelector('[data-expense-erp]'); if (!box) return;
        const paid = box.querySelector('[data-exp-paid]'), from = box.querySelector('[data-exp-from]').closest('[data-field]'), sup = box.querySelector('[data-exp-supplier]').closest('[data-field]');
        const sync = () => { const p = paid.value !== '0'; from.classList.toggle('hidden', !p); sup.classList.toggle('hidden', p);
            box.querySelector('[data-exp-hint-paid]').classList.toggle('hidden', !p); box.querySelector('[data-exp-hint-unpaid]').classList.toggle('hidden', p); };
        paid.addEventListener('change', sync); sync();
    })();
    </script>
</x-resource-form>
