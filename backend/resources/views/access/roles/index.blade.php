<x-layouts.admin :title="__('access.roles')">
    <x-slot:header><x-ui.page-header :title="__('access.roles')" /></x-slot:header>
    <x-ui.card>
        <x-slot:actions>
            @can('roles.create')<x-ui.button :href="route('admin.roles.create')">{{ __('admin.add_new') }}</x-ui.button>@endcan
        </x-slot:actions>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-panel-border text-start text-panel-text-subtle"><tr>
                    <th class="py-2 text-start">{{ __('access.fields.name') }}</th><th class="text-start">{{ __('access.permissions') }}</th><th class="text-start">{{ __('access.users') }}</th><th></th>
                </tr></thead>
                <tbody>
                @foreach ($roles as $r)
                    <tr class="border-b border-panel-border">
                        <td class="py-2 font-medium">{{ $r['name'] }} @if ($r['system'])<span class="badge badge-muted">{{ __('access.system') }}</span>@endif</td>
                        <td>{{ $r['system'] ? __('access.all') : $r['permissions'] }}</td>
                        <td>{{ $r['users'] }}</td>
                        <td class="text-end">
                            @unless ($r['system'])
                                <div class="flex justify-end gap-2">
                                    @can('roles.edit')<a href="{{ route('admin.roles.edit', $r['id']) }}" class="btn-sm btn-secondary">{{ __('admin.edit') }}</a>@endcan
                                    @can('roles.delete')<button type="button" data-delete="{{ route('admin.roles.destroy', $r['id']) }}" class="btn-sm btn-danger">{{ __('admin.delete') }}</button>@endcan
                                </div>
                            @endunless
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>
</x-layouts.admin>
