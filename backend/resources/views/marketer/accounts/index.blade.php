{{-- Marketer panel → Team → Marketers (marketers.view; changes need marketers.manage). --}}
@php($a = fn ($k) => __('marketing.accounts.'.$k))
<x-layouts.marketer :title="$a('title')">
    <x-slot:header>
        <x-ui.page-header :title="$a('title')" :subtitle="$a('hint')" :count="count($marketers)">
            @can('marketers.manage')<x-ui.button :href="route('marketer.accounts.create')" icon="plus">{{ __('admin.add_new') }}</x-ui.button>@endcan
        </x-ui.page-header>
    </x-slot:header>
    <div class="md:rounded-2xl md:border md:border-panel-border md:bg-panel-surface md:p-3 md:shadow-panel-xs" data-marketers>
        <x-ui.table>
            <thead><tr>
                <th>{{ __('access.fields.name') }}</th><th>{{ __('access.fields.phone') }}</th><th>{{ __('access.fields.roles') }}</th>
                <th>{{ $a('last_login') }}</th><th>{{ __('admin.status') }}</th><th></th>
            </tr></thead>
            <tbody>
            @foreach ($marketers as $m)
                <tr>
                    <td data-label="" class="font-semibold">{{ $m->name }}<span class="block text-xs font-normal text-panel-text-muted">{{ $m->email }}</span></td>
                    <td data-label="{{ __('access.fields.phone') }}" dir="ltr">{{ \App\Support\AdminPhone::mask($m->phone) }}</td>
                    <td data-label="{{ __('access.fields.roles') }}">@foreach ($m->roles as $r)<x-ui.badge :tone="$r->name === \App\Support\Marketing\MarketingPermissions::OWNER_ROLE ? 'warning' : 'neutral'" size="sm">{{ $a('roles.'.$r->name) }}</x-ui.badge> @endforeach</td>
                    <td data-label="{{ $a('last_login') }}" class="text-panel-text-muted">{{ $m->last_login_at?->diffForHumans() ?? '—' }}</td>
                    <td data-label="{{ __('admin.status') }}"><x-ui.badge :tone="$m->is_active ? 'success' : 'danger'" size="sm" dot>{{ $m->is_active ? __('admin.active') : __('admin.inactive') }}</x-ui.badge></td>
                    <td class="ui-table-actions"><div class="flex justify-end gap-2">
                        @can('marketers.manage')
                            <a href="{{ route('marketer.accounts.edit', $m->id) }}" class="btn-sm btn-secondary">{{ __('admin.edit') }}</a>
                            @if ($m->id !== auth('marketer')->id())<button type="button" data-delete="{{ route('marketer.accounts.destroy', $m->id) }}" class="btn-sm btn-danger">{{ __('admin.delete') }}</button>@endif
                        @endcan
                    </div></td>
                </tr>
            @endforeach
            </tbody>
        </x-ui.table>
    </div>
</x-layouts.marketer>
