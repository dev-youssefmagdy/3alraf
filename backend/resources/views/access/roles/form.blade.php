@php $editing = $role->exists; @endphp
<x-layouts.admin :title="$editing ? __('admin.edit') : __('admin.add_new')">
    <x-slot:header><x-ui.page-header :title="$editing ? __('admin.edit') : __('admin.add_new')" /></x-slot:header>
        <form data-ajax-form @if ($editing) data-method="PUT" @endif data-validate-url="{{ route('admin.roles.validate', $editing ? [$role->id] : []) }}"
            action="{{ $editing ? route('admin.roles.update', $role->id) : route('admin.roles.store') }}" method="POST" class="mx-auto max-w-5xl space-y-5" novalidate data-perm-matrix>
            @csrf
            <x-ui.form-section :title="__('ui.sections.basic')" :cols="1">
                <x-ui.input name="name" :label="__('access.fields.name')" :value="$name" required class="max-w-md" />
            </x-ui.form-section>
            <x-ui.form-section :title="__('ui.sections.permissions')" :cols="1">
            <div>
                <div class="mb-2 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-panel-text">{{ __('access.permissions') }}</h3>
                    <label class="inline-flex items-center gap-2 text-xs text-panel-text-muted"><input type="checkbox" data-perm-all class="size-4 rounded border-panel-border-strong"> {{ __('access.select_all') }}</label>
                </div>
                <div class="overflow-x-auto rounded-lg border border-panel-border">
                    <table class="w-full text-sm">
                        <tbody>
                        @foreach ($matrix as $module => $actions)
                            <tr class="border-b border-panel-border last:border-0">
                                <td class="w-56 bg-panel-surface-sunken px-3 py-2 font-medium text-panel-text">
                                    <label class="inline-flex items-center gap-2"><input type="checkbox" data-perm-row class="size-4 rounded border-panel-border-strong"> {{ \Illuminate\Support\Facades\Lang::has("access.modules.".str_replace('.', '_', $module)) ? __("access.modules.".str_replace('.', '_', $module)) : \Illuminate\Support\Str::headline($module) }}</label>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex flex-wrap gap-x-5 gap-y-1">
                                    @foreach ($actions as $action => $perm)
                                        <label class="inline-flex items-center gap-1.5 text-panel-text-muted">
                                            <input type="checkbox" name="permissions[]" value="{{ $perm }}" @checked(in_array($perm, $granted, true)) class="size-4 rounded border-panel-border-strong text-panel-accent-text">
                                            @php $ak = 'access.actions.'.str_replace('.', '_', $action); @endphp{{ \Illuminate\Support\Facades\Lang::has($ak) ? __($ak) : \Illuminate\Support\Str::headline($action) }}
                                        </label>
                                    @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            </x-ui.form-section>
            <x-ui.save-bar :cancel="route('admin.roles.index')" />
        </form>
</x-layouts.admin>
