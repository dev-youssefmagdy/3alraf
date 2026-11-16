{{-- Marketer panel → Team → Marketers (marketers.view; changes need marketers.manage). --}}
@php($a = fn ($k) => __('marketing.accounts.'.$k))
<x-layouts.marketer :title="$a('title')">
    <x-card>
        <x-slot:actions>
            @can('marketers.manage')<x-button :href="route('marketer.accounts.create')">{{ __('admin.add_new') }}</x-button>@endcan
        </x-slot:actions>
        <div class="overflow-x-auto" data-marketers>
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 text-slate-500"><tr>
                    <th class="py-2 text-start">{{ __('access.fields.name') }}</th><th class="text-start">{{ __('access.fields.phone') }}</th><th class="text-start">{{ __('access.fields.roles') }}</th>
                    <th class="text-start">{{ $a('last_login') }}</th><th class="text-start">{{ __('admin.status') }}</th><th></th>
                </tr></thead>
                <tbody>
                @foreach ($marketers as $m)
                    <tr class="border-b border-slate-100">
                        <td class="py-2 font-medium">{{ $m->name }}<span class="block text-xs text-slate-500">{{ $m->email }}</span></td>
                        <td dir="ltr" class="text-start">{{ \App\Support\AdminPhone::mask($m->phone) }}</td>
                        <td>@foreach ($m->roles as $r)<span class="badge {{ $r->name === \App\Support\Marketing\MarketingPermissions::OWNER_ROLE ? 'badge-warn' : 'badge-muted' }}">{{ $a('roles.'.$r->name) }}</span> @endforeach</td>
                        <td class="text-slate-500">{{ $m->last_login_at?->diffForHumans() ?? '—' }}</td>
                        <td>{!! $m->is_active ? '<span class="badge badge-success">'.e(__('admin.active')).'</span>' : '<span class="badge badge-danger">'.e(__('admin.inactive')).'</span>' !!}</td>
                        <td class="text-end"><div class="flex justify-end gap-2">
                            @can('marketers.manage')
                                <a href="{{ route('marketer.accounts.edit', $m->id) }}" class="btn-sm btn-secondary">{{ __('admin.edit') }}</a>
                                @if ($m->id !== auth('marketer')->id())<button type="button" data-delete="{{ route('marketer.accounts.destroy', $m->id) }}" class="btn-sm btn-danger">{{ __('admin.delete') }}</button>@endif
                            @endcan
                        </div></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-xs text-slate-500">{{ $a('hint') }}</p>
    </x-card>
</x-layouts.marketer>
