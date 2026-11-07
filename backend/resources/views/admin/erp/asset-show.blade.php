<x-layouts.admin :title="$a->name">
    @include('admin.erp._flash')
    @php $n = fn ($v) => number_format((float) $v, 2); $in = 'mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm'; @endphp
    <div class="mb-4 flex flex-wrap items-center gap-4 text-sm">
        <a class="text-brand-600" href="{{ route('admin.erp.assets.index') }}">&larr; {{ __('erp_ext.fa.assets') }}</a>
        <span>{{ __('erp_ext.fa.cost') }}: <b class="tabular-nums">{{ $n($a->cost) }}</b></span><span>{{ __('erp_ext.fa.salvage') }}: <b class="tabular-nums">{{ $n($a->salvage_value) }}</b></span>
        <span>{{ __('erp_ext.fa.accumulated') }}: <b class="tabular-nums">{{ $n($a->accumulated_depreciation) }}</b></span><span>{{ __('erp_ext.fa.nbv') }}: <b class="tabular-nums">{{ $n($a->status === 'active' ? $a->book_value : 0) }}</b></span>
        <span>{{ __('erp_ext.fa.status.'.$a->status) }}</span>@if ($a->status === 'disposed')<span>{{ __('erp_ext.fa.gain_loss') }}: <b class="tabular-nums">{{ $n($a->disposal_gain_loss) }}</b></span>@endif
    </div>
    @if ($a->status === 'active' && auth('admin')->user()->can('erp.assets.manage'))
    <div class="mb-5 grid gap-4 lg:grid-cols-3">
        <x-card :title="__('erp_ext.fa.depreciate')"><form method="POST" action="{{ route('admin.erp.assets.depreciate', $a->id) }}" class="flex items-end gap-3">@csrf<label class="text-sm">{{ __('erp_ext.fa.period') }}<input name="period" pattern="\d{4}-\d{2}" required value="{{ now()->format('Y-m') }}" class="{{ $in }}"></label><x-button type="submit">{{ __('erp_ext.f.save') }}</x-button></form></x-card>
        <x-card :title="__('erp_ext.fa.transfer')"><form method="POST" action="{{ route('admin.erp.assets.transfer', $a->id) }}" class="flex items-end gap-3">@csrf<label class="text-sm">{{ __('erp_ext.f.branch') }}<select name="branch_id" required class="{{ $in }}">@foreach ($branches as $b)<option value="{{ $b->id }}" @selected($a->branch_id == $b->id)>{{ $b->name }}</option>@endforeach</select></label><x-button type="submit" variant="secondary">{{ __('erp_ext.f.save') }}</x-button></form></x-card>
        <x-card :title="__('erp_ext.fa.dispose')"><form method="POST" action="{{ route('admin.erp.assets.dispose', $a->id) }}" class="grid gap-3 sm:grid-cols-2" onsubmit="return confirm(@js(__('erp_ext.fa.dispose').'?'))">@csrf
            <label class="text-sm">{{ __('erp_ext.f.date') }}<input name="date" type="date" value="{{ now()->toDateString() }}" required class="{{ $in }}"></label>
            <label class="text-sm">{{ __('erp_ext.fa.proceeds') }}<input name="proceeds" type="number" step="0.01" min="0" value="0" class="{{ $in }}"></label>
            <label class="text-sm">{{ __('erp_ext.f.account') }}<select name="into" class="{{ $in }}">@foreach ($cashRoles as $c)<option value="{{ $c }}">{{ __('erp_ext.roles.'.$c) }}</option>@endforeach</select></label>
            <div class="self-end"><x-button type="submit" variant="secondary">{{ __('erp_ext.fa.dispose') }}</x-button></div></form></x-card>
    </div>
    @endif
    <x-card :title="__('erp_ext.fa.entries')"><table class="w-full text-sm"><tbody>@foreach ($entries as $e)<tr class="border-b border-slate-100"><td class="py-1.5">{{ $e->entry_date->format('Y-m-d') }}</td><td>@include('admin.erp._jlink', ['id' => $e->id, 'no' => $e->number])</td><td>{{ $e->description }}</td><td class="text-end tabular-nums">{{ $n($e->total) }}</td></tr>@endforeach</tbody></table></x-card>
</x-layouts.admin>
