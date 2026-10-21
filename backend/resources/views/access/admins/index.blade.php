<x-layouts.admin :guard="$guard" :title="__('access.admins')">
    <x-card>
        <x-slot:actions>
            @can('admins.create')<x-button :href="route($guard.'.admins.create')">{{ __('admin.add_new') }}</x-button>@endcan
        </x-slot:actions>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 text-slate-500"><tr>
                    <th class="py-2 text-start">{{ __('access.fields.name') }}</th><th class="text-start">{{ __('access.fields.email') }}</th><th class="text-start">{{ __('access.roles') }}</th><th class="text-start">{{ __('admin.status') }}</th><th></th>
                </tr></thead>
                <tbody>
                @foreach ($admins as $a)
                    <tr class="border-b border-slate-100">
                        <td class="py-2 font-medium">{{ $a->name }}</td><td>{{ $a->email }}</td>
                        <td>@foreach ($a->roles as $r)<span class="badge badge-muted">{{ $access->displayName($guard, $r) }}</span> @endforeach</td>
                        <td>{!! $a->is_active ? '<span class="badge badge-success">'.e(__('admin.active')).'</span>' : '<span class="badge badge-danger">'.e(__('admin.inactive')).'</span>' !!}</td>
                        <td class="text-end"><div class="flex justify-end gap-2">
                            @can('admins.edit')<a href="{{ route($guard.'.admins.edit', $a->id) }}" class="btn-sm btn-secondary">{{ __('admin.edit') }}</a>@endcan
                            @if ($a->id !== auth($guard)->id())@can('admins.delete')<button type="button" data-delete="{{ route($guard.'.admins.destroy', $a->id) }}" class="btn-sm btn-danger">{{ __('admin.delete') }}</button>@endcan @endif
                        </div></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts.admin>
