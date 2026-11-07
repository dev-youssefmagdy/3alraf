<x-layouts.admin :title="__('erp.cards.accounts')[0]">
    @include('admin.erp._flash')
    @php $can = auth('admin')->user()->can('erp.accounts.manage'); @endphp
    @if ($can)
    <x-card :title="__('erp.a.new')" class="mb-5">
        <form method="POST" action="{{ route('admin.erp.accounts.store') }}" class="grid gap-3 md:grid-cols-6">
            @csrf
            <input name="code" required placeholder="{{ __('erp.f.code') }}" value="{{ old('code') }}" class="rounded border border-slate-300 px-2 py-1.5 text-sm">
            <input name="name_en" required placeholder="{{ __('erp.f.name_en') }}" value="{{ old('name_en') }}" class="rounded border border-slate-300 px-2 py-1.5 text-sm md:col-span-2">
            <input name="name_ar" placeholder="{{ __('erp.f.name_ar') }}" value="{{ old('name_ar') }}" class="rounded border border-slate-300 px-2 py-1.5 text-sm md:col-span-2">
            <select name="type" class="rounded border border-slate-300 px-2 py-1.5 text-sm">@foreach ($types as $t)<option value="{{ $t }}">{{ __('erp.types.'.$t) }}</option>@endforeach</select>
            <select name="parent_id" class="rounded border border-slate-300 px-2 py-1.5 text-sm md:col-span-3"><option value="">{{ __('erp.f.parent') }} -</option>@foreach ($accounts->where('is_active', true) as $a)<option value="{{ $a->id }}">{{ $a->code }} {{ $a->name }}</option>@endforeach</select>
            <label class="flex items-center gap-2 text-sm md:col-span-2"><input type="checkbox" name="is_group" value="1"> {{ __('erp.f.group') }}</label>
            <x-button type="submit">{{ __('erp.a.add') }}</x-button>
        </form>
    </x-card>
    @endif
    <x-card>
        <div class="overflow-x-auto"><table class="w-full text-sm">
            <thead><tr class="border-b text-start text-slate-500"><th class="py-2 text-start">{{ __('erp.f.code') }}</th><th class="text-start">{{ __('erp.f.name') }}</th><th class="text-start">{{ __('erp.f.type') }}</th><th class="text-end">{{ __('erp.f.balance') }}</th><th></th></tr></thead>
            <tbody>
            @foreach ($accounts as $a)
                <tr class="border-b border-slate-100 {{ $a->is_group ? 'bg-slate-50 font-semibold' : '' }} {{ $a->is_active ? '' : 'opacity-50' }}">
                    <td class="py-1.5 font-mono">{{ $a->code }}</td>
                    <td>{{ $a->parent_id ? '↳ ' : '' }}{{ $a->name }} @unless ($a->is_active)<span class="text-xs text-slate-500">({{ __('erp.a.archived') }})</span>@endunless</td>
                    <td>{{ __('erp.types.'.$a->type) }}</td>
                    <td class="text-end tabular-nums">{{ $a->is_group ? '' : number_format((float) $a->current_balance, 2) }}</td>
                    <td class="text-end">@if ($can && ! $a->role_key)
                        @if ($a->is_active)<form method="POST" action="{{ route('admin.erp.accounts.destroy', $a->id) }}" onsubmit="return confirm(@js(__('erp.a.confirm')))" class="inline">@csrf @method('DELETE')<button class="btn-sm btn-danger">{{ __('erp.a.archive') }}</button></form>
                        @else<form method="POST" action="{{ route('admin.erp.accounts.restore', $a->id) }}" class="inline">@csrf<button class="btn-sm btn-secondary">{{ __('erp.a.restore') }}</button></form>@endif
                    @endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </x-card>
</x-layouts.admin>
