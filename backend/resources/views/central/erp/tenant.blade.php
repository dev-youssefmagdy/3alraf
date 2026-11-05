<x-layouts.admin guard="central" :title="$tenant->name.' - '.__('erp_central.drill')">
    <a href="{{ route('central.erp.overview') }}" class="mb-4 inline-block text-sm text-brand-600">&larr; {{ __('erp_central.cards.overview')[0] }}</a>
    @php $in = 'mt-1 block rounded border border-slate-300 px-2 py-1.5'; $n = fn ($v) => number_format((float) $v, 2); @endphp
    <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" data-tenant-banner>
        <strong>{{ $tenant->name }}</strong> - {{ __('erp_central.tenant_only') }} <strong>{{ $tenant->currency_code }}</strong> ({{ __('erp_central.read_only') }})
    </div>
    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
            <label>{{ __('erp_central.f.from') }}<input type="date" name="from" value="{{ $from }}" class="{{ $in }}"></label>
            <label>{{ __('erp_central.f.to') }}<input type="date" name="to" value="{{ $to }}" class="{{ $in }}"></label>
            <x-button type="submit">{{ __('erp_central.f.filter') }}</x-button>
            <a class="text-brand-600" href="{{ route('central.erp.transactions', ['tenant_id' => $tenant->id]) }}">{{ __('erp_central.cards.transactions')[0] }}</a>
            <a class="text-brand-600" href="{{ route('central.erp.anomalies', ['tenant_id' => $tenant->id]) }}">{{ __('erp_central.cards.anomalies')[0] }}</a>
        </form>
    </x-card>
    @if ($row)
    <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach (['cash', 'ar', 'ap', 'inventory', 'equity'] as $k)<div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs text-slate-500">{{ __('erp_central.col.'.$k) }} ({{ $tenant->currency_code }})</div><div class="mt-1 text-xl font-bold tabular-nums">{{ $n($row[$k]) }}</div></div>@endforeach
    </div>
    @endif
    @foreach (['trial-balance', 'profit-loss', 'balance-sheet'] as $k)
        <x-card :title="$reports[$k]['title'].' - '.$tenant->name.' ('.$tenant->currency_code.')'" class="mb-5">
            @foreach ($reports[$k]['tables'] as $t)@include('tenant.erp._rtable', ['t' => $t])@endforeach
            @foreach ($reports[$k]['notes'] as $note)<p class="mt-3 text-sm text-amber-700">{{ $note }}</p>@endforeach
        </x-card>
    @endforeach
</x-layouts.admin>
