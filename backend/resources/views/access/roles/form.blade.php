@php $editing = $role->exists; @endphp
<x-layouts.admin :guard="$guard" :title="$editing ? __('admin.edit') : __('admin.add_new')">
    <x-card>
        <form data-ajax-form @if ($editing) data-method="PUT" @endif data-validate-url="{{ route($guard.'.roles.validate', $editing ? [$role->id] : []) }}"
            action="{{ $editing ? route($guard.'.roles.update', $role->id) : route($guard.'.roles.store') }}" method="POST" class="space-y-5" novalidate data-perm-matrix>
            @csrf
            <x-input name="name" :label="__('access.fields.name')" :value="$name" required class="max-w-md" />
            <div>
                <div class="mb-2 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-700">{{ __('access.permissions') }}</h3>
                    <label class="inline-flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" data-perm-all class="size-4 rounded border-slate-300"> {{ __('access.select_all') }}</label>
                </div>
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="w-full text-sm">
                        <tbody>
                        @foreach ($matrix as $module => $actions)
                            <tr class="border-b border-slate-100 last:border-0">
                                <td class="w-56 bg-slate-50 px-3 py-2 font-medium text-slate-700">
                                    <label class="inline-flex items-center gap-2"><input type="checkbox" data-perm-row class="size-4 rounded border-slate-300"> {{ \Illuminate\Support\Facades\Lang::has("access.modules.".str_replace('.', '_', $module)) ? __("access.modules.".str_replace('.', '_', $module)) : \Illuminate\Support\Str::headline($module) }}</label>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex flex-wrap gap-x-5 gap-y-1">
                                    @foreach ($actions as $action => $perm)
                                        <label class="inline-flex items-center gap-1.5 text-slate-600">
                                            <input type="checkbox" name="permissions[]" value="{{ $perm }}" @checked(in_array($perm, $granted, true)) class="size-4 rounded border-slate-300 text-brand-600">
                                            {{ \Illuminate\Support\Facades\Lang::has("access.actions.$action") ? __("access.actions.$action") : \Illuminate\Support\Str::headline($action) }}
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
            <x-form-actions :cancel="route($guard.'.roles.index')" />
        </form>
    </x-card>
</x-layouts.admin>
