<x-resource-form :route="$route" :editing="$editing" :model="$model">
    <x-input name="name" :label="__('admin.fields.name')" :value="$model->name" required />
    <div class="grid gap-4 sm:grid-cols-2">
        <x-input name="contact_name" :label="__('ops.fields.contact_name')" :value="$model->contact_name" />
        <x-input name="phone" :label="__('ops.fields.phone')" :value="$model->phone" />
        <x-input name="email" type="email" :label="__('ops.fields.email')" :value="$model->email" />
        <x-input name="address" :label="__('ops.fields.address')" :value="$model->address" />
    </div>
    <x-textarea name="notes" :label="__('ops.fields.notes')" :value="$model->notes" />
    <x-checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$model->is_active" />
</x-resource-form>
