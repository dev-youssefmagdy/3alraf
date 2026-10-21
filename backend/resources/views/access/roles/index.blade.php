<x-layouts.admin :guard="$guard" :title="__('access.roles')">
    <x-card>
        <x-slot:actions>
            @can('roles.create')<x-button :href="route($guard.'.roles.create')">{{ __('admin.add_new') }}</x-button>@endcan
        </x-slot:actions>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 text-start text-slate-500"><tr>
                    <th class="py-2 text-start">{{ __('access.fields.name') }}</th><th class="text-start">{{ __('access.permissions') }}</th><th class="text-start">{{ __('access.users') }}</th><th></th>
                </tr></thead>
                <tbody>
                @foreach ($roles as $r)
                    <tr class="border-b border-slate-100">
                        <td class="py-2 font-medium">{{ $r['name'] }} @if ($r['system'])<span class="badge badge-muted">{{ __('access.system') }}</span>@endif</td>
                        <td>{{ $r['system'] ? __('access.all') : $r['permissions'] }}</td>
                        <td>{{ $r['users'] }}</td>
                        <td class="text-end">
                            @unless ($r['system'])
                                <div class="flex justify-end gap-2">
                                    @can('roles.edit')<a href="{{ route($guard.'.roles.edit', $r['id']) }}" class="btn-sm btn-secondary">{{ __('admin.edit') }}</a>@endcan
                                    @can('roles.delete')<button type="button" data-delete="{{ route($guard.'.roles.destroy', $r['id']) }}" class="btn-sm btn-danger">{{ __('admin.delete') }}</button>@endcan
                                </div>
                            @endunless
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts.admin>
