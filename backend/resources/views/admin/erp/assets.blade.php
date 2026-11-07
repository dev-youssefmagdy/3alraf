<x-layouts.admin :title="__('erp.cards.assets')[0]">
    @include('admin.erp._flash')
    @php $n = fn ($v) => number_format((float) $v, 2); $in = 'mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm'; @endphp
    @can('erp.assets.manage')
    <div class="mb-5 grid gap-4 lg:grid-cols-2">
        <x-card :title="__('erp_ext.fa.add')">
            <form method="POST" action="{{ route('admin.erp.assets.store') }}" class="grid gap-3 sm:grid-cols-2">@csrf<input type="hidden" name="ref" value="{{ old('ref', (string) \Illuminate\Support\Str::uuid()) }}">
                <label class="text-sm">{{ __('erp_ext.f.name') }}<input name="name" required maxlength="150" value="{{ old('name') }}" class="{{ $in }}"></label>
                <label class="text-sm">{{ __('erp_ext.fa.category') }}<input name="category" maxlength="80" value="{{ old('category') }}" class="{{ $in }}"></label>
                <label class="text-sm">{{ __('erp_ext.fa.acquired') }}<input name="acquisition_date" type="date" required value="{{ old('acquisition_date', now()->toDateString()) }}" class="{{ $in }}"></label>
                <label class="text-sm">{{ __('erp_ext.fa.cost') }}<input name="cost" type="number" step="0.01" min="0.01" required value="{{ old('cost') }}" class="{{ $in }}"></label>
                <label class="text-sm">{{ __('erp_ext.fa.salvage') }}<input name="salvage_value" type="number" step="0.01" min="0" value="{{ old('salvage_value', 0) }}" class="{{ $in }}"></label>
                <label class="text-sm">{{ __('erp_ext.fa.life') }}<input name="useful_life_months" type="number" min="1" max="1200" required value="{{ old('useful_life_months', 60) }}" class="{{ $in }}"></label>
                <label class="text-sm">{{ __('erp_ext.fa.method') }}<select name="method" class="{{ $in }}">@foreach (\App\Models\Erp\FixedAsset::METHODS as $m)<option value="{{ $m }}">{{ __('erp_ext.fa.methods.'.$m) }}</option>@endforeach</select></label>
                <label class="text-sm">{{ __('erp_ext.f.branch') }}<select name="branch_id" class="{{ $in }}"><option value="">-</option>@foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
                <label class="text-sm">{{ __('erp_ext.fa.paid_from') }}<select name="paid_from" class="{{ $in }}">@foreach ($cashRoles as $c)<option value="{{ $c }}">{{ __('erp_ext.roles.'.$c) }}</option>@endforeach<option value="payable">{{ __('erp_ext.roles.payable') }}</option></select></label>
                <label class="text-sm">{{ __('erp_ext.fa.supplier') }}<select name="supplier_id" class="{{ $in }}"><option value="">-</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></label>
                <div class="sm:col-span-2"><x-button type="submit">{{ __('erp_ext.f.save') }}</x-button></div></form>
        </x-card>
        <x-card :title="__('erp_ext.fa.depreciate')">
            <form method="POST" action="{{ route('admin.erp.assets.depreciate-all') }}" class="flex flex-wrap items-end gap-3">@csrf
                <label class="text-sm">{{ __('erp_ext.fa.period') }}<input name="period" pattern="\d{4}-\d{2}" required value="{{ now()->format('Y-m') }}" class="{{ $in }}"></label><x-button type="submit">{{ __('erp_ext.fa.depreciate') }}</x-button></form>
            <div class="mt-4 text-sm"><div class="mb-1 font-semibold">{{ __('erp_ext.fa.recon') }}</div>
                <div class="{{ $rep['reconciled'] ? 'text-emerald-700' : 'text-red-600' }}">{{ $rep['reconciled'] ? __('erp_ext.fa.recon_ok') : __('erp_ext.fa.recon_bad') }}</div>
                <div class="mt-1 text-slate-500">{{ __('erp_ext.fa.cost') }}: {{ $n($rep['cost']) }} / {{ $n($rep['gl_cost']) }} &middot; {{ __('erp_ext.fa.accumulated') }}: {{ $n($rep['accumulated']) }} / {{ $n($rep['gl_accumulated']) }}</div></div>
        </x-card>
    </div>
    @endcan
    @if ($rep['categories'])
    <x-card :title="__('erp_ext.fa.by_category')" class="mb-5"><table class="w-full text-sm"><thead><tr class="border-b text-slate-500"><th class="py-2 text-start">{{ __('erp_ext.fa.category') }}</th><th class="text-end">{{ __('erp_ext.fa.cost') }}</th><th class="text-end">{{ __('erp_ext.fa.accumulated') }}</th><th class="text-end">{{ __('erp_ext.fa.nbv') }}</th></tr></thead><tbody>
        @foreach ($rep['categories'] as $k => $c)<tr class="border-b border-slate-100"><td class="py-1.5">{{ $k }}</td><td class="text-end tabular-nums">{{ $n($c['cost']) }}</td><td class="text-end tabular-nums">{{ $n($c['acc']) }}</td><td class="text-end tabular-nums">{{ $n($c['nbv']) }}</td></tr>@endforeach
        <tr class="font-semibold"><td class="py-2">{{ __('erp_ext.f.total') }}</td><td class="text-end tabular-nums">{{ $n($rep['cost']) }}</td><td class="text-end tabular-nums">{{ $n($rep['accumulated']) }}</td><td class="text-end tabular-nums">{{ $n($rep['nbv']) }}</td></tr></tbody></table></x-card>
    @endif
    <x-card :title="__('erp_ext.fa.assets')"><div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="border-b text-slate-500"><th class="py-2 text-start">{{ __('erp_ext.f.name') }}</th><th class="text-start">{{ __('erp_ext.fa.category') }}</th><th class="text-start">{{ __('erp_ext.f.branch') }}</th><th class="text-end">{{ __('erp_ext.fa.cost') }}</th><th class="text-end">{{ __('erp_ext.fa.accumulated') }}</th><th class="text-end">{{ __('erp_ext.fa.nbv') }}</th><th>{{ __('erp_ext.f.status') }}</th><th></th></tr></thead><tbody>
        @forelse ($rep['assets'] as $a)<tr class="border-b border-slate-100"><td class="py-1.5">{{ $a->name }}</td><td>{{ $a->category }}</td><td>{{ $a->branch?->name }}</td><td class="text-end tabular-nums">{{ $n($a->cost) }}</td><td class="text-end tabular-nums">{{ $n($a->accumulated_depreciation) }}</td><td class="text-end tabular-nums">{{ $n($a->status === 'active' ? $a->book_value : 0) }}</td><td>{{ __('erp_ext.fa.status.'.$a->status) }}</td><td class="text-end"><a class="text-brand-600" href="{{ route('admin.erp.assets.show', $a->id) }}">{{ __('erp_ext.f.view') }}</a></td></tr>
        @empty<tr><td colspan="8" class="py-4 text-slate-500">{{ __('erp_ext.f.none') }}</td></tr>@endforelse
    </tbody></table></div></x-card>
</x-layouts.admin>
