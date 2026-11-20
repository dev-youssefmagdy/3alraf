<x-layouts.admin :title="__('access.admins')">
    <x-slot:header><x-ui.page-header :title="__('access.admins')" /></x-slot:header>
    <x-ui.card>
        <x-slot:actions>
            @can('admins.create')<x-ui.button :href="route('admin.admins.create')">{{ __('admin.add_new') }}</x-ui.button>@endcan
        </x-slot:actions>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-panel-border text-panel-text-subtle"><tr>
                    <th class="py-2 text-start">{{ __('access.fields.name') }}</th><th class="text-start">{{ __('access.fields.email') }}</th><th class="text-start">{{ __('access.roles') }}</th><th class="text-start">{{ __('admin.status') }}</th><th></th>
                </tr></thead>
                <tbody>
                @foreach ($admins as $a)
                    <tr class="border-b border-panel-border">
                        <td class="py-2 font-medium">{{ $a->name }}</td><td>{{ $a->email }}</td>
                        <td>@foreach ($a->roles as $r)<span class="badge badge-muted">{{ $access->displayName('admin', $r) }}</span> @endforeach</td>
                        <td>{!! $a->is_active ? '<span class="badge badge-success">'.e(__('admin.active')).'</span>' : '<span class="badge badge-danger">'.e(__('admin.inactive')).'</span>' !!}</td>
                        <td class="text-end"><div class="flex justify-end gap-2">
                            @can('admins.edit')<a href="{{ route('admin.admins.edit', $a->id) }}" class="btn-sm btn-secondary">{{ __('admin.edit') }}</a>@endcan
                            @if ($a->id !== auth('admin')->id())@can('admins.delete')<button type="button" data-delete="{{ route('admin.admins.destroy', $a->id) }}" class="btn-sm btn-danger">{{ __('admin.delete') }}</button>@endcan @endif
                        </div></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>
</x-layouts.admin>
