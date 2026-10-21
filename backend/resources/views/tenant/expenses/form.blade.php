<x-resource-form :route="$route" :editing="$editing" :model="$model">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-select name="expense_category_id" :label="__('ops.fields.category')" :options="$categories" :value="$model->expense_category_id" :placeholder="__('admin.select')" required />
        <x-select name="branch_id" :label="__('ops.fields.branch')" :options="$branches" :value="$model->branch_id" :placeholder="__('admin.select')" />
        <x-input name="amount" type="number" step="0.01" min="0" :label="__('ops.fields.amount')" :value="$model->amount" required />
        <x-input name="expense_date" type="date" :label="__('ops.fields.date')" :value="$model->expense_date?->toDateString()" required />
    </div>
    <x-textarea name="note" :label="__('ops.fields.note')" :value="$model->note" />
    <div data-field>
        <label class="form-label" for="f-attachment">{{ __('ops.fields.attachment') }}</label>
        @if ($model->attachment_url)
            <a href="{{ $model->attachment_url }}" target="_blank" class="mb-2 block text-sm text-brand-600 underline">{{ __('ops.fields.attachment') }}</a>
            <x-checkbox name="remove_attachment" :label="__('ops.remove_image')" />
        @endif
        <input id="f-attachment" type="file" name="attachment" accept="image/*,.pdf" class="form-control">
    </div>
</x-resource-form>
