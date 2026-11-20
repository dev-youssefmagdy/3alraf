<x-layouts.admin :title="__('erp.cards.accounts')[0]">
    <x-slot:header><x-ui.page-header :title="__('erp.cards.accounts')[0]" /></x-slot:header>
    @include('admin.erp._flash')
    @php $can = auth('admin')->user()->can('erp.accounts.manage'); @endphp
    @if ($can)
    <x-ui.card :title="__('erp.a.new')" class="mb-5">
        <form method="POST" action="{{ route('admin.erp.accounts.store') }}" class="grid gap-3 md:grid-cols-6">
            @csrf
            <input name="code" required placeholder="{{ __('erp.f.code') }}" value="{{ old('code') }}" class="form-control">
            <input name="name_en" required placeholder="{{ __('erp.f.name_en') }}" value="{{ old('name_en') }}" class="form-control md:col-span-2">
            <input name="name_ar" placeholder="{{ __('erp.f.name_ar') }}" value="{{ old('name_ar') }}" class="form-control md:col-span-2">
            <select name="type" class="form-control">@foreach ($types as $t)<option value="{{ $t }}">{{ __('erp.types.'.$t) }}</option>@endforeach</select>
            <select name="parent_id" class="form-control md:col-span-3"><option value="">{{ __('erp.f.parent') }} -</option>@foreach ($accounts->where('is_active', true) as $a)<option value="{{ $a->id }}">{{ $a->code }} {{ $a->name }}</option>@endforeach</select>
            <label class="flex items-center gap-2 text-sm md:col-span-2"><input type="checkbox" name="is_group" value="1"> {{ __('erp.f.group') }}</label>
            <x-ui.button type="submit">{{ __('erp.a.add') }}</x-ui.button>
        </form>
    </x-ui.card>
    @endif
    <x-ui.card>
        <x-ui.table>
            <thead><tr><th>{{ __('erp.f.code') }}</th><th>{{ __('erp.f.name') }}</th><th>{{ __('erp.f.type') }}</th><th class="text-end">{{ __('erp.f.balance') }}</th><th></th></tr></thead>
            <tbody>
            @foreach ($accounts as $a)
                <tr class="border-b border-panel-border {{ $a->is_group ? 'bg-panel-surface-sunken font-semibold' : '' }} {{ $a->is_active ? '' : 'opacity-50' }}">
                    <td data-label="" class="font-mono">{{ $a->code }}</td>
                    <td data-label="{{ __('erp.f.name') }}">{{ $a->parent_id ? '↳ ' : '' }}{{ $a->name }} @unless ($a->is_active)<span class="text-xs text-panel-text-subtle">({{ __('erp.a.archived') }})</span>@endunless</td>
                    <td data-label="{{ __('erp.f.type') }}">{{ __('erp.types.'.$a->type) }}</td>
                    <td data-label="{{ __('erp.f.balance') }}" class="text-end tabular-nums">{{ $a->is_group ? '' : number_format((float) $a->current_balance, 2) }}</td>
                    <td class="ui-table-actions text-end">@if ($can && ! $a->role_key)
                        @if ($a->is_active)<form method="POST" action="{{ route('admin.erp.accounts.destroy', $a->id) }}" onsubmit="return confirm(@js(__('erp.a.confirm')))" class="inline">@csrf @method('DELETE')<button class="btn-sm btn-danger">{{ __('erp.a.archive') }}</button></form>
                        @else<form method="POST" action="{{ route('admin.erp.accounts.restore', $a->id) }}" class="inline">@csrf<button class="btn-sm btn-secondary">{{ __('erp.a.restore') }}</button></form>@endif
                    @endif</td>
                </tr>
            @endforeach
            </tbody>
        </x-ui.table>
    </x-ui.card>
</x-layouts.admin>
